<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 위키/공지 읽음 확인 — 문서를 연 사용자 기록 (문서당 사용자 1행) */
class WikiRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['wiki_id', 'user_id', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
