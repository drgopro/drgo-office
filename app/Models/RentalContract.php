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

    protected $fillable = [
        'client_id', 'kind', 'start_date', 'end_date', 'monthly_fee', 'status', 'memo', 'calendar_meta',
        'server_name', 'server_spec', 'server_access',
    ];

    protected $attributes = [
        'kind' => self::KIND_EQUIPMENT,
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'monthly_fee' => 'integer',
        'calendar_meta' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
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
