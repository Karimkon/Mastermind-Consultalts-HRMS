<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityCheck extends Model
{
    protected $fillable = [
        'quality_standard_id', 'engine_key', 'name', 'description', 'hr_function',
        'severity', 'weight', 'is_automated', 'auto_raise_nc', 'is_active', 'last_run_at',
    ];

    protected $casts = [
        'is_automated' => 'boolean',
        'auto_raise_nc' => 'boolean',
        'is_active' => 'boolean',
        'weight' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function standard(): BelongsTo
    {
        return $this->belongsTo(QualityStandard::class, 'quality_standard_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(QualityCheckResult::class);
    }

    public function nonconformities(): HasMany
    {
        return $this->hasMany(QualityNonconformity::class);
    }
}
