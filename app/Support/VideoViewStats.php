<?php

namespace App\Support;

use App\Video;
use App\VideoDailyView;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class VideoViewStats
{
    /**
     * @return array<int, array{date:string, views:int}>
     */
    public static function last30Days(Video $video): array
    {
        try {
            if (!Schema::hasTable('video_daily_views')) {
                return self::emptySeries();
            }
        } catch (\Throwable $e) {
            return self::emptySeries();
        }

        $start = Carbon::today()->subDays(29);
        $rows = VideoDailyView::query()
            ->where('video_id', $video->id)
            ->where('stat_date', '>=', $start->toDateString())
            ->orderBy('stat_date')
            ->get(['stat_date', 'views']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->stat_date->format('Y-m-d')] = (int) $row->views;
        }

        $out = [];
        for ($i = 0; $i < 30; $i++) {
            $d = $start->copy()->addDays($i)->format('Y-m-d');
            $out[] = [
                'date' => $d,
                'views' => (int) ($map[$d] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{date:string, views:int}>
     */
    private static function emptySeries(): array
    {
        $start = Carbon::today()->subDays(29);
        $out = [];
        for ($i = 0; $i < 30; $i++) {
            $out[] = [
                'date' => $start->copy()->addDays($i)->format('Y-m-d'),
                'views' => 0,
            ];
        }

        return $out;
    }
}
