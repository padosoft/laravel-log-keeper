<?php namespace MathiasGrimm\LaravelLogKeeper\Repos;

use Exception;
use Illuminate\Filesystem\Filesystem;
use MathiasGrimm\LaravelLogKeeper\Support\LogUtil;

class LocalLogsRepo implements LogsRepoInterface
{
    private $config;
    private $localLogPath;
    private $disk;

    public function __construct(array $config)
    {
        $this->config       = $config;
        $this->localLogPath = storage_path('logs');
        $this->disk         = new Filesystem();
    }

    public function getLogs()
    {
        $allLogs = $this->disk->files($this->localLogPath);

        $logs = LogUtil::getLogs($allLogs);
        $logs = LogUtil::mapBasename($logs);

        return $logs;
    }

    public function getCompressed()
    {
        $allLogs = $this->disk->files($this->localLogPath);

        $logs = LogUtil::getCompressed($allLogs);
        $logs = LogUtil::mapBasename($logs);

        return $logs;
    }

    public function delete($log)
    {
        $path = "{$this->localLogPath}".DIRECTORY_SEPARATOR."{$log}";

        $this->disk->delete($path);
    }

    public function compress($log, $compressedName)
    {
        if (windows_os()){
            $result = gzCompressFile($this->localLogPath.DIRECTORY_SEPARATOR.$log);

            if ($result === false) {
                throw new Exception("Something went wrong when compressing {$log} under win system");
            }
        } else {
            $command = "cd {$this->localLogPath}; tar cjf {$compressedName} {$log}";
            exec($command, $output, $exit);

            if ($exit) {
                throw new Exception("Something went wrong when compressing {$log}");
            }
        }
    }

    public function get($log)
    {
        $path = "{$this->localLogPath}".DIRECTORY_SEPARATOR."{$log}";
        return $this->disk->get($path);
    }

    public function put($log, $content)
    {
        $this->disk->put($log, $content);
    }

    public function exists($log)
    {
        $path = "{$this->localLogPath}".DIRECTORY_SEPARATOR."{$log}";
        return $this->disk->exists($path);
    }
}
