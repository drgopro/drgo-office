<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\WikiTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WikiTemplate extends Model
{
    /** @use HasFactory<WikiTemplateFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name',
        'content',
        'created_by',
        'updated_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
