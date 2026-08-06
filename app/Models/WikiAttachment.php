<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiAttachment extends Model
{
    protected $fillable = [
        'wiki_id',
        'wiki_comment_id',
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

    public function comment()
    {
        return $this->belongsTo(WikiComment::class, 'wiki_comment_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
