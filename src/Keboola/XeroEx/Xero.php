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
		'bucket',
		'consumer_key', 
		'#consumer_secret', 
		'private_key',
		'public_key',
		'parameters',
		'report_name',
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

		$this->signatures['consumer_key'] = $this->config['consumer_key'];
		$this->signatures['access_token'] = $this->config['consumer_key'];
		$this->signatures['shared_secret'] = $this->config['#consumer_secret'];
		$this->signatures['access_token_secret'] = $this->config['#consumer_secret'];
		$this->signatures['rsa_private_key'] = "/data/in/files/".$this->config['private_key'];
		$this->signatures['rsa_public_key'] = "/data/in/files/".$this->config['public_key'];

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
		$url = $this->xero->url('Reports/'.$this->config['report_name']);

		$response = $this->xero->request('GET', $url, $this->config['parameters'], '', 'json');

		$this->write($response['response']);
	}

	private function write($result)
	{
		print_r($result);
		$json = json_decode($result);
		print_r($json);

		$parser = Parser::create(new \Monolog\Logger('json-parser'));
		$parser->process($json->Reports, $this->config['report_name']);
		$result = $parser->getCsvFiles();

		foreach ($result as $file)
		{
			copy($file->getPathName(), $this->destination.$this->config['bucket'].'.'.substr($file->getFileName(), strpos($file->getFileName(), '-')+1));
		}
	}
}