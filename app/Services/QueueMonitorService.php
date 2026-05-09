<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class QueueMonitorService
{
    /**
     * @return array{
     *   driver:string,
     *   source:string,
     *   queues:array<int, array{name:string,waiting:int,processing:int,consumers:?int,state:?string}>,
     *   failed_jobs:int,
     *   error:?string,
     *   updated_at:string
     * }
     */
    public function snapshot(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $failed = $this->failedJobsCount();
        $base = [
            'driver' => $driver,
            'source' => $driver,
            'queues' => [],
            'failed_jobs' => $failed,
            'error' => null,
            'updated_at' => now()->toIso8601String(),
        ];

        if ($driver === 'rabbitmq') {
            $rabbit = $this->rabbitMqQueues();
            $base['source'] = 'rabbitmq_management';
            $base['queues'] = $rabbit['queues'];
            $base['error'] = $rabbit['error'];

            return $base;
        }

        if ($driver === 'database') {
            $db = $this->databaseQueues();

            return array_merge($base, [
                'source' => 'database',
                'queues' => $db['queues'],
                'error' => $db['error'],
            ]);
        }

        return array_merge($base, [
            'source' => 'none',
            'error' => $driver === 'sync'
                ? 'QUEUE_CONNECTION=sync: los jobs se ejecutan en el mismo request (no hay cola en segundo plano).'
                : 'Vista de colas no disponible para el driver "'.$driver.'". Usá rabbitmq o database.',
        ]);
    }

    private function failedJobsCount(): int
    {
        try {
            if (!Schema::hasTable('failed_jobs')) {
                return 0;
            }

            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array{queues: array, error: ?string}
     */
    private function databaseQueues(): array
    {
        try {
            if (!Schema::hasTable('jobs')) {
                return ['queues' => [], 'error' => 'No existe la tabla jobs (ejecutá migraciones).'];
            }

            $rows = DB::table('jobs')
                ->selectRaw('queue,
                    SUM(CASE WHEN reserved_at IS NULL THEN 1 ELSE 0 END) as waiting,
                    SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as processing')
                ->groupBy('queue')
                ->orderBy('queue')
                ->get();

            $queues = [];
            foreach ($rows as $r) {
                $queues[] = [
                    'name' => (string) $r->queue,
                    'waiting' => (int) $r->waiting,
                    'processing' => (int) $r->processing,
                    'consumers' => null,
                    'state' => null,
                ];
            }

            return ['queues' => $queues, 'error' => null];
        } catch (\Throwable $e) {
            return ['queues' => [], 'error' => 'No se pudo leer la tabla jobs: '.$e->getMessage()];
        }
    }

    /**
     * @return array{queues: array, error: ?string}
     */
    private function rabbitMqQueues(): array
    {
        $mgmtBase = rtrim((string) env('RABBITMQ_MANAGEMENT_URL', ''), '/');
        if ($mgmtBase === '') {
            $host = (string) env('RABBITMQ_HOST', '');
            if ($host !== '') {
                $mgmtBase = 'http://'.$host.':'.(int) env('RABBITMQ_MANAGEMENT_PORT', 15672);
            }
        }
        if ($mgmtBase === '') {
            return [
                'queues' => [],
                'error' => 'Definí RABBITMQ_MANAGEMENT_URL (p. ej. http://127.0.0.1:15672) o RABBITMQ_HOST para ver colas.',
            ];
        }

        $user = (string) (env('RABBITMQ_MANAGEMENT_USER') ?: env('RABBITMQ_USER', 'guest'));
        $pass = (string) (env('RABBITMQ_MANAGEMENT_PASSWORD') ?: env('RABBITMQ_PASSWORD', 'guest'));
        $vhost = (string) env('RABBITMQ_VHOST', '/');
        $vhostEnc = rawurlencode($vhost);

        $url = $mgmtBase.'/api/queues/'.$vhostEnc;

        try {
            $response = Http::timeout(5)
                ->withBasicAuth($user, $pass)
                ->acceptJson()
                ->get($url);

            if (!$response->successful()) {
                return [
                    'queues' => [],
                    'error' => 'Management API HTTP '.$response->status().'. Revisá usuario/contraseña y plugin rabbitmq_management.',
                ];
            }

            $list = $response->json();
            if (!is_array($list)) {
                return ['queues' => [], 'error' => 'Respuesta inválida del API de RabbitMQ.'];
            }

            $watchRaw = (string) env('RABBITMQ_ADMIN_QUEUE_NAMES', 'media,default');
            $watch = array_values(array_filter(array_map('trim', explode(',', $watchRaw))));

            $queues = [];
            foreach ($list as $item) {
                if (!is_array($item) || empty($item['name'])) {
                    continue;
                }
                $name = (string) $item['name'];
                if ($watch !== [] && !in_array($name, $watch, true)) {
                    continue;
                }
                $ready = (int) ($item['messages_ready'] ?? 0);
                $unacked = (int) ($item['messages_unacknowledged'] ?? 0);
                $consumers = (int) ($item['consumers'] ?? 0);
                $queues[] = [
                    'name' => $name,
                    'waiting' => $ready,
                    'processing' => $unacked,
                    'consumers' => $consumers,
                    'state' => isset($item['state']) ? (string) $item['state'] : null,
                ];
            }

            usort($queues, fn ($a, $b) => strcmp($a['name'], $b['name']));

            $emptyHint = $watch === []
                ? 'No hay colas en este vhost o el broker no respondió listas.'
                : 'No hay colas con nombres ['.implode(', ', $watch).'] en el vhost (crealas al encolar jobs o ampliá RABBITMQ_ADMIN_QUEUE_NAMES).';

            return ['queues' => $queues, 'error' => $queues === [] ? $emptyHint : null];
        } catch (\Throwable $e) {
            return ['queues' => [], 'error' => $e->getMessage()];
        }
    }
}
