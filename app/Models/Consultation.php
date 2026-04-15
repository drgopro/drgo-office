<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id',
        'client_id',
        'consulted_at',
        'consultant_id',
        'manager_name',
        'author_user_id',
        'consult_type',
        'result',
        'content',
        'is_important',
    ];

    protected $casts = [
        'consulted_at' => 'date',
        'is_important' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function consultant()
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
