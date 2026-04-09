<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientDocument extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'client_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'note',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
