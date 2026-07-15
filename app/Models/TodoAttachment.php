<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TodoAttachment extends Model
{
    protected $fillable = [
        'todo_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    /** @return BelongsTo<Todo, $this> */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }
}
