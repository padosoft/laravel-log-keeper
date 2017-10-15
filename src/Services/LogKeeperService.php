<?php namespace MathiasGrimm\LaravelLogKeeper\Services;

use Exception;
use MathiasGrimm\LaravelLogKeeper\Repos\LocalLogsRepoInterface;
use MathiasGrimm\LaravelLogKeeper\Repos\LogsRepoInterface;
use Carbon\Carbon;
use MathiasGrimm\LaravelLogKeeper\Support\LogUtil;
use Psr\Log\LoggerInterface;

class LogKeeperService
{
    private $config;
    private $localRepo;
    private $remoteRepo;
    private $localRetentionDays;
    private $localRetentionDaysForCompressed;
    private $remoteRetentionDays;
    private $remoteRetentionDaysCalculated;
    private $logger;

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
     * @var Carbon
     */
    private $today;

    public function __construct($config, LogsRepoInterface $localRepo, LogsRepoInterface $remoteRepo, LoggerInterface $logger)
    {
        $this->config                           = $config;
        $this->localRepo                        = $localRepo;
        $this->remoteRepo                       = $remoteRepo;
        $this->today                            = Carbon::today();
        $this->localRetentionDays               = $this->config['localRetentionDays'];
        $this->localRetentionDaysForCompressed  = $this->config['localRetentionDaysForCompressed'];
        $this->remoteRetentionDays              = $this->config['remoteRetentionDays'];
        $this->remoteRetentionDaysCalculated    = $this->config['remoteRetentionDaysCalculated'];
        $this->logger                           = $logger;
    }

    public function work()
    {
        if (!$this->config['enabled']) {
            $this->logger->warning("Log Keeper can't work because it is disabled");
            return;
        }

        $this->logger->info("Starting Laravel Log Keeper");
        $this->logger->info("Local Retention: {$this->localRetentionDays} days");
        $this->logger->info("Local Retention for compressed: {$this->localRetentionDaysForCompressed} days");
        $this->logger->info("Remote Retention: {$this->remoteRetentionDays} days");
        $this->logger->info("Calculated Retention: {$this->remoteRetentionDaysCalculated} days");

        $this->localWork();

        if ($this->config['enabled_remote']) {
            $this->remoteCleanUp();
        } else {
            $this->logger->warning("Laravel Log Keeper is not enabled for remote operations");
        }
    }

    private function localWork()
    {
        $logs = $this->localRepo->getLogs();

        foreach ($logs as $log) {

            $this->logger->info("Analysing {$log}");

            $days = LogUtil::diffInDays($log, $this->today);

            $this->logger->info("{$log} is {$days} day(s) old");

            if (($days > $this->localRetentionDays) && ($days <= $this->remoteRetentionDaysCalculated)) {
                $compressedName = "{$log}.tar.bz2";

                $this->logger->info("Compressing {$log} into {$compressedName}");

                $this->localRepo->compress($log, $compressedName);

                $this->logger->info("Deleting $log locally");
                $this->localRepo->delete($log);

                if ($this->config['enabled_remote']) {

                    $this->logger->info("Uploading {$compressedName}");
                    $content = $this->localRepo->get($compressedName);
                    $this->remoteRepo->put($compressedName, $content);

                } else {
                    $this->logger->info("Deleting $log locally");
                    $this->localRepo->delete($log);

                    $this->logger->info("Not uploading {$compressedName} because enabled_remote is false");
                }

                $this->logger->info("Deleting $compressedName locally");
                $this->localRepo->delete($compressedName);

            } elseif (($days > $this->localRetentionDays) ) {
                $this->logger->info("Deleting {$log} because it is to old to be kept either local or remotely");

                // file too old to be stored either remotely or locally
                $this->localRepo->delete($log);
            } else {
                $this->logger->info("Keeping {$log}");
            }
        }

        $compressedlogs = $this->localRepo->getCompressed();

        foreach ($compressedlogs as $compressedlog) {

            $this->logger->info("Analysing {$compressedlog}");

            if (($days > $this->localRetentionDaysForCompressed)) {
                $this->logger->info("Deleting $compressedlog locally");
                $this->localRepo->delete($compressedlog);
            }
        }
    }

    private function remoteCleanUp()
    {
        $this->logger->info("Starting remote clean up");

        $logs = $this->remoteRepo->getCompressed();

        foreach ($logs as $log) {
            $days = LogUtil::diffInDays($log, $this->today);

            $this->logger->info("{$log} is {$days} day(s) old");

            if ($days > $this->remoteRetentionDaysCalculated) {
                $this->logger->info("Deleting {$log}");
                $this->remoteRepo->delete($log);
            } else {
                $this->logger->info("Keeping {$log}");
            }
        }
    }
}
