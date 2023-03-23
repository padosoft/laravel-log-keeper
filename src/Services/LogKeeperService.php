<?php namespace MathiasGrimm\LaravelLogKeeper\Services;

use Exception;
use MathiasGrimm\LaravelLogKeeper\Repos\LogsRepoInterface;
use Carbon\Carbon;
use MathiasGrimm\LaravelLogKeeper\Support\LogUtil;
use Psr\Log\LoggerInterface;

class LogKeeperService
{
    private $config;
    private $localRepo;
    private $remoteRepo;
    private $processFilesWithZeroDaysRetention;
    private $localRetentionDays;
    private $localRetentionDaysForCompressed;
    private $remoteRetentionDays;
    private $remoteRetentionDaysCalculated;
    private $logger;
    private $command;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return LogKeeperService
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param $cmd
     */
    public function setCommand($cmd)
    {
        $this->command = $cmd;
    }

    /**
     * @var Carbon
     */
    private $today;

    public function __construct($config, LogsRepoInterface $localRepo, LogsRepoInterface $remoteRepo, LoggerInterface $logger)
    {
        $this->config                           = $config;
        $this->localRepo                        = $localRepo;
        $this->remoteRepo                       = $remoteRepo;
        $this->today                            = Carbon::today();
        $this->processFilesWithZeroDaysRetention= $this->config['processFilesWithZeroDaysRetention'];
        $this->localRetentionDays               = $this->config['localRetentionDays'];
        $this->localRetentionDaysForCompressed  = $this->config['localRetentionDaysForCompressed'];
        $this->remoteRetentionDays              = $this->config['remoteRetentionDays'];
        $this->remoteRetentionDaysCalculated    = $this->config['remoteRetentionDaysCalculated'];
        $this->logger                           = $logger;
    }

    public function log($msg)
    {
        if($this->command!==null) {
            $this->command->line($msg);
        }
        $this->logger->info($msg);
    }

    public function logWarning($msg)
    {
        if($this->command!==null) {
            $this->command->question($msg);
        }
        $this->logger->warning($msg);
    }

    public function logComment($msg)
    {
        if($this->command!==null){
            $this->command->comment($msg);
        }
        $this->logger->info($msg);
    }

    public function logSuccess($msg)
    {
        if($this->command!==null) {
            $this->command->info($msg);
        }
        $this->logger->info($msg);
    }

    public function logError($msg)
    {
        if($this->command!==null) {
            $this->command->error($msg);
        }
        $this->logger->error($msg);
    }

    public function logRed($msg)
    {
        if($this->command!==null) {
            $this->command->error($msg);
        }
        $this->logger->info($msg);
    }

    public function work()
    {
        if (!$this->config['enabled']) {
            $this->logWarning("Log Keeper can't work because it is disabled");
            return;
        }

        $this->logComment("Starting Laravel Log Keeper");
        $this->log("Process files with zero retention: ".($this->processFilesWithZeroDaysRetention ? 'true': 'false'));
        $this->log("Local Retention: {$this->localRetentionDays} days");
        $this->log("Local Retention for compressed: {$this->localRetentionDaysForCompressed} days");
        $this->log("Remote Retention: {$this->remoteRetentionDays} days");
        $this->log("Calculated Retention: {$this->remoteRetentionDaysCalculated} days");
        $this->log("Enabled remote: ".($this->config['enabled_remote'] ? 'true': 'false'));

        $this->localWork();

        $this->localCompressedCleanUp();

        $this->remoteCleanUp();

        $this->logSuccess("Finish successfull.");
    }

    private function localWork()
    {
        $logs = $this->localRepo->getLogs();

        foreach ($logs as $log) {

            $this->logComment("Analysing {$log}");

            $days = LogUtil::diffInDays($log, $this->today);

            $this->log("{$log} is {$days} day(s) old");

            if(!$this->processFilesWithZeroDaysRetention && $days<1){
                $this->log("{$log} not processing because it is 0 day old and processFilesWithZeroDaysRetention is false");
                continue;
            }

            if (($days > $this->localRetentionDaysForCompressed) && (!$this->config['enabled_remote'] || $days > $this->remoteRetentionDaysCalculated)) {
                $this->logRed("Deleting {$log} because it is to old to be kept either local or remotely");
                $this->localRepo->delete($log);
                continue;
            }

            $compressedName = $this->compress($log);

            $this->uploadToRemote($log, $compressedName);

            if ($days > $this->localRetentionDaysForCompressed) {
                $this->logRed("Deleting {$compressedName} because it is to old to be kept local");
                $this->localRepo->delete($compressedName);
            }

            if ($days > $this->localRetentionDays) {
                $this->logRed("Deleting $log locally");
                $this->localRepo->delete($log);
                continue;
            }

            $this->log("Keeping {$log} because it is to recent to be deleted locally.");
        }
    }

    private function remoteCleanUp()
    {
        if (!$this->config['enabled_remote']) {
            return;
        }

        $this->logComment("Starting remote clean up");

        $logs = $this->remoteRepo->getCompressed();

        foreach ($logs as $log) {
            $this->logComment("Analysing {$log}");

            $days = LogUtil::diffInDays($log, $this->today);

            $this->log("{$log} is {$days} day(s) old");

            if ($days > $this->remoteRetentionDaysCalculated) {
                $this->logRed("Deleting {$log} in remote");
                $this->remoteRepo->delete($log);
            } else {
                $this->log("Keeping {$log}");
            }
        }
    }

    /**
     *
     */
    private function localCompressedCleanUp()
    {
        $this->logComment("Starting local compressed clean up");

        $logs = $this->localRepo->getCompressed();

        foreach ($logs as $log) {

            $this->logComment("Analysing {$log}");

            $days = LogUtil::diffInDays($log, $this->today);
            $this->log("{$log} is {$days} day(s) old");

            if ($days <= $this->localRetentionDaysForCompressed) {
                $this->log("keeping $log locally");
                continue;
            }

            $this->logRed("Deleting $log locally");
            $this->localRepo->delete($log);
        }
    }

    /**
     * @param $log
     * @return string
     */
    private function compress($log): string
    {
        $compressedName = "{$log}.tar.bz2";
        if (windows_os())
            $compressedName = "{$log}.gz";

        if ($this->localRepo->exists($compressedName)) {
            $this->log("Compressed file {$compressedName} already exists");
        } else {
            $this->log("Compressing {$log} into {$compressedName}");
            $this->localRepo->compress($log, $compressedName);
        }
        return $compressedName;
    }

    /**
     * @param $log
     * @param $compressedName
     */
    private function uploadToRemote($log, $compressedName)
    {
        $days = LogUtil::diffInDays($log, $this->today);

        if (($days > $this->remoteRetentionDaysCalculated) || !$this->config['enabled_remote']) {
            $this->log("Not uploading {$compressedName} because enabled_remote is false or is too old to be kept remotely");
            return;
        }

        if ($this->remoteRepo->exists($compressedName)) {
            $this->log("Not uploading {$compressedName} because already exists remotely");
            return;
        }

        $this->log("Uploading {$compressedName}");
        $content = $this->localRepo->get($compressedName);
        $this->remoteRepo->put($compressedName, $content);
    }
}
