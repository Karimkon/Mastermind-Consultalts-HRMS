<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityStandard extends Model
{
    protected $fillable = [
        'code', 'title', 'description', 'hr_function', 'category',
        'severity', 'weight', 'target_score', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weight' => 'integer',
        'target_score' => 'integer',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(QualityCheck::class);
    }
}
