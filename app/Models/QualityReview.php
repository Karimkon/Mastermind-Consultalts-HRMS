<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityReview extends Model
{
    protected $fillable = [
        'reference', 'title', 'period_start', 'period_end', 'status',
        'chaired_by', 'overall_score', 'summary', 'decisions', 'held_on',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'held_on' => 'date',
        'overall_score' => 'decimal:2',
    ];

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chaired_by');
    }

    public static function nextReference(): string
    {
        $n = (int) static::max('id') + 1;
        return 'QR-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
