<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoConsultation extends Model
{
    protected $fillable = ['demo_project_id', 'consult_type', 'content', 'result', 'consulted_at', 'created_by'];

    protected $casts = ['consulted_at' => 'date:Y-m-d'];
}
