<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** AS 현황판 건 — 사무실로 물건을 받은 시점부터 기재, 의뢰자·프로젝트 연동 필수 */
class AsRequest extends Model
{
    use SoftDeletes;

    /** 상태 → 한글 라벨 (4단계 순서 고정) */
    public const STATUS_LABELS = [
        'received' => '사무실 접수',
        'in_repair' => 'AS 접수/진행',
        'repaired' => 'AS 완료',
        'done' => '완료',
    ];

    /** 상태 → 해당 단계 전환 시각 컬럼 */
    public const STATUS_TIMESTAMPS = [
        'received' => 'received_at',
        'in_repair' => 'sent_at',
        'repaired' => 'repaired_at',
        'done' => 'done_at',
    ];

    public const COST_LABELS = [
        'free' => '무상',
        'paid' => '유상',
    ];

    protected $fillable = [
        'client_id',
        'project_id',
        'product_name',
        'description',
        'status',
        'cost_type',
        'cost_amount',
        'received_at',
        'sent_at',
        'repaired_at',
        'done_at',
        'created_by',
    ];

    protected $casts = [
        'cost_amount' => 'integer',
        'received_at' => 'datetime',
        'sent_at' => 'datetime',
        'repaired_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
