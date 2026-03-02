<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'institution',
        'expertise_areas',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'must_change_password' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // ── JWT ────────────────────────────────────────────────────────
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
        ];
    }

    // ── Role helpers ────────────────────────────────────────────────
    public function isAuthor(): bool
    {
        return $this->role === 'author';
    }

    public function isReviewer(): bool
    {
        return $this->role === 'reviewer';
    }

    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    public function isEic(): bool
    {
        return $this->role === 'eic';
    }

    // ── Relationships ────────────────────────────────────────────────
    public function researchSubmissions()
    {
        return $this->hasMany(ResearchSubmission::class, 'author_id', 'user_id');
    }

    public function editorAssignments()
    {
        return $this->hasMany(EditorAssignment::class, 'editor_id', 'user_id');
    }

    public function reviewerAssignments()
    {
        return $this->hasMany(ReviewerAssignment::class, 'reviewer_id', 'user_id');
    }

    public function reviewReports()
    {
        return $this->hasMany(ReviewReport::class, 'reviewer_id', 'user_id');
    }

    public function annotations()
    {
        return $this->hasMany(Annotation::class, 'reviewer_id', 'user_id');
    }

    public function forumReplies()
    {
        return $this->hasMany(ForumReply::class, 'user_id', 'user_id');
    }

    public function conflictOfInterestDeclarations()
    {
        return $this->hasMany(ConflictOfInterestDeclaration::class, 'declared_by', 'user_id');
    }
}
