<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'project_type',
        'stage',
        'status',
        'assigned_user_id',
        'memo',
        'as_deadline',
        'completed_at',
    ];

    protected $casts = [
        'as_deadline' => 'date',
        'completed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }
}
