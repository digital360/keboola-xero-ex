<?php

namespace App\Keboola\Configuration;

use App\Keboola\Authentication\OAuth20Login;
use App\Keboola\Authentication;
use App\Keboola\Authentication\AuthInterface;
use App\Keboola\Exception\UserException;
use App\Keboola\Configuration\Config;
use Keboola\Juicer\Pagination\ScrollerFactory;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Utils\Exception\JsonDecodeException;
use Psr\Log\LoggerInterface;

use function Keboola\Utils\jsonDecode;

/**
 * API Description
 */
class Api
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $name = 'generic';

    /**
     * @var AuthInterface
     */
    private $auth;

    /**
     * @var array
     */
    private $scrollerConfig = [];

    /**
     * @var Headers
     */
    private $headers;

    /**
     * @var array
     */
    private $defaultRequestOptions = [];

    /**
     * @var array
     */
    private $retryConfig = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $ignoreErrors = [];

    /**
     * Api constructor.
     *
     * @param  LoggerInterface  $logger
     * @param  array  $api
     * @param  array  $configAttributes
     * @param  array  $authorization
     */
    public function __construct(LoggerInterface $logger, array $api, array $configAttributes, array $authorization)
    {
        $this->logger = $logger;
        $this->auth = $this->createAuth($api, $configAttributes, $authorization);
        $this->headers = new Headers($api, $configAttributes);
        $this->config = new Config($configAttributes);

        if (!empty($api['pagination']) && is_array($api['pagination'])) {
            $this->scrollerConfig = $api['pagination'];
        }
        if (!empty($api['retryConfig']) && is_array($api['retryConfig'])) {
            $this->retryConfig = $api['retryConfig'];
        }
        if (!empty($api['http']['ignoreErrors']) && is_array($api['http']['ignoreErrors'])) {
            $this->ignoreErrors = $api['http']['ignoreErrors'];
        }
        $this->baseUrl = $this->createBaseUrl($api, $configAttributes);
        if (!empty($api['name'])) {
            $this->name = $api['name'];
        }
        if (!empty($api['http']['defaultOptions'])) {
            $this->defaultRequestOptions = $api['http']['defaultOptions'];
        }
    }

    /**
     * Create Authentication class that accepts a Guzzle client.
     *
     * @param  array  $api
     * @param  array  $configAttributes
     * @param  array  $authorization
     *
     * @return AuthInterface
     * @throws UserException
     */
    private function createAuth(array $api, array $configAttributes, array $authorization): AuthInterface
    {
        if (empty($api['authentication']['type'])) {
            $this->logger->debug("Using no authentication.");

            return new Authentication\NoAuth();
        }
        $this->logger->debug("Using '{$api['authentication']['type']}' authentication.");
        switch ($api['authentication']['type']) {
            case 'login':
                return new Authentication\Login($configAttributes, $api['authentication']);
            case 'oauth20.login':
                return new OAuth20Login($configAttributes, $authorization, $api['authentication']);
            default:
                throw new UserException("Unknown authorization type '{$api['authentication']['type']}'.");
        }
    }

    /**
     * @param  array  $api
     * @param  array  $configAttributes
     *
     * @return string
     * @throws UserException
     */
    private function createBaseUrl(array $api, array $configAttributes): string
    {
        if (empty($api['baseUrl'])) {
            throw new UserException("The 'baseUrl' attribute must be set in API configuration");
        }

        if (filter_var($api['baseUrl'], FILTER_VALIDATE_URL)) {
            return $api['baseUrl'];
        }

        if (is_string($api['baseUrl'])) {
            // For backwards compatibility
            try {
                $fn = jsonDecode($api['baseUrl']);
                $this->logger->warning("Passing json-encoded baseUrl is deprecated.");
            } catch (JsonDecodeException $e) {
                throw new UserException("The 'baseUrl' attribute in API configuration is not a valid URL");
            }
            $baseUrl = UserFunction::build([$fn], ['attr' => $configAttributes])[0];
        } else {
            $baseUrl = UserFunction::build([$api['baseUrl']], ['attr' => $configAttributes])[0];
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new UserException(sprintf(
                'The "baseUrl" attribute in API configuration resulted in an invalid URL (%s)',
                $baseUrl
            ));
        }

        return $baseUrl;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return ScrollerInterface
     */
    public function getNewScroller(): ScrollerInterface
    {
        return ScrollerFactory::getScroller($this->scrollerConfig);
    }

    /**
     * @return AuthInterface
     */
    public function getAuth(): AuthInterface
    {
        return $this->auth;
    }

    /**
     * @return Headers
     */
    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return array
     */
    public function getDefaultRequestOptions(): array
    {
        return $this->defaultRequestOptions;
    }

    /**
     * @return array
     */
    public function getRetryConfig(): array
    {
        return $this->retryConfig;
    }

    public function getIgnoreErrors()
    {
        return $this->ignoreErrors;
    }
}
