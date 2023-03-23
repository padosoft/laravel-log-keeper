Laravel Log Keeper
======

[![Author](http://img.shields.io/badge/author-@matgrimm-blue.svg?style=flat-square)](https://twitter.com/matgrimm)
[![Latest Version](https://img.shields.io/github/release/mathiasgrimm/laravel-log-keeper.svg?style=flat-square)](https://github.com/mathiasgrimm/laravel-log-keeper/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/mathiasgrimm/laravel-log-keeper.svg?style=flat-square)](https://packagist.org/packages/mathiasgrimm/laravel-log-keeper)

Laravel Log Keeper helps rotating your logs while storing them anywhere you want with custom local/remote retention policies.

A typical usage of Laravel Log Keeper would be to set a cron job to run daily and store local logs in a S3 bucket, (s)ftp, Dropbox or any other FileSystem driver

You can define a local and/or a remote retention, which by default is 7 and 30 days subsequently.

- Local files older than 7 days will be compressed using bzip2 and uploaded to the remote disk
- Remote files older than 30 days will be permanently deleted from the remote disk

### Highlights

- Have full control of where/when/how you archive you logs
- Prevent your server from running out of space
- Custom retention policies for Local and Remote archiving

## Installation

Laravel Log Keeper is available via Composer:

```json
{
    "require": {
        "mathiasgrimm/laravel-log-keeper": "1.*"
    }
}
```

## Setup

### Laravel

#### Register Service Provider

```php
// config/app.php

'providers' => [
    ...
    MathiasGrimm\LaravelLogKeeper\Providers\LaravelServiceProvider::class,
    ...
],
```

#### Register the cron job
```php
// app/Console/Kernel.php

protected $commands = [
    ...
    \MathiasGrimm\LaravelLogKeeper\Commands\LogKeeper::class
    ...
];

...

protected function schedule(Schedule $schedule)
{
    ...
    $schedule->command('laravel-log-keeper')->daily();
    ...
}

```

### Log Format
To use Laravel Log Keeper your log files have to be in the daily format, which is defined in your `config/app.php`
```php
    ...
    'log' => 'daily',
    ...
```

### Timezone
To use Laravel Log Keeper pay attention to timezone laravel app setting, which is defined in your `config/app.php` or datetime check fails.
```php
    ...
    'timezone' => 'Europe/Rome',
    ...
```

### Environment
You can override the following variables placing them in your .env
(LARAVEL_LOG_KEEPER_REMOTE_DISK is mandatory)

Example:

```
# .env
...

LARAVEL_LOG_KEEPER_ENABLED=true
LARAVEL_LOG_KEEPER_ENABLED_REMOTE=false
LARAVEL_LOG_KEEPER_REMOTE_PATH=/
LARAVEL_LOG_KEEPER_PROCESS_FILES_WITH_ZERO_DAYS_RETENTION=false
LARAVEL_LOG_KEEPER_LOCAL_RETENTION_DAYS=7
LARAVEL_LOG_KEEPER_LOCAL_RETENTION_DAYS_FOR_COMPRESSED=15
LARAVEL_LOG_KEEPER_REMOTE_RETENTION_DAYS=30
LARAVEL_LOG_KEEPER_REMOTE_DISK=local
LARAVEL_LOG_KEEPER_LOG=true

...

```

```php
    // laravel-log-keeper.php

    // ----------------------------------------------------------------------------
    // Enable or Disable the Laravel Log Keeper.
    // If it is set to false, no operations will be performed and it will be logged
    // if the logs are enabled
    // ----------------------------------------------------------------------------
    'enabled' => env('LARAVEL_LOG_KEEPER_ENABLED', true),

    // ----------------------------------------------------------------------------
    // Enable or Disable the Laravel Log Keeper for remote operations.
    // if it is set to false, the local files older than the local retention will be
    // delete without being uploaded to the remote disk
    // ----------------------------------------------------------------------------
    'enabled_remote' => env('LARAVEL_LOG_KEEPER_ENABLED_REMOTE', false),

    // ----------------------------------------------------------------------------
    // Where in the remote location it will be stored. You can leave it blank
    // or specify a custom folder like proj1-prod or proj1-integ so that you could
    // use the same s3 bucket for storing the logs in different environments
    // ----------------------------------------------------------------------------
    'remote_path' => rtrim(env('LARAVEL_LOG_KEEPER_REMOTE_PATH'), '/'),

    // ----------------------------------------------------------------------------
    // Enable or Disable processing Files With Zero Days Retention
    // Normally log files in the same day, sometimes is usefull because another process
    // lock or logging into this file and compress fails.
    // ----------------------------------------------------------------------------
    'processFilesWithZeroDaysRetention' => env('LARAVEL_LOG_KEEPER_PROCESS_FILES_WITH_ZERO_DAYS_RETENTION', false),

    // ----------------------------------------------------------------------------
    // How many days a file will be kept on the local disk before
    // being uploaded to the remote disk.
    // Default is 7 days.
    // Local files with more than 7 days will be compressed using bzip2 and uploaded
    // to the remote disk. They will also be deleted from the local disk after being
    // uploaded
    // ----------------------------------------------------------------------------
    'localRetentionDays' => env('LARAVEL_LOG_KEEPER_LOCAL_RETENTION_DAYS', 7),
    
    // ----------------------------------------------------------------------------
    // How many days a compressed file will be kept on the local disk.
    // Default is 15 days.
    // Local compressed files with more than 15 days will be deleted on the local disk.
    // ----------------------------------------------------------------------------
    'localRetentionDaysForCompressed' => env('LARAVEL_LOG_KEEPER_LOCAL_RETENTION_DAYS_FOR_COMPRESSED', 15),

    // ----------------------------------------------------------------------------
    // How many days a file will be kept on the remote for.
    // The days here means days after the local retention. So 30 would actually
    // 30 + 7 = 37
    // Only files older than 37 days would be deleted from the remote disk
    // ----------------------------------------------------------------------------
    'remoteRetentionDays' => env('LARAVEL_LOG_KEEPER_REMOTE_RETENTION_DAYS', 30),

    'remoteRetentionDaysCalculated' =>
        env('LARAVEL_LOG_KEEPER_REMOTE_RETENTION_DAYS', 30) +
        env('LARAVEL_LOG_KEEPER_LOCAL_RETENTION_DAYS', 7),

    // ----------------------------------------------------------------------------
    // Which config/filesystems.php disk will be used for remote disk.
    // This would be typically a AWS S3 Disk, (s)ftp, Dropbox or any other configured
    // disk that will store the old logs
    // ----------------------------------------------------------------------------
    'remote_disk' => env('LARAVEL_LOG_KEEPER_REMOTE_DISK'),

    // ----------------------------------------------------------------------------
    // Define whether Laravel Log Keeper will log actions or not.
    // The log will be stored in the logs folders with name
    // laravel-log-keeper-{yyyy-mm-dd}.log
    // ----------------------------------------------------------------------------
    'log' => env('LARAVEL_LOG_KEEPER_LOG', true)
];
```

## Security

If you discover any security related issues, please email mathiasgrimm@gmail.com instead of using the issue tracker.

## Credits

- [Mathias Grimm](https://github.com/mathiasgrimm)

