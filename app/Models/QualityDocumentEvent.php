<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityDocumentEvent extends Model
{
    protected $fillable = ['quality_document_id', 'action', 'by_user_id', 'note'];

    public function document(): BelongsTo { return $this->belongsTo(QualityDocument::class, 'quality_document_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'by_user_id'); }
}
