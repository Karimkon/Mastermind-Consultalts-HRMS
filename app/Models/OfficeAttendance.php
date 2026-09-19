<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * A day's office clock in/out for one staff user.
 *
 * Never read by PayrollService — this is a presence register, not a pay input.
 * See the migration for why it is keyed on user_id rather than employee_id.
 */
class OfficeAttendance extends Model
{
    protected $table = 'office_attendance';

    protected $fillable = [
        'user_id', 'date', 'clock_in', 'clock_out',
        'clock_in_lat', 'clock_in_lng', 'clock_out_lat', 'clock_out_lng',
        'clock_in_distance_m', 'clock_out_distance_m',
        'clock_in_offsite', 'clock_out_offsite', 'note',
    ];

    protected $casts = [
        'date'              => 'date',
        'clock_in'          => 'datetime',
        'clock_out'         => 'datetime',
        'clock_in_lat'      => 'float',
        'clock_in_lng'      => 'float',
        'clock_out_lat'     => 'float',
        'clock_out_lng'     => 'float',
        'clock_in_offsite'  => 'boolean',
        'clock_out_offsite' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }

    /** Hours between the two taps, or null while still clocked in. */
    public function getHoursAttribute(): ?float
    {
        if (!$this->clock_in || !$this->clock_out) return null;

        return round(Carbon::parse($this->clock_in)->diffInMinutes(Carbon::parse($this->clock_out)) / 60, 2);
    }

    /** Still clocked in with no clock-out recorded. */
    public function getIsOpenAttribute(): bool
    {
        return $this->clock_in !== null && $this->clock_out === null;
    }

    public function getStatusLabelAttribute(): string
    {
        if (!$this->clock_in)  return 'Not clocked in';
        if (!$this->clock_out) return 'Still in';
        return 'Complete';
    }
}
