<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    // `type` removed: `performance_reviews` has no such column anywhere, and a
    // fillable that names a missing column is discarded without error on save.
    protected $fillable = ['employee_id', 'reviewer_id', 'cycle_id', 'ratings', 'comments', 'total_score'];
    protected $casts    = ['ratings' => 'array'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(Employee::class, 'reviewer_id'); }
    public function cycle()    { return $this->belongsTo(PerformanceCycle::class); }
}