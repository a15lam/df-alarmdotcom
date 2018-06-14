<?php

namespace a15lam\Alarm;

use DreamFactory\Core\Models\BaseModel;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use a15lam\Alarm\Services\Alarm;
use Cache;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    //use ServiceDocBuilder;

    public function boot()
    {
        // Auto seed the service as it doesn't requir any configuration.
        // Default service name is 'alarm'. You can change this using
        // ALARM_SERVICE_NAME Environment option.
        if (false === Cache::get('alarm-seeded', false)) {
            $serviceName = env('ALARM_SERVICE_NAME', 'alarm');
            $model = Service::whereName($serviceName)->whereType('alarm')->get()->first();

            if (empty($model)) {
                $model = Service::create([
                    'name'        => $serviceName,
                    'type'        => 'alarm',
                    'label'       => 'Alarm.com  Service',
                    'description' => 'A DreamFactory service for alarm.com',
                    'is_active'   => 1
                ]);

                BaseModel::unguard();
                $model->mutable = 0;
                $model->update();
                BaseModel::reguard();
                Cache::forever('alarm-seeded', true);
            }
        }
    }

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
                    'config_handler'  => null,
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
}