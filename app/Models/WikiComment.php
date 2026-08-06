<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WikiComment extends Model
{
    protected $fillable = [
        'wiki_id',
        'parent_id',
        'user_id',
        'body',
    ];

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

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

    /** @return HasMany<WikiAttachment, $this> */
    public function attachments()
    {
        return $this->hasMany(WikiAttachment::class, 'wiki_comment_id');
    }
}
