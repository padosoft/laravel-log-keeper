<?php namespace MathiasGrimm\LaravelLogKeeper\Repos;

use Exception;

class FakeRemoteLogsRepo extends FakeLogsRepo
{
    public function __construct(array $config)
    {
        parent::__construct($config);

        if ($this->config['enabled_remote'] && !$this->config['remote_disk']) {
            throw new Exception("remote_disk not configured for Laravel Log Keeper");
        }
    }

    public function exists($log)
    {
        return false;
    }
}