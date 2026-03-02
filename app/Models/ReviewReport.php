<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReport extends Model
{
    protected $primaryKey = 'report_id';

    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'reviewer_id',
        'summary',
        'major_issues',
        'minor_issues',
        'recommendation',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_id', 'research_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'user_id');
    }

    public function forumDiscussion()
    {
        return $this->hasOne(ForumDiscussion::class, 'referenced_report_id', 'report_id');
    }
}
