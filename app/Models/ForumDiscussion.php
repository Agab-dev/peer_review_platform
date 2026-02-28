<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumDiscussion extends Model
{
    protected $primaryKey = 'discussion_id';
    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'discussion_type',
        'referenced_annotation_id',
        'referenced_report_id',
        'title',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_id', 'research_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function annotation()
    {
        return $this->belongsTo(Annotation::class, 'referenced_annotation_id', 'annotation_id');
    }

    public function report()
    {
        return $this->belongsTo(ReviewReport::class, 'referenced_report_id', 'report_id');
    }

    public function replies()
    {
        return $this->hasMany(ForumReply::class, 'discussion_id', 'discussion_id');
    }

    public function activeReplies()
    {
        return $this->hasMany(ForumReply::class, 'discussion_id', 'discussion_id')
            ->whereNull('deleted_at');
    }
}
