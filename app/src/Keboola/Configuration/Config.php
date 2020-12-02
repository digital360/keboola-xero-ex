<?php

namespace App\Keboola\Configuration;

use App\Keboola\Exception\UserException;

/**
 * Carries the extractor configuration
 */
class Config
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var JobConfig[]
     */
    private $jobs = [];

    /**
     * Config constructor.
     * @param array $configuration
     * @throws UserException
     */
    public function __construct(array $configuration)
    {
        if (empty($configuration['jobs']) || !is_array($configuration['jobs'])) {
            throw new UserException("The 'jobs' section is required in the configuration.");
        }

        $jobConfigs = [];
        foreach ($configuration['jobs'] as $job) {
            if (!is_array($job)) {
                throw new UserException("Job configuration must be an array: " . var_export($job, true));
            }
            $jobConfig = new JobConfig($job);
            $jobConfigs[$jobConfig->getJobId()] = $jobConfig;
        }
        $this->jobs = $jobConfigs;
        unset($configuration['jobs']);
        $this->attributes = $configuration;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     * @return bool|mixed
     */
    public function getAttribute($name)
    {
        return empty($this->attributes[$name]) ? false : $this->attributes[$name];
    }

    /**
     * @return JobConfig[]
     */
    public function getJobs()
    {
        return $this->jobs;
    }
}
