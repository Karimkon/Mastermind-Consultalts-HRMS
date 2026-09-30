<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One file of evidence attached to a goal. */
class GoalAttachment extends Model
{
    protected $fillable = [
        'employee_goal_id', 'uploaded_by', 'note',
        'file_path', 'original_name', 'mime_type', 'size',
    ];

    protected $casts = ['size' => 'integer'];

    public function goal()     { return $this->belongsTo(EmployeeGoal::class, 'employee_goal_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->size;
        if ($bytes <= 0)      return '—';
        if ($bytes < 1024)    return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
