<?php

namespace a15lam\Alarm;

use a15lam\Alarm\Models\AlarmdotcomConfig;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use a15lam\Alarm\Services\Alarm;
use Cache;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    //use ServiceDocBuilder;

    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType([
                    'name'            => 'alarm',
                    'label'           => 'Alarm',
                    'description'     => 'Alarm.com service.',
                    'group'           => 'IoT',
                    'config_handler'  => AlarmdotcomConfig::class,
//                    'default_api_doc' => function ($service){
//                        return $this->buildServiceDoc($service->id, Alarm::getApiDocInfo($service));
//                    },
                    'factory'         => function ($config){
                        return new Alarm($config);
                    },
                ])
            );
        });
    }

    public function boot()
    {
        // add migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}