<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeAttachment extends Model
{
    protected $fillable = [
        'notice_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function notice()
    {
        return $this->belongsTo(Notice::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
