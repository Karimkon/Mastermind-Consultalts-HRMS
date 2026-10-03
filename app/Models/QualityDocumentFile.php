<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityDocumentFile extends Model
{
    protected $fillable = [
        'quality_document_id', 'version', 'path', 'original_name', 'mime', 'size', 'uploaded_by', 'notes',
    ];

    public function document(): BelongsTo { return $this->belongsTo(QualityDocument::class, 'quality_document_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
