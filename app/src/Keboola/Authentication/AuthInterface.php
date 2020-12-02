<?php

namespace App\Keboola\Authentication;

use Keboola\Juicer\Client\RestClient;

interface AuthInterface
{
    public function authenticateClient(RestClient $client);
}
