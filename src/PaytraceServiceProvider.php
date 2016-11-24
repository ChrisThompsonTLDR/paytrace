<?php

namespace Christhompsontldr\Paytrace;

use Illuminate\Support\ServiceProvider;
use Christhompsontldr\Paytrace\Paytrace;

class PaytraceServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // publish configs
        $this->publishes([
           realpath(dirname(__DIR__)) . '/config/paytrace.php' => config_path('paytrace.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerPaytraceBuilder();

        $this->app->alias('paytrace', 'Christhompsontldr\Paytrace\Paytrace');
    }

    /**
     * Register the Paytrace instance.
     *
     * @return void
     */
    protected function registerPaytraceBuilder()
    {
        $this->app->singleton('paytrace', function ($app) {
            $paytrace = new Paytrace();

            $paytrace->setUsername(config('paytrace.username'));
            $paytrace->setPassword(config('paytrace.password'));

            return $paytrace;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['paytrace', 'Christhompsontldr\Paytrace\Paytrace'];
    }
}