<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoFeedback extends Model
{
    protected $table = 'demo_feedbacks';

    protected $fillable = ['demo_project_id', 'content', 'created_by'];
}
