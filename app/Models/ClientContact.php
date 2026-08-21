<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 의뢰자 관계자(매니저/실장/소속사 담당 등) — 의뢰자당 최대 10명 */
class ClientContact extends Model
{
    /** 의뢰자당 관계자 최대 수 */
    public const MAX_PER_CLIENT = 10;

    protected $fillable = [
        'client_id',
        'name',
        'phone',
        'relation',
        'memo',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
