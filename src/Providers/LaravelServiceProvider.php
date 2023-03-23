<?php namespace MathiasGrimm\LaravelLogKeeper\Providers;

use Exception;
use Illuminate\Support\ServiceProvider as Provider;
use MathiasGrimm\LaravelLogKeeper\Commands\LogKeeper;

class LaravelServiceProvider extends Provider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LogKeeper::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-log-keeper.php', 'laravel-log-keeper');
        //$this->publishes([__DIR__ . '/../config/laravel-log-keeper.php' => config_path('laravel-log-keeper.php'),]);

        $this->app->singleton('command.laravel-log-keeper', function ($app) {
            return $app['MathiasGrimm\LaravelLogKeeper\Commands\LogKeeper'];
        });
    }
}
