<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $primaryKey = 'document_id';
    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'version_number',
        'pdf_file_path',
        'html_content',
        'html_ready',
        'uploaded_at',
    ];

    protected $casts = [
        'html_ready'  => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_id', 'research_id');
    }

    public function annotations()
    {
        return $this->hasMany(Annotation::class, 'document_id', 'document_id');
    }
}
