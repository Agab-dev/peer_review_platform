<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewerAssignment extends Model
{
    protected $primaryKey = 'assignment_id';
    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'reviewer_id',
        'assigned_at',
        'deleted_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_id', 'research_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'user_id');
    }
}
