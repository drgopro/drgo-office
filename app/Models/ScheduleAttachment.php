<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAttachment extends Model
{
    protected $fillable = [
        'schedule_id',
        'attachment_type',
        'file_name',
        'file_path',
        'thumb_path',
        'mime_type',
        'file_size',
        'note',
        'sort_order',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
