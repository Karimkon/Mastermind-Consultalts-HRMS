<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BscKra extends Model
{
    protected $fillable = [
        'cycle_id', 'perspective', 'kra_name', 'objective', 'measure',
        'target', 'unit', 'weightage', 'review_frequency', 'sort_order',
    ];

    protected $casts = [
        'target'     => 'float',
        'weightage'  => 'float',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function cycle()
    {
        return $this->belongsTo(BscCycle::class, 'cycle_id');
    }

    public function entries()
    {
        return $this->hasMany(BscEntry::class, 'kra_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getPerspectiveLabelAttribute(): string
    {
        return match ($this->perspective) {
            'financial'        => 'Financial',
            'customer'         => 'Customer',
            'internal_process' => 'Internal Business Process',
            'learning_growth'  => 'Learning & Growth',
            default            => ucfirst($this->perspective),
        };
    }

    public function getPerspectiveColorAttribute(): string
    {
        return match ($this->perspective) {
            'financial'        => 'blue',
            'customer'         => 'green',
            'internal_process' => 'purple',
            'learning_growth'  => 'orange',
            default            => 'gray',
        };
    }
}
