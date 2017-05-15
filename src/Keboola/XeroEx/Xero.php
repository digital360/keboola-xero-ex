<?php

use Keboola\Json\Parser;

require_once dirname(__FILE__).'/XeroOAuth-PHP/lib/XeroOAuth.php';

class Xero
{
	private $signatures = array(
		'application_type' => 'Private',
		'oauth_callback' => 'oob',
		'user_agent' => "Keboola Connection Extractor",

		'consumer_key' => NULL,
		'shared_secret' => NULL,

		'rsa_private_key' => NULL,
		'rsa_public_key' => NULL,

		'core_version'=> '2.0',
		'payroll_version'=> '1.0',
		'file_version' => '1.0',
	);

	private $mandatoryConfigColumns = array(
		'consumer_key', 
		'#consumer_secret', 
		'#private_key',
		'public_key',
		'parameters',
		'endpoint',
	);

	private $destination;

	private $config;

	public function __construct($config, $destination)
	{
		date_default_timezone_set('UTC');
		$this->destination = $destination;

		foreach ($this->mandatoryConfigColumns as $c)
		{
			if (!isset($config[$c])) 
			{
				throw new Exception("Mandatory column '{$c}' not found or empty.");
			}

			$this->config[$c] = $config[$c];
		}

		if (!file_exists(dirname(__FILE__)."/certs/"))
		{
			mkdir(dirname(__FILE__)."/certs/");
		}

		if (!file_exists(dirname(__FILE__)."/certs/ca-bundle.crt"))
		{
			$cert = file_get_contents("http://curl.haxx.se/ca/cacert.pem");

			if (empty($cert)) 
			{
				throw new Exception("Cannot load SSL certificate for comms.");
			}

			file_put_contents(dirname(__FILE__)."/certs/ca-bundle.crt", $cert);
		}

		$this->prepareCertificates();

		$this->signatures['consumer_key'] = $this->config['consumer_key'];
		$this->signatures['access_token'] = $this->config['consumer_key'];
		$this->signatures['shared_secret'] = $this->config['#consumer_secret'];
		$this->signatures['access_token_secret'] = $this->config['#consumer_secret'];
		$this->signatures['rsa_private_key'] = dirname(__FILE__)."/certs/privatekey";
		$this->signatures['rsa_public_key'] = dirname(__FILE__)."/certs/publickey";

		$this->xero = new XeroOAuth($this->signatures);

		foreach (array('date', 'fromDate', 'toDate') as $date)
		{
			if (!empty($this->config['parameters'][$date]))
			{
				$timestamp = strtotime($this->config['parameters'][$date]);
				$dateTime = new DateTime();
				$dateTime->setTimestamp($timestamp);

				$this->config['parameters'][$date] = $dateTime->format('Y-m-d');
			}
		}
	}

	public function run()
	{
		$endpoints = $this->config['endpoint'];

		if (is_string($this->config['endpoint']))
		{
			$endpoints = array($this->config['endpoint']);
		}

		foreach ($endpoints as $endpoint)
		{
			$url = $this->xero->url($endpoint);

			$response = $this->xero->request('GET', $url, $this->config['parameters'], '', 'json');

			if (empty($response['code']) || $response['code'] != '200')
			{
				if (empty($response['code']))
				{
					$response['code'] = 'N/A';
					$response['response'] = 'N/A';
				}
				trigger_error("Request to the API failed: ".$response['code'].": ".$response['response'], E_USER_ERROR);

			}

			$this->write($response['response'], $endpoint);
		}
	}

	private function write($result, $endpoint)
	{
		$json = json_decode($result);

		$log = new \Monolog\Logger('json-parser');
		$log->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'));
		$parser = Parser::create($log);
		$parser->process(array($json), str_replace('/', '_', $endpoint));
		$result = $parser->getCsvFiles();

		foreach ($result as $file)
		{
			copy($file->getPathName(), $this->destination.substr($file->getFileName(), strpos($file->getFileName(), '-')+1));
		}
	}

	private function prepareCertificates()
	{
		foreach (array('#private_key' => 'privatekey', 'public_key' => 'publickey') as $configName => $fileName)
		{
			$cert = str_replace("\\n", "\n", $this->config[$configName]);
			file_put_contents(dirname(__FILE__)."/certs/".$fileName, $cert);
		}	
	}
}