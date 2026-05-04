<?php

namespace App\Services;

use App\Video;
use App\VideoDailyView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VideoViewTracker
{
    public static function record(Video $video): void
    {
        try {
            if (!Schema::hasTable('video_daily_views')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        try {
            $date = Carbon::today()->toDateString();
            DB::transaction(function () use ($video, $date) {
                $video->increment('views_count');
                $row = VideoDailyView::query()->firstOrCreate(
                    [
                        'video_id' => $video->id,
                        'stat_date' => $date,
                    ],
                    ['views' => 0]
                );
                $row->increment('views');
            });
        } catch (\Throwable $e) {
            // no bloquear la página si falla el tracking
        }
    }
}
