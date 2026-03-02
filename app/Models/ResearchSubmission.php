<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchSubmission extends Model
{
    use HasFactory;

    protected $primaryKey = 'research_id';

    public $timestamps = false;

    protected $fillable = [
        'author_id',
        'title',
        'research_field',
        'status',
        'review_phase',
        'anonymization_model',
        'deadline',
        'submitted_at',
        'accepted_at',
    ];

    protected $casts = [
        'deadline' => 'date',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    // ── Status / phase helpers ──────────────────────────────────────
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isIndependent(): bool
    {
        return $this->review_phase === 'independent';
    }

    public function isInteractive(): bool
    {
        return $this->review_phase === 'interactive';
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['accepted', 'rejected']);
    }

    // ── Relationships ────────────────────────────────────────────────
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'user_id');
    }

    public function documentVersions()
    {
        return $this->hasMany(DocumentVersion::class, 'research_id', 'research_id');
    }

    public function latestDocument()
    {
        return $this->hasOne(DocumentVersion::class, 'research_id', 'research_id')
            ->orderByDesc('version_number');
    }

    public function editorAssignments()
    {
        return $this->hasMany(EditorAssignment::class, 'research_id', 'research_id');
    }

    public function activeEditorAssignments()
    {
        return $this->hasMany(EditorAssignment::class, 'research_id', 'research_id')
            ->whereNull('deleted_at');
    }

    public function primaryEditorAssignment()
    {
        return $this->hasOne(EditorAssignment::class, 'research_id', 'research_id')
            ->whereNull('deleted_at')
            ->where('is_primary', true);
    }

    public function reviewerAssignments()
    {
        return $this->hasMany(ReviewerAssignment::class, 'research_id', 'research_id');
    }

    public function activeReviewerAssignments()
    {
        return $this->hasMany(ReviewerAssignment::class, 'research_id', 'research_id')
            ->whereNull('deleted_at');
    }

    public function reviewReports()
    {
        return $this->hasMany(ReviewReport::class, 'research_id', 'research_id');
    }

    public function forumDiscussions()
    {
        return $this->hasMany(ForumDiscussion::class, 'research_id', 'research_id');
    }

    public function conflictOfInterestDeclarations()
    {
        return $this->hasMany(ConflictOfInterestDeclaration::class, 'research_id', 'research_id');
    }
}
