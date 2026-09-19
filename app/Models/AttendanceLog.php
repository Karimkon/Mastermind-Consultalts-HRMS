<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'clock_in', 'clock_out', 'status',
        'overtime_hours', 'lat', 'lng', 'note', 'approved_by',
        'client_id', 'clock_out_lat', 'clock_out_lng', 'distance_metres',
        'approved_overtime_hours', 'overtime_status',
        'overtime_approved_by', 'overtime_approved_at', 'overtime_note',
    ];

    protected $casts = [
        'date'                    => 'date',
        'clock_in'                => 'datetime',
        'clock_out'               => 'datetime',
        'distance_metres'         => 'float',
        'clock_out_lat'           => 'float',
        'clock_out_lng'           => 'float',
        'overtime_hours'          => 'float',
        'approved_overtime_hours' => 'float',
        'overtime_approved_at'    => 'datetime',
    ];

    /**
     * Company overtime policy: 2 hours a day, across 6 days, so 12 a week.
     * These are shown as warnings — HR can still approve more, deliberately.
     */
    public const DAILY_OVERTIME_CAP  = 2;
    public const WEEKLY_OVERTIME_CAP = 12;

    public function employee()         { return $this->belongsTo(Employee::class); }
    public function client()           { return $this->belongsTo(Client::class); }
    public function overtimeApprover() { return $this->belongsTo(User::class, 'overtime_approved_by'); }

    /** Overtime was recorded but nobody has decided on it yet. */
    public function scopeAwaitingOvertimeApproval($q)
    {
        return $q->where('overtime_hours', '>', 0)->where('overtime_status', 'pending');
    }

    /** Hours payroll is allowed to pay — zero until someone approves. */
    public function payableOvertime(): float
    {
        return $this->overtime_status === 'approved' ? (float) $this->approved_overtime_hours : 0.0;
    }

    public function exceedsDailyCap(): bool
    {
        return (float) $this->overtime_hours > self::DAILY_OVERTIME_CAP;
    }
}
