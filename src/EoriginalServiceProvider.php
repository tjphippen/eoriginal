<?php namespace Phippen\Eoriginal;

use Illuminate\Support\ServiceProvider;

class EoriginalServiceProvider extends ServiceProvider
{
    /**
     *  Bootstrap the application events.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/eoriginal.php' => config_path('eoriginal.php'),
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->bind('Eoriginal', function($app)
        {
            return new Eoriginal($app->config->get('eoriginal', []));
        });

        // Auto boot the facade.
        $this->app->booting(function()
        {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Eoriginal', 'Phippen\Eoriginal\Facades\Eoriginal');
        });
    }

}