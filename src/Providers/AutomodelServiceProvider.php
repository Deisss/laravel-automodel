<?php

namespace Deisss\Automodel\Providers;

use Deisss\Automodel\Console\Commands\AutomodelDatabase;
use Deisss\Automodel\Console\Commands\AutomodelTable;
use Illuminate\Support\ServiceProvider;

class AutomodelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $DS = DIRECTORY_SEPARATOR;

        // Where to locate the base of this plugin
        $root = __DIR__.$DS.'..'.$DS;
        // Where to locate the views of this plugin
        $views = $root.'resources'.$DS.'views'.$DS.'models';

        // Loading views
        $this->loadViewsFrom($views, 'automodel');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AutomodelDatabase::class,
                AutomodelTable::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
