<?php

namespace App\Providers;

use App\Category;
use App\Support\PlatformConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        $this->ensureRabbitMqQueueConnectionConfigured();
    }

    /**
     * Refuerzo: con `php artisan config:cache` antiguo a veces falta queue.connections.rabbitmq en el blob cacheado.
     */
    private function ensureRabbitMqQueueConnectionConfigured(): void
    {
        if (!$this->app->bound('config')) {
            return;
        }

        $config = $this->app->make('config');
        $existing = $config->get('queue.connections.rabbitmq');
        if (is_array($existing) && ($existing['driver'] ?? null) === 'rabbitmq') {
            return;
        }

        $config->set('queue.connections.rabbitmq', [
            'driver' => 'rabbitmq',
            'queue' => env('RABBITMQ_QUEUE', 'default'),
            'connection' => 'default',
            'hosts' => [
                [
                    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
                    'port' => env('RABBITMQ_PORT', 5672),
                    'user' => env('RABBITMQ_USER', 'guest'),
                    'password' => env('RABBITMQ_PASSWORD', 'guest'),
                    'vhost' => env('RABBITMQ_VHOST', '/'),
                ],
            ],
            'options' => [],
            'worker' => env('RABBITMQ_WORKER', 'default'),
        ]);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('web.layout', function ($view) {
            $categories = collect();
            if (auth()->check()) {
                try {
                    if (Schema::hasTable('categories')) {
                        $categories = Category::query()->orderBy('name')->get();
                    }
                } catch (\Throwable $e) {
                }
            }
            $view->with('publishCategories', $categories);
        });

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
