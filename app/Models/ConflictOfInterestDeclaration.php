<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConflictOfInterestDeclaration extends Model
{
    protected $primaryKey = 'coi_id';

    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'declared_by',
        'description',
        'declared_at',
    ];

    protected $casts = [
        'declared_at' => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_id', 'research_id');
    }

    public function declaredBy()
    {
        return $this->belongsTo(User::class, 'declared_by', 'user_id');
    }
}
