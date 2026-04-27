<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'nickname',
        'phone',
        'phones',
        'address',
        'address_detail',
        'grade',
        'platforms',
        'content_types',
        'inflow_source',
        'client_type',
        'gender',
        'affiliation',
        'important_memo',
        'memo',
        'custom_data',
        'assigned_user_id',
        'status',
        'last_contact_at',
    ];

    protected $casts = [
        'phones' => 'array',
        'platforms' => 'array',
        'content_types' => 'array',
        'custom_data' => 'array',
        'last_contact_at' => 'datetime',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function documents()
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function memos()
    {
        return $this->hasMany(ClientMemo::class)->orderByDesc('created_at');
    }

    public function estimates()
    {
        return $this->hasMany(Estimate::class)->orderByDesc('created_at');
    }

    public function rentalContracts()
    {
        return $this->hasMany(RentalContract::class)->orderByDesc('start_date');
    }

    public function broadcastRoomContracts()
    {
        return $this->hasMany(BroadcastRoomContract::class)->orderByDesc('start_date');
    }

    public function broadcastRoomUsages()
    {
        return $this->hasMany(BroadcastRoomUsage::class)->orderByDesc('used_date');
    }
}
