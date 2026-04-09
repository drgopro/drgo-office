<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ProjectDocument extends Model
{
    use LogsActivity;

    protected $fillable = [
        'project_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'note',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
