<?php

namespace App\Jobs;

use App\Services\HlsTranscodingService;
use App\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class GenerateVideoHlsJob implements ShouldQueue
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

    public function handle(HlsTranscodingService $service): void
    {
        $video = Video::query()->find($this->videoId);
        if ($video) {
            $service->transcodeVideo($video);
        }
        Cache::forget('hls:queued:video:' . $this->videoId);
    }
}

