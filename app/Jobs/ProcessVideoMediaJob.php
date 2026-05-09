<?php

namespace App\Jobs;

use App\Services\LocalVideoCompressor;
use App\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoMediaJob implements ShouldQueue
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

    public function handle(LocalVideoCompressor $compressor): void
    {
        $video = Video::query()->with('media')->find($this->videoId);
        if (!$video) {
            return;
        }

        if (!$compressor->isEnabled()) {
            Log::info('ProcessVideoMediaJob: FFmpeg desactivado o no disponible en PATH', ['video_id' => $video->id]);

            return;
        }

        $urls = [];
        foreach ($video->media as $item) {
            if (($item->type ?? '') === 'video' && !empty($item->url)) {
                $urls[] = $item->url;
            }
        }
        if (!empty($video->video_url)) {
            $urls[] = $video->video_url;
        }
        if (!empty($video->preview_url)) {
            $urls[] = $video->preview_url;
        }

        $urls = array_values(array_unique(array_filter($urls)));
        $pathsDone = [];

        foreach ($urls as $url) {
            $path = $compressor->resolvePublicDiskPath($url);
            if (!$path || isset($pathsDone[$path])) {
                continue;
            }
            $pathsDone[$path] = true;

            try {
                $compressor->compressInPlace($path);
            } catch (\Throwable $e) {
                Log::warning('ProcessVideoMediaJob: error al comprimir', [
                    'video_id' => $video->id,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $video->refresh();
        if ($video->needsPosterImageGeneration()) {
            $key = 'poster:queued:video:' . $video->id;
            if (Cache::add($key, '1', now()->addMinutes(10))) {
                GenerateVideoPosterJob::dispatch($video->id);
            }
        }
    }
}
