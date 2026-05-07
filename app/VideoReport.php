<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VideoReport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'video_id',
        'user_id',
        'reason',
        'details',
        'status',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function reasonLabels(): array
    {
        return [
            'spam' => 'Spam o contenido basura',
            'inappropriate' => 'Contenido inapropiado',
            'copyright' => 'Derechos de autor',
            'misleading' => 'Información engañosa',
            'violence' => 'Violencia o contenido sensible',
            'other' => 'Otro motivo',
        ];
    }
}
