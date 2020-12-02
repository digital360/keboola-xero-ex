<?php

namespace App\Keboola\XeroEx;

use App\Keboola\Authentication\OAuth20Login;
use App\Keboola\Authentication\AuthInterface;
use App\Keboola\Configuration\Api;
use App\Keboola\Configuration\Extractor;
use App\Keboola\Configuration\UserFunction;
use Keboola\Json\Parser;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use function Couchbase\defaultDecoder;

class Xero
{
    private $destination;

    private $config;

    private bool $debug = false;

    private array $sentRequests = [];

    private int $requestsPerMinuteLimit = 55;

    /**
     * @var \App\Keboola\Configuration\Api
     */
    private $api;

    /**
     * @var AuthInterface
     */
    private $auth;

    public function __construct($authorization, $configAttributes, $destination)
    {
        date_default_timezone_set('UTC');
        $this->destination = $destination;

        $this->logger = new Logger('logger');
        $this->api = new Api($this->logger, $configAttributes['api'], $configAttributes, $authorization);

        $this->auth();


        if (!empty($config["debug"])) {
            $this->debug = true;
        }

        foreach (['date', 'fromDate', 'toDate'] as $date) {
            if (!empty($this->config['parameters'][$date])) {
                $timestamp = strtotime($this->config['parameters'][$date]);
                $dateTime = new DateTime();
                $dateTime->setTimestamp($timestamp);

                $this->config['parameters'][$date] = $dateTime->format('Y-m-d');
            }
        }
    }

    public function auth()
    {
        $a = UserFunction::build(
            $this->api->getHeaders()->getHeaders(),
            ['attr' => $this->api->getConfig()->getAttributes()]
        );

        dd($a);
        dd($this->api->getBaseUrl());
        dd($auth);

        $client = new GuzzleHttp\Client();
        $res = $client->request('POST', $this->api->getBaseUrl(), [
            'auth' => ['user', 'pass']
        ]);
        echo $res->getStatusCode();
// "200"
        echo $res->getHeader('content-type')[0];
// 'application/json; charset=utf8'
        echo $res->getBody();

        $client = new RestClient(
            $this->logger,
            [
                'base_url' => $this->api->getBaseUrl(),
                'defaults' => [
                    'headers' => UserFunction::build(
                        $this->api->getHeaders()->getHeaders(),
                        ['attr' => $config->getAttributes()]
                    ),
                    'proxy'   => $this->proxy,
                ]
            ],
            JuicerRest::convertRetry($this->api->getRetryConfig()),
            $this->api->getDefaultRequestOptions(),
            $this->api->getIgnoreErrors()
        );
    }

    public function run()
    {
        $endpoints = $this->config['endpoint'];

        if (is_string($this->config['endpoint'])) {
            $endpoints = [$this->config['endpoint']];
        }

        foreach ($endpoints as $endpoint) {
            $parameters = $this->config['parameters'];

            if (is_array($endpoint)) {
                $parameters = array_merge($parameters, array_values($endpoint)[0][0]);
                $endpoint = array_keys($endpoint)[0];
            }

            if (in_array($endpoint, [
                'BankTransactions', 'Contacts', 'Invoices', 'Overpayments', 'Prepayments', 'PurchaseOrders',
                'CreditNotes', 'ManualJournals'
            ])) {
                $parameters['page'] = 1;
            }

            $response = $this->makeRequest($endpoint, $parameters);

            // Page pagination
            if (in_array($endpoint, [
                'BankTransactions', 'Contacts', 'Invoices', 'Overpayments', 'Prepayments', 'PurchaseOrders',
                'CreditNotes', 'ManualJournals'
            ])) {
                $records = $response->$endpoint;
                $page = 2;

                while (count($response->$endpoint) > 0 && $page <= 1000) {
                    if ($this->debug) {
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
            if (in_array($endpoint, ['Journals'])) {
                $offsetNames = [
                    'Journals' => 'JournalNumber'
                ];
                $records = $response->$endpoint;

                $counter = 1;

                while (count($response->$endpoint) > 0 && $counter <= 10000) {
                    if ($this->debug) {
                        print_r(count($response->$endpoint));
                    }

                    $currentParameters = $parameters;
                    $currentParameters['offset'] = $records[count($records) - 1]->$offsetNames[$endpoint];
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
        while ($this->getRequestsInLastMinute() >= $this->requestsPerMinuteLimit) {
            sleep(1);
        }

        $this->sentRequests[] = time();

        $url = $this->xero->url($endpoint);

        $response = $this->xero->request('GET', $url, $parameters, '', 'json');


        if ($this->debug) {
            echo "\nEndpoint: ";
            print_r($endpoint);
            echo "\nParameters: ";
            print_r($parameters);
            echo "\nResponse: ";
            print_r($response);
            echo "\n";
            echo "Requests in last minute: ".$this->getRequestsInLastMinute();
            echo "\n";
        }

        if (empty($response['code']) || $response['code'] != '200') {
            if (empty($response['code'])) {
                $response['code'] = 'N/A';
                $response['response'] = 'N/A';
            }
            fwrite(STDERR, "Request to the API failed: ".$response['code'].": ".$response['response']);
            exit(1);
        }

        return json_decode($response['response']);
    }

    private function getRequestsInLastMinute()
    {
        $this->sentRequests;

        $lastRequests = 0;
        $minuteAgo = time() - 60;

        foreach ($this->sentRequests as $req) {
            if ($req >= $minuteAgo) {
                $lastRequests += 1;
            }
        }

        return $lastRequests;
    }

    private function write($json, $endpoint)
    {
        $log = new Logger('json-parser');
        $log->pushHandler(new StreamHandler('php://stdout'));
        $parser = Parser::create($log);

        if (empty($json)) {
            echo "ERRROR: Endpoint ".$endpoint." returned empty response.\n";

            return;
        }

        if (!is_array($json)) {
            $json = [$json];
        }

        $parser->process($json, str_replace('/', '_', $endpoint));
        $result = $parser->getCsvFiles();

        foreach ($result as $file) {
            copy($file->getPathName(),
                $this->destination.substr($file->getFileName(), strpos($file->getFileName(), '-') + 1));
        }
    }

    private function prepareCertificates()
    {
        foreach (['#private_key' => 'privatekey', 'public_key' => 'publickey'] as $configName => $fileName) {
            $cert = str_replace("\\n", "\n", $this->config[$configName]);
            file_put_contents(dirname(__FILE__)."/certs/".$fileName, $cert);
        }
    }
}