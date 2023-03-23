<?php
/**
 * Copyright (c) Padosoft.com 2020.
 */

namespace MathiasGrimm\LaravelLogKeeper\Test;

use MathiasGrimm\LaravelLogKeeper\Providers\LaravelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public function setUp(): void{
        parent::setUp();
    }
    protected function getPackageProviders($app){
        return [
            LaravelServiceProvider::class,
        ];
    }
}