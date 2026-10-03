<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    /** A fence was configured and the fix was inside it. */
    public const LOCATION_VERIFIED = 'verified';

    /** A fence was configured and the fix was outside it. */
    public const LOCATION_OUTSIDE = 'outside';

    /** The client has no coordinates, so nothing could be checked. */
    public const LOCATION_UNFENCED = 'unfenced';

    /** The device supplied no location at all. */
    public const LOCATION_NO_FIX = 'no_fix';

    /**
     * Whether this row's location means anything.
     *
     * Only `verified` is evidence of where somebody was. The other three are the
     * different ways the check could not be made, and a report that treats them
     * as the same thing is reporting a control that does not exist.
     */
    public function locationWasChecked(): bool
    {
        return in_array($this->location_status, [self::LOCATION_VERIFIED, self::LOCATION_OUTSIDE], true);
    }

    public function locationLabel(): string
    {
        return match ($this->location_status) {
            self::LOCATION_VERIFIED => 'On site',
            self::LOCATION_OUTSIDE => 'Away from site',
            self::LOCATION_UNFENCED => 'Not verified — no work site set',
            self::LOCATION_NO_FIX => 'Not verified — no GPS',
            default => 'Not recorded',
        };
    }

    protected $fillable = [
        'employee_id', 'date', 'clock_in', 'clock_out', 'status', 'location_status',
        'overtime_hours', 'lat', 'lng', 'note', 'approved_by',
        'client_id', 'verified_at_client_id', 'clock_out_lat', 'clock_out_lng', 'distance_metres',
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
