<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 견적 프리셋 — 자주 쓰는 품목 구성 저장본. 불러올 때 현재 판매가로 갱신된다. */
class EstimatePreset extends Model
{
    protected $fillable = ['title', 'items', 'created_by'];

    protected $casts = ['items' => 'array'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
