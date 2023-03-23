<?php namespace MathiasGrimm\LaravelLogKeeper\Repos;

use Exception;
use \Storage;
use MathiasGrimm\LaravelLogKeeper\Support\LogUtil;

class RemoteLogsRepo implements LogsRepoInterface
{
    private $config;
    private $localLogPath;
    private $disk;
    private $remotePath;

    public function __construct(array $config)
    {
        $this->config = $config;

        if ($this->config['enabled_remote'] && !$this->config['remote_disk']) {
            throw new Exception("remote_disk not configured for Laravel Log Keeper");
        }

        $this->localLogPath = storage_path('logs');
        $this->disk         = Storage::disk($this->config['remote_disk']);
        $this->remotePath   = $this->config['remote_path'] ? $this->config['remote_path'] . '/' : null;
    }

    public function getLogs()
    {
        $allLogs    = $this->disk->files($this->remotePath);
        $logs       = LogUtil::getLogs($allLogs);
        $logs       = LogUtil::mapBasename($logs);

        return $logs;
    }

    public function delete($log)
    {
        $path = "{$this->remotePath}{$log}";

        $this->disk->delete($path);
    }

    public function put($log, $content)
    {
        $path = "{$this->remotePath}{$log}";

        $this->disk->put($path, $content);
    }

    public function getCompressed()
    {
        $allLogs    = $this->disk->files($this->remotePath);
        $logs       = LogUtil::getCompressed($allLogs);
        $logs       = LogUtil::mapBasename($logs);

        return $logs;
    }

    public function compress($log, $compressedName)
    {
        throw new Exception("Method not implemented yet");
        // TODO: Implement compress() method.
    }

    public function get($log)
    {
        return $this->disk->get("{$this->remotePath}/{$log}");
    }

    public function exists($log)
    {
        $path = "{$this->remotePath}/{$log}";

        return $this->disk->exists($path);
    }
}
