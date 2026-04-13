<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PerformanceCycle extends Model
{
    protected $fillable = ['name', 'year', 'start_date', 'end_date', 'status'];
    public function reviews() { return $this->hasMany(PerformanceReview::class, 'cycle_id'); }
}