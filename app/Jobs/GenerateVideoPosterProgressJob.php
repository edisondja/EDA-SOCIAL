<?php

namespace App\Jobs;

use App\Services\VideoPreviewGenerationService;
use App\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class GenerateVideoPosterProgressJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $videoId;

    /** @var string */
    public $batchId;

    /** @var bool */
    public $forceReplace;

    /** @var bool */
    public $durationAware;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(int $videoId, string $batchId, bool $forceReplace, bool $durationAware)
    {
        $this->videoId = $videoId;
        $this->batchId = $batchId;
        $this->forceReplace = $forceReplace;
        $this->durationAware = $durationAware;
        $this->onQueue('media');
    }

    public function handle(VideoPreviewGenerationService $previewService): void
    {
        $video = Video::query()->find($this->videoId);
        if (!$video) {
            $this->mark('fail', 'Video no encontrado.');

            return;
        }

        $r = $previewService->generatePosterJpeg($video, null, $this->forceReplace, $this->durationAware);
        $status = $r['status'] === 'ok' ? 'ok' : ($r['status'] === 'skip' ? 'ok' : 'fail');
        $this->mark($status, (string) ($r['detail'] ?? ''));
    }

    public function failed(\Throwable $e): void
    {
        $this->mark('fail', 'Excepción: ' . $e->getMessage());
    }

    private function mark(string $status, string $detail): void
    {
        $key = 'admin:poster-batch:' . $this->batchId;
        $state = Cache::get($key);
        if (!is_array($state)) {
            return;
        }

        $completed = is_array($state['completed'] ?? null) ? $state['completed'] : [];
        if (isset($completed[$this->videoId])) {
            return;
        }

        $completed[$this->videoId] = [
            'status' => $status,
            'detail' => $detail,
        ];
        $state['completed'] = $completed;
        $state['done'] = max(0, (int) ($state['done'] ?? 0)) + 1;
        if ($status === 'ok') {
            $state['ok'] = max(0, (int) ($state['ok'] ?? 0)) + 1;
        } else {
            $state['failed'] = max(0, (int) ($state['failed'] ?? 0)) + 1;
        }

        $label = $status === 'ok' ? 'OK' : 'ERROR';
        $recent = is_array($state['recent'] ?? null) ? $state['recent'] : [];
        $recent[] = 'Video #' . $this->videoId . ' · ' . $label . ($detail !== '' ? (' · ' . $detail) : '');
        if (count($recent) > 40) {
            $recent = array_slice($recent, -40);
        }
        $state['recent'] = $recent;

        $total = max(0, (int) ($state['total'] ?? 0));
        if ($total > 0 && (int) $state['done'] >= $total) {
            $state['status'] = 'done';
            $state['finished_at'] = now()->toDateTimeString();
        }

        Cache::put($key, $state, now()->addHours(6));
    }
}
