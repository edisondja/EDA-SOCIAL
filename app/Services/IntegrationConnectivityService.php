<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class IntegrationConnectivityService
{
    /**
     * @return array{redis: array<string, mixed>, rabbitmq: array<string, mixed>}
     */
    public function snapshots(): array
    {
        return [
            'redis' => $this->redisSnapshot(),
            'rabbitmq' => $this->rabbitMqSnapshot(),
        ];
    }

    /**
     * @return array{
     *   uses_redis_for_cache: bool,
     *   configured: bool,
     *   reachable: ?bool,
     *   label: string,
     *   detail: string
     * }
     */
    public function redisSnapshot(): array
    {
        $cacheDriver = (string) config('cache.default', 'file');
        $usesRedis = $cacheDriver === 'redis';
        /* config() para que funcione con `php artisan config:cache` (env() fuera de config/ devuelve null). */
        $host = trim((string) config('database.redis.default.host', ''));
        if ($host === '') {
            return [
                'uses_redis_for_cache' => $usesRedis,
                'configured' => false,
                'reachable' => null,
                'label' => 'Sin configurar',
                'detail' => 'Definí REDIS_HOST en .env para probar conexión.',
            ];
        }

        $port = (int) config('database.redis.default.port', 6379);
        $connection = $usesRedis ? 'cache' : 'default';

        try {
            $client = Redis::connection($connection);
            $pong = $client->ping();
            $ok = $pong === true || $pong === '+PONG' || $pong === 'PONG'
                || (is_string($pong) && stripos($pong, 'PONG') !== false);

            $detail = $usesRedis
                ? "Caché usa Redis (conexión «{$connection}») · {$host}:{$port}"
                : "Caché usa «{$cacheDriver}»; probado Redis «{$connection}» · {$host}:{$port}";

            return [
                'uses_redis_for_cache' => $usesRedis,
                'configured' => true,
                'reachable' => (bool) $ok,
                'label' => $ok ? 'Conectado' : 'No conectado',
                'detail' => $detail,
            ];
        } catch (\Throwable $e) {
            return [
                'uses_redis_for_cache' => $usesRedis,
                'configured' => true,
                'reachable' => false,
                'label' => 'No conectado',
                'detail' => $usesRedis
                    ? "Redis caché no responde ({$host}:{$port}): ".$e->getMessage()
                    : "Redis no responde ({$host}:{$port}): ".$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{
     *   host_configured: bool,
     *   amqp_reachable: ?bool,
     *   management_ok: ?bool,
     *   label: string,
     *   detail: string
     * }
     */
    public function rabbitMqSnapshot(): array
    {
        $rabbit = config('queue.connections.rabbitmq', []);
        $firstHost = is_array($rabbit['hosts'] ?? null) ? ($rabbit['hosts'][0] ?? []) : [];
        $host = trim((string) ($firstHost['host'] ?? ''));
        if ($host === '') {
            return [
                'host_configured' => false,
                'amqp_reachable' => null,
                'management_ok' => null,
                'label' => 'Sin configurar',
                'detail' => 'Definí RABBITMQ_HOST (y credenciales) en .env para el broker AMQP. Si ya lo tenés y ves esto tras `config:cache`, ejecutá `php artisan config:clear` y volvé a cachear.',
            ];
        }

        $port = (int) ($firstHost['port'] ?? 5672);
        $amqpUser = (string) ($firstHost['user'] ?? 'guest');
        $amqpPass = (string) ($firstHost['password'] ?? 'guest');
        $amqpOk = $this->tcpReachable($host, $port, 3);

        $mgmtBase = rtrim((string) ($rabbit['management_url'] ?? ''), '/');
        if ($mgmtBase === '') {
            $mgmtBase = 'http://'.$host.':'.(int) ($rabbit['management_port'] ?? 15672);
        }

        /* Evita "Undefined array key" con config cacheado viejo; credenciales vacías = mismas que AMQP. */
        $mgmtUserRaw = trim((string) ($rabbit['management_user'] ?? ''));
        $mgmtUser = $mgmtUserRaw !== '' ? $mgmtUserRaw : $amqpUser;
        $mgmtPassRaw = (string) ($rabbit['management_password'] ?? '');
        $mgmtPass = $mgmtPassRaw !== '' ? $mgmtPassRaw : $amqpPass;

        $mgmtOk = null;
        $mgmtDetail = '';
        try {
            $url = $mgmtBase.'/api/overview';
            $resp = Http::timeout(4)->withBasicAuth($mgmtUser, $mgmtPass)->acceptJson()->get($url);
            $mgmtOk = $resp->successful();
            if (!$mgmtOk) {
                $mgmtDetail = ' Management HTTP '.$resp->status().'.';
            }
        } catch (\Throwable $e) {
            $mgmtOk = false;
            $mgmtDetail = ' Management: '.$e->getMessage();
        }

        if ($amqpOk && $mgmtOk) {
            $label = 'Conectado';
        } elseif ($amqpOk) {
            $label = 'Broker OK';
        } elseif ($mgmtOk) {
            $label = 'Parcial';
        } else {
            $label = 'No conectado';
        }

        $detail = "AMQP {$host}:{$port} — ".($amqpOk ? 'puerto alcanzable' : 'puerto no alcanzable');
        $detail .= ' · Management '.$mgmtBase.' — '.($mgmtOk === true ? 'API OK' : ($mgmtOk === false ? 'API no disponible'.$mgmtDetail : 'no probado'));

        return [
            'host_configured' => true,
            'amqp_reachable' => $amqpOk,
            'management_ok' => $mgmtOk,
            'label' => $label,
            'detail' => $detail,
        ];
    }

    private function tcpReachable(string $host, int $port, int $timeoutSeconds): bool
    {
        $errno = 0;
        $errstr = '';
        $ctx = stream_socket_client(
            'tcp://'.$host.':'.$port,
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT
        );

        if (is_resource($ctx)) {
            fclose($ctx);

            return true;
        }

        return false;
    }
}
