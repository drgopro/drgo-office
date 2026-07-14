<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WikiComment extends Model
{
    protected $fillable = [
        'wiki_id',
        'user_id',
        'body',
    ];

    /** @return BelongsTo<Wiki, $this> */
    public function wiki(): BelongsTo
    {
        return $this->belongsTo(Wiki::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
