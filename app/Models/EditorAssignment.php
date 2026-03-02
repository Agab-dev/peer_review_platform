<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditorAssignment extends Model
{
    protected $primaryKey = 'assignment_id';

    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'editor_id',
        'is_primary',
        'assigned_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'assigned_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_id', 'research_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id', 'user_id');
    }
}
