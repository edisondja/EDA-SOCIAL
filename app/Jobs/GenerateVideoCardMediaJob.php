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

/**
 * Poster JPG + clip MP4 para la tarjeta del feed (hover), vía ffmpeg.
 * Cola {@code media} (RabbitMQ/Redis según QUEUE_CONNECTION).
 */
class GenerateVideoCardMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $videoId;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(int $videoId)
    {
        $this->videoId = $videoId;
        $this->onQueue('media');
    }

    public function handle(VideoPreviewGenerationService $previewService): void
    {
        $video = Video::query()->find($this->videoId);
        if ($video) {
            $previewService->generateForVideo($video);
        }
        Cache::forget($this->cacheKey());
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget($this->cacheKey());
    }

    private function cacheKey(): string
    {
        return 'hover-card:queued:' . $this->videoId;
    }
}
