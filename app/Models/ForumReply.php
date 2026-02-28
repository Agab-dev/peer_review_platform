<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumReply extends Model
{
    protected $primaryKey = 'reply_id';
    public $timestamps = false;

    protected $fillable = [
        'discussion_id',
        'user_id',
        'content',
        'created_at',
        'deleted_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function discussion()
    {
        return $this->belongsTo(ForumDiscussion::class, 'discussion_id', 'discussion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
