<?php

namespace App\Jobs;

use App\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class EnqueueMissingPosterJobsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var string */
    public $batchId;

    /** @var bool */
    public $durationAware;

    /** @var bool */
    public $allVideos;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(string $batchId, bool $durationAware, bool $allVideos = false)
    {
        $this->batchId = $batchId;
        $this->durationAware = $durationAware;
        $this->allVideos = $allVideos;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $key = 'admin:poster-batch:' . $this->batchId;
        $state = Cache::get($key);
        if (!is_array($state)) {
            return;
        }

        $query = Video::query()->orderBy('id');
        if (!$this->allVideos) {
            $query->where(function ($w) {
                $w->where(function ($a) {
                    $a->whereNull('thumbnail_url')->orWhere('thumbnail_url', '');
                })->orWhere(function ($a) {
                    foreach (['%.mp4%', '%.webm%', '%.mov%', '%.m4v%', '%.mkv%', '%.ts%'] as $like) {
                        $a->orWhere('thumbnail_url', 'like', $like);
                    }
                });
            });
        }

        $total = (int) $query->count();
        $state['total'] = $total;
        $state['status'] = $total > 0 ? 'running' : 'done';
        $state['scan_done'] = true;
        if ($total === 0) {
            $state['finished_at'] = now()->toDateTimeString();
        }
        Cache::put($key, $state, now()->addHours(6));

        if ($total === 0) {
            return;
        }

        $query->select('id')->chunkById(200, function ($chunk) {
            foreach ($chunk as $video) {
                GenerateVideoPosterProgressJob::dispatch((int) $video->id, $this->batchId, false, $this->durationAware);
            }
        });
    }

    public function failed(\Throwable $e): void
    {
        $key = 'admin:poster-batch:' . $this->batchId;
        $state = Cache::get($key);
        if (!is_array($state)) {
            return;
        }
        $state['status'] = 'failed';
        $state['scan_done'] = true;
        $state['finished_at'] = now()->toDateTimeString();
        $recent = is_array($state['recent'] ?? null) ? $state['recent'] : [];
        $recent[] = 'Escáner de posts falló: ' . $e->getMessage();
        $state['recent'] = array_slice($recent, -40);
        Cache::put($key, $state, now()->addHours(6));
    }
}
