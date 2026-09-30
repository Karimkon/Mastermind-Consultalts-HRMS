<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One step on a rating scale: the score, what it is called, and the percentage
 * floor that lands an overall result in it.
 */
class RatingScaleBand extends Model
{
    protected $fillable = ['rating_scale_id', 'points', 'label', 'min_percent', 'range_label'];

    protected $casts = [
        'points'      => 'integer',
        'min_percent' => 'float',
    ];

    public function scale() { return $this->belongsTo(RatingScale::class, 'rating_scale_id'); }
}
