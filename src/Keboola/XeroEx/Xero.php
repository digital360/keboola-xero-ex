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

	private $debug = false;

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

		if (!empty($config["debug"]))
		{
			$this->debug = true;
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
			$parameters = $this->config['parameters'];

			if (is_array($endpoint))
			{
				$parameters = array_merge($parameters, array_values($endpoint)[0][0]);
				$endpoint = array_keys($endpoint)[0];
			}

			if (in_array($endpoint, array('BankTransactions','Contacts','Invoices','Overpayments','Prepayments','PurchaseOrders')))
			{
				$parameters['page'] = 1;
			}

			$response = $this->makeRequest($endpoint, $parameters);

			// Page pagination
			if (in_array($endpoint, array('BankTransactions','Contacts','Invoices','Overpayments','Prepayments','PurchaseOrders')))
			{
				$records = $response->$endpoint;
				$page = 2;
				
				while (count($response->$endpoint) > 0 && $page <= 1000)
				{
					if ($this->debug)
					{
						echo "\n";
						print_r(count($response->$endpoint));
					}

					$currentParameters = $parameters;
					$currentParameters['page'] = $page;
					$response = $this->makeRequest($endpoint, $currentParameters);

					$page += 1;
					$records = array_merge($records, $response->$endpoint);
				}

				$response = $records;
			}

			// Offset paignation
			if (in_array($endpoint, array('Journals')))
			{
				$offsetNames = array(
					'Journals' => 'JournalNumber'
				);
				$records = $response->$endpoint;

				$counter = 1;
				
				while (count($response->$endpoint) > 0 && $counter <= 20)
				{
					if ($this->debug)
					{
						print_r(count($response->$endpoint));
					}

					$currentParameters = $parameters;
					$currentParameters['offset'] = $records[count($records)-1]->$offsetNames[$endpoint];
					$response = $this->makeRequest($endpoint, $currentParameters);

					$counter += 1;
					$records = array_merge($records, $response->$endpoint);
				}

				$response = $records;
			}			

			$this->write($response, $endpoint);
		}
	}

	private function makeRequest($endpoint, $parameters)
	{
		$url = $this->xero->url($endpoint);

		$response = $this->xero->request('GET', $url, $parameters, '', 'json');

		
		if ($this->debug)
		{
			echo "\n";
			print_r($endpoint);
			echo "\n";
			print_r($parameters);
		}

		if (empty($response['code']) || $response['code'] != '200')
		{
			if (empty($response['code']))
			{
				$response['code'] = 'N/A';
				$response['response'] = 'N/A';
			}
			fwrite(STDERR, "Request to the API failed: ".$response['code'].": ".$response['response']);
			exit(1);
		}

		return json_decode($response['response']);
	}

	private function write($json, $endpoint)
	{
		$log = new \Monolog\Logger('json-parser');
		$log->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'));
		$parser = Parser::create($log);

		if (empty($json))
		{
			echo "ERRROR: Endpoint ".$endpoint." returned empty response.\n";
			return;
		}
		
		if (!is_array($json))
		{
			$json = array($json);
		}

		$parser->process($json, str_replace('/', '_', $endpoint));
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