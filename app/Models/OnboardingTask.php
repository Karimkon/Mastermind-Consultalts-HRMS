<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OnboardingTask extends Model
{
    protected $fillable = ['employee_id','task','description','sort_order','completed_at','completed_by'];
    protected $casts    = ['completed_at' => 'datetime'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }

    public function getIsCompletedAttribute(): bool { return !is_null($this->completed_at); }
}
