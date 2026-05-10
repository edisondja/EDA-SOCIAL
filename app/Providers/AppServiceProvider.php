<?php

namespace App\Providers;

use App\Category;
use App\Support\PlatformConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
        $this->ensureRedisClientDoesNotRequireMissingExtension();
        $this->ensureRabbitMqQueueConnectionConfigured();
    }

    /**
     * Sin extensión phpredis, Laravel igual puede usar Redis vía predis/predis.
     * Corrige config cacheada o REDIS_CLIENT=phpredis cuando la extensión no está cargada
     * (evita LogicException al instanciar PhpRedisConnector).
     */
    private function ensureRedisClientDoesNotRequireMissingExtension(): void
    {
        if (!$this->app->bound('config')) {
            return;
        }

        $this->app->booting(function () {
            if (! $this->app->bound('config')) {
                return;
            }
            $config = $this->app->make('config');
            $client = $config->get('database.redis.client', 'predis');
            if ($client === 'phpredis' && ! extension_loaded('redis')) {
                $config->set('database.redis.client', 'predis');
            }
        });
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
            /* Config cacheado antiguo: asegurar claves management_* para evitar errores al leer con config(). */
            $config->set('queue.connections.rabbitmq', array_replace([
                'management_url' => null,
                'management_port' => 15672,
                'management_user' => null,
                'management_password' => null,
            ], $existing));

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
        if ($this->shouldForceHttpsUrls()) {
            URL::forceScheme('https');
        }

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

        $redisHost = trim((string) config('database.redis.default.host', ''));
        if (PlatformConfig::get('feature_redis_cache') === '1'
            && (extension_loaded('redis') || extension_loaded('Redis'))
            && $redisHost !== ''
        ) {
            config(['cache.default' => 'redis']);
        }
        // RabbitMQ: Laravel no lo trae por defecto. Si instalas vladimir-yuldashev/laravel-queue-rabbitmq
        // y defines RABBITMQ_*, puedes poner QUEUE_CONNECTION=rabbitmq en .env.
        $rabbitHost = trim((string) (config('queue.connections.rabbitmq.hosts.0.host') ?? ''));
        if (PlatformConfig::get('feature_rabbit_queue') === '1' && $rabbitHost !== '') {
            if (class_exists(\VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector::class)) {
                config(['queue.default' => 'rabbitmq']);
            }
        }
    }

    /**
     * URLs absolutas en https: solo en APP_ENV=production cuando APP_URL usa https://.
     * Cualquier entorno: definí FORCE_HTTPS=true|false para forzar o desactivar explícitamente.
     */
    private function shouldForceHttpsUrls(): bool
    {
        $raw = env('FORCE_HTTPS');
        if ($raw !== null && $raw !== '') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        if (!$this->app->environment('production')) {
            return false;
        }

        return str_starts_with((string) config('app.url'), 'https://');
    }
}
