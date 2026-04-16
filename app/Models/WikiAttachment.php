<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiAttachment extends Model
{
    protected $fillable = [
        'wiki_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function wiki()
    {
        return $this->belongsTo(Wiki::class);
    }
}
