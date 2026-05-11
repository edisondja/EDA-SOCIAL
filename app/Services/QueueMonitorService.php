<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class QueueMonitorService
{
    private const MAX_ACTIVE_JOBS = 60;

    private const MAX_WAITING_JOBS = 35;

    /**
     * @return array{
     *   driver:string,
     *   source:string,
     *   queues:array<int, array{name:string,waiting:int,processing:int,consumers:?int,state:?string}>,
     *   failed_jobs:int,
     *   error:?string,
     *   updated_at:string,
     *   active_jobs: array<int, array{id:int,queue:string,attempts:int,running_seconds:int,label:string}>,
     *   waiting_jobs: array<int, array{id:int,queue:string,label:string,available_at:?int}>,
     *   rabbit_consumers: array<int, array{tag:string,queue:string,prefetch:?int,channel:string,peer:string}>
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
            'active_jobs' => [],
            'waiting_jobs' => [],
            'rabbit_consumers' => [],
        ];

        if ($driver === 'rabbitmq') {
            $rabbit = $this->rabbitMqQueues();
            $base['source'] = 'rabbitmq_management';
            $base['queues'] = $rabbit['queues'];
            $base['error'] = $rabbit['error'];
            $base['rabbit_consumers'] = $this->rabbitConsumersList();

            return $base;
        }

        if ($driver === 'database') {
            $db = $this->databaseQueues();

            return array_merge($base, [
                'source' => 'database',
                'queues' => $db['queues'],
                'error' => $db['error'],
                'active_jobs' => $this->databaseActiveJobsDetail(),
                'waiting_jobs' => $this->databaseWaitingJobsSample(),
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
     * Jobs que el worker ya reservó (en ejecución).
     *
     * @return array<int, array{id:int,queue:string,attempts:int,running_seconds:int,label:string}>
     */
    private function databaseActiveJobsDetail(): array
    {
        try {
            if (!Schema::hasTable('jobs')) {
                return [];
            }

            $rows = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->orderBy('reserved_at', 'asc')
                ->limit(self::MAX_ACTIVE_JOBS)
                ->get(['id', 'queue', 'attempts', 'reserved_at', 'payload']);

            $now = time();
            $out = [];
            foreach ($rows as $r) {
                $reserved = (int) $r->reserved_at;
                $out[] = [
                    'id' => (int) $r->id,
                    'queue' => (string) $r->queue,
                    'attempts' => (int) $r->attempts,
                    'running_seconds' => max(0, $now - $reserved),
                    'label' => $this->jobPayloadLabel((string) $r->payload),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Próximos jobs en espera (muestra; no es la cola completa).
     *
     * @return array<int, array{id:int,queue:string,label:string,available_at:?int}>
     */
    private function databaseWaitingJobsSample(): array
    {
        try {
            if (!Schema::hasTable('jobs')) {
                return [];
            }

            $rows = DB::table('jobs')
                ->whereNull('reserved_at')
                ->orderBy('id', 'asc')
                ->limit(self::MAX_WAITING_JOBS)
                ->get(['id', 'queue', 'available_at', 'payload']);

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id' => (int) $r->id,
                    'queue' => (string) $r->queue,
                    'label' => $this->jobPayloadLabel((string) $r->payload),
                    'available_at' => isset($r->available_at) ? (int) $r->available_at : null,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function jobPayloadLabel(string $payload): string
    {
        $p = json_decode($payload, true);
        if (!is_array($p)) {
            return 'Job';
        }
        if (!empty($p['displayName']) && is_string($p['displayName'])) {
            return $this->trunc($p['displayName'], 180);
        }
        if (!empty($p['data']['commandName']) && is_string($p['data']['commandName'])) {
            return $this->trunc($p['data']['commandName'], 180);
        }
        if (!empty($p['job'])) {
            return $this->trunc((string) $p['job'], 180);
        }

        return 'Job';
    }

    private function trunc(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max - 1).'…';
    }

    /**
     * @return array{queues: array, error: ?string}
     */
    private function rabbitMqQueues(): array
    {
        $mgmt = $this->rabbitManagementConfig();
        if ($mgmt === null) {
            return [
                'queues' => [],
                'error' => 'Definí RABBITMQ_MANAGEMENT_URL o RABBITMQ_HOST en .env (y regenerá config:cache) para ver colas.',
            ];
        }

        $url = $mgmt['base'].'/api/queues/'.$mgmt['vhost_enc'];

        try {
            $response = Http::timeout(5)
                ->withBasicAuth($mgmt['user'], $mgmt['pass'])
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

            $watch = $this->rabbitAdminQueueNames();

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

    /**
     * Consumidores conectados (cada proceso {@code queue:work} suele ser un consumer).
     *
     * @return array<int, array{tag:string,queue:string,prefetch:?int,channel:string,peer:string}>
     */
    private function rabbitConsumersList(): array
    {
        $mgmt = $this->rabbitManagementConfig();
        if ($mgmt === null) {
            return [];
        }

        $url = $mgmt['base'].'/api/consumers/'.$mgmt['vhost_enc'];

        try {
            $response = Http::timeout(5)
                ->withBasicAuth($mgmt['user'], $mgmt['pass'])
                ->acceptJson()
                ->get($url);

            if (!$response->successful()) {
                return [];
            }

            $list = $response->json();
            if (!is_array($list)) {
                return [];
            }

            $watch = $this->rabbitAdminQueueNames();

            $out = [];
            foreach ($list as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $queueName = '';
                if (!empty($item['queue']['name']) && is_string($item['queue']['name'])) {
                    $queueName = $item['queue']['name'];
                }
                if ($watch !== [] && $queueName !== '' && !in_array($queueName, $watch, true)) {
                    continue;
                }
                $tag = isset($item['consumer_tag']) ? (string) $item['consumer_tag'] : '';
                $prefetch = isset($item['prefetch_count']) ? (int) $item['prefetch_count'] : null;
                $ch = '';
                $peer = '';
                if (!empty($item['channel_details']) && is_array($item['channel_details'])) {
                    $d = $item['channel_details'];
                    $ch = (string) ($d['name'] ?? $d['connection_name'] ?? '');
                    $peer = trim((string) ($d['peer_host'] ?? '').':'.(string) ($d['peer_port'] ?? ''));
                    $peer = $peer === ':' ? '' : $peer;
                }
                $out[] = [
                    'tag' => $this->trunc($tag !== '' ? $tag : '(sin tag)', 120),
                    'queue' => $queueName !== '' ? $queueName : '—',
                    'prefetch' => $prefetch,
                    'channel' => $this->trunc($ch, 80),
                    'peer' => $this->trunc($peer, 80),
                ];
            }

            usort($out, fn ($a, $b) => strcmp($a['queue'].$a['tag'], $b['queue'].$b['tag']));

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{base:string,user:string,pass:string,vhost_enc:string}|null
     */
    private function rabbitManagementConfig(): ?array
    {
        $rabbit = config('queue.connections.rabbitmq', []);
        $firstHost = is_array($rabbit['hosts'] ?? null) ? ($rabbit['hosts'][0] ?? []) : [];

        $mgmtBase = rtrim((string) ($rabbit['management_url'] ?? ''), '/');
        if ($mgmtBase === '') {
            $host = trim((string) ($firstHost['host'] ?? ''));
            if ($host !== '') {
                $mgmtBase = 'http://'.$host.':'.(int) ($rabbit['management_port'] ?? 15672);
            }
        }
        if ($mgmtBase === '') {
            return null;
        }

        $amqpUser = (string) ($firstHost['user'] ?? 'guest');
        $amqpPass = (string) ($firstHost['password'] ?? 'guest');
        $mgmtUser = trim((string) ($rabbit['management_user'] ?? ''));
        $mgmtPass = (string) ($rabbit['management_password'] ?? '');
        $user = $mgmtUser !== '' ? $mgmtUser : $amqpUser;
        $pass = $mgmtPass !== '' ? $mgmtPass : $amqpPass;

        $vhost = (string) ($firstHost['vhost'] ?? '/');

        return [
            'base' => $mgmtBase,
            'user' => $user,
            'pass' => $pass,
            'vhost_enc' => rawurlencode($vhost),
        ];
    }

    /**
     * @return list<string>
     */
    private function rabbitAdminQueueNames(): array
    {
        $raw = (string) config('queue.connections.rabbitmq.admin_queue_names', 'media,default');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}

