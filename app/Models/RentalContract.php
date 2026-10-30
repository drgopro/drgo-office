<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalContract extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /** 계약 종류 — 장비 렌탈(기본) / 서버 렌탈 */
    public const KIND_EQUIPMENT = 'equipment';

    public const KIND_SERVER = 'server';

    public const KINDS = [self::KIND_EQUIPMENT, self::KIND_SERVER];

    public const KIND_LABELS = [self::KIND_EQUIPMENT => '장비 렌탈', self::KIND_SERVER => '서버 렌탈'];

    /** 상태 — 준비중(계약 후 설치·개통 전) / 진행중 / 해지 */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_TERMINATED];

    protected $fillable = [
        'client_id', 'kind', 'product_id', 'product_name', 'start_date', 'end_date', 'monthly_fee', 'fee_manual',
        'status', 'memo', 'calendar_meta', 'server_name', 'server_spec', 'server_access',
    ];

    protected $attributes = [
        'kind' => self::KIND_EQUIPMENT,
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'monthly_fee' => 'integer',
        'fee_manual' => 'boolean',
        'calendar_meta' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(RentalProduct::class, 'product_id');
    }

    /** 카드에 표시할 상품명 — 선택 당시 스냅샷 우선, 없으면 현재 상품명 */
    public function displayProductName(): ?string
    {
        return $this->product_name ?: $this->product?->name;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isServer(): bool
    {
        return $this->kind === self::KIND_SERVER;
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? self::KIND_LABELS[self::KIND_EQUIPMENT];
    }
}
