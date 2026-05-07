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

class GenerateVideoPosterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $videoId;

    public function __construct(int $videoId)
    {
        $this->videoId = $videoId;
        $this->onQueue('media');
    }

    public function handle(VideoPreviewGenerationService $previewService): void
    {
        $video = Video::query()->find($this->videoId);
        if ($video) {
            $previewService->generatePosterIfMissing($video);
        }
        Cache::forget('poster:queued:video:' . $this->videoId);
    }
}

