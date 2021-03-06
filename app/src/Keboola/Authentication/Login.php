<?php

namespace App\Keboola\Authentication;

use Keboola\Configuration\UserFunction;
use Keboola\Exception\UserException;
use Keboola\GenericExtractor\Subscriber\LoginSubscriber;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Utils\Exception\NoDataFoundException;

/**
 * config:
 *
 * loginRequest:
 *    endpoint: string
 *    params: array (optional)
 *    method: GET|POST|FORM (optional)
 *    headers: array (optional)
 * apiRequest:
 *    headers: array # [$headerName => $responsePath]
 *    query: array # same as with headers
 * expires: int|array # # of seconds OR ['response' => 'path', 'relative' => false] (optional)
 *
 * The response MUST be a JSON object containing credentials
 */
class Login implements AuthInterface
{
    /**
     * @var array
     */
    protected $configAttributes;

    /**
     * @var array
     */
    protected $auth;

    /**
     * @var string
     */
    protected $format;

    /**
     * Login constructor.
     *
     * @param array $configAttributes
     * @param array $authentication
     *
     * @throws UserException
     */
    public function __construct(array $configAttributes, array $authentication)
    {
        $this->configAttributes = $configAttributes;
        $this->auth = $authentication;
        if (empty($authentication['format'])) {
            $this->format = 'json';
        } else {
            if (in_array($authentication['format'], ['json', 'text'])) {
                $this->format = $authentication['format'];
            } else {
                throw new UserException("'format' must be either 'json' or 'text'.");
            }
        }
        if (empty($authentication['loginRequest'])) {
            throw new UserException("'loginRequest' is not configured for Login authentication");
        }
        if (empty($authentication['loginRequest']['endpoint'])) {
            throw new UserException('Request endpoint must be set for the Login authentication method.');
        }
        if (!empty($authentication['expires']) && (!filter_var($authentication['expires'], FILTER_VALIDATE_INT)
                && empty($authentication['expires']['response']))
        ) {
            throw new UserException(
                "The 'expires' attribute must be either an integer or an array with 'response' " .
                "key containing a path in the response"
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function authenticateClient(RestClient $client)
    {
        $loginRequest = $this->getAuthRequest($this->auth['loginRequest']);
        $sub = new LoginSubscriber();

        $sub->setLoginMethod(
            function () use ($client, $loginRequest, $sub) {
                // Need to bypass the subscriber for the login call
                $client->getClient()->getEmitter()->detach($sub);
                $rawResponse = $client->getClient()->send($client->getGuzzleRequest($loginRequest));
                if ($this->format == 'json') {
                    $response = $client->getObjectFromResponse($rawResponse);
                    if (is_scalar($response)) {
                        $response = (object)['data' => $response];
                    }
                } else {
                    $response = (object)['data' => (string)$rawResponse->getBody()];
                }
                $client->getClient()->getEmitter()->attach($sub);

                return [
                    'query'   => $this->getResults($response, 'query'),
                    'headers' => $this->getResults($response, 'headers'),
                    'expires' => $this->getExpiry($response)
                ];
            }
        );

        $client->getClient()->getEmitter()->attach($sub);
    }

    /**
     * @param array $config
     *
     * @return RestRequest
     * @throws UserException
     */
    protected function getAuthRequest(array $config): RestRequest
    {
        if (!empty($config['params'])) {
            $config['params'] = UserFunction::build($config['params'], ['attr' => $this->configAttributes]);
        }
        if (!empty($config['headers'])) {
            $config['headers'] = UserFunction::build($config['headers'], ['attr' => $this->configAttributes]);
        }

        return new RestRequest($config);
    }

    /**
     * Maps data from login result into $type (header/query)
     *
     * @param \stdClass $response
     * @param string    $type
     *
     * @return array
     * @throws UserException
     */
    protected function getResults(\stdClass $response, $type): array
    {
        $result = [];
        if (!empty($this->auth['apiRequest'][ $type ])) {
            $result = UserFunction::build(
                $this->auth['apiRequest'][ $type ],
                [
                    'response' => \Keboola\Utils\objectToArray($response),
                    'attr'     => $this->configAttributes
                ]
            );
            // for backward compatibility, check the values if they are a valid path within the response
            foreach ($result as $key => $value) {
                try {
                    $result[ $key ] = \Keboola\Utils\getDataFromPath($value, $response, '.', false);
                } catch (NoDataFoundException $e) {
                    // silently ignore invalid paths as they are probably values already processed by functions
                }
            }
        }

        return $result;
    }

    /**
     * @param \stdClass $response
     *
     * @return int|null
     * @throws UserException
     */
    protected function getExpiry(\stdClass $response): ?int
    {
        if (!isset($this->auth['expires'])) {
            return null;
        } elseif (is_numeric($this->auth['expires'])) {
            return time() + (int)$this->auth['expires'];
        } elseif (is_array($this->auth['expires'])) {
            $rExpiry = \Keboola\Utils\getDataFromPath($this->auth['expires']['response'], $response, '.');
            $expiry = is_int($rExpiry) ? $rExpiry : strtotime($rExpiry);

            if (!empty($this->auth['expires']['relative'])) {
                $expiry += time();
            }

            if ($expiry < time()) {
                throw new UserException("Login authentication returned expiry time before current time: '{$rExpiry}'");
            }

            return $expiry;
        }

        return null;
    }
}
