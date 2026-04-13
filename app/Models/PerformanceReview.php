<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    protected $fillable = ['employee_id', 'reviewer_id', 'cycle_id', 'type', 'ratings', 'comments', 'total_score'];
    protected $casts    = ['ratings' => 'array'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(Employee::class, 'reviewer_id'); }
    public function cycle()    { return $this->belongsTo(PerformanceCycle::class); }
}