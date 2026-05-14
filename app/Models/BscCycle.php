<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BscCycle extends Model
{
    protected $fillable = [
        'name', 'year', 'period', 'start_date', 'end_date', 'status', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'year'       => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function kras()
    {
        return $this->hasMany(BscKra::class, 'cycle_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Fixed perspective weights per BSC framework.
     */
    public function getPerspectiveWeightsAttribute(): array
    {
        return [
            'financial'        => 15,
            'customer'         => 15,
            'internal_process' => 40,
            'learning_growth'  => 30,
        ];
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active' => '<span class="badge-green">Active</span>',
            'closed' => '<span class="badge-gray">Closed</span>',
            default  => '<span class="badge-yellow">Draft</span>',
        };
    }
}
