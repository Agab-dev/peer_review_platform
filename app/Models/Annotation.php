<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Annotation extends Model
{
    protected $primaryKey = 'annotation_id';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'reviewer_id',
        'text_range_start',
        'text_range_end',
        'comment',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(DocumentVersion::class, 'document_id', 'document_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'user_id');
    }

    public function forumDiscussion()
    {
        return $this->hasOne(ForumDiscussion::class, 'referenced_annotation_id', 'annotation_id');
    }
}
