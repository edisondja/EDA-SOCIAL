<?php

namespace App\Providers;

use App\Support\PlatformConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        try {
            if (!Schema::hasTable('platform_settings')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        if (PlatformConfig::get('feature_redis_cache') === '1'
            && (extension_loaded('redis') || extension_loaded('Redis'))
            && env('REDIS_HOST')
        ) {
            config(['cache.default' => 'redis']);
        }

        // RabbitMQ: Laravel no lo trae por defecto. Si instalas vladimir-yuldashev/laravel-queue-rabbitmq
        // y defines RABBITMQ_*, puedes poner QUEUE_CONNECTION=rabbitmq en .env.
        if (PlatformConfig::get('feature_rabbit_queue') === '1' && env('RABBITMQ_HOST')) {
            if (class_exists(\VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector::class)) {
                config(['queue.default' => 'rabbitmq']);
            }
        }
    }
}
