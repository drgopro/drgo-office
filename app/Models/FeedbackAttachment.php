<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackAttachment extends Model
{
    protected $fillable = [
        'feedback_post_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    /** @return BelongsTo<FeedbackPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedbackPost::class, 'feedback_post_id');
    }
}
