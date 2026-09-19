<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records that one employee actually worked on one public holiday.
 *
 * Ticked in the system by the account manager who runs that client site, so
 * nothing has to be exported or re-imported. Payroll pays these at double the
 * daily rate when the holiday was approved, and at the normal rate — flagged —
 * when it was not.
 */
class HolidayWork extends Model
{
    protected $table = 'holiday_work';

    protected $fillable = ['public_holiday_id', 'employee_id', 'worked', 'recorded_by', 'note'];

    protected $casts = ['worked' => 'boolean'];

    public function holiday()  { return $this->belongsTo(PublicHoliday::class, 'public_holiday_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }

    /**
     * Employee ids that worked any of the given holidays, keyed by holiday id.
     * One query for the whole payroll run rather than one per employee.
     */
    public static function workedMap(array $holidayIds): array
    {
        if (!$holidayIds) return [];

        return static::whereIn('public_holiday_id', $holidayIds)
            ->where('worked', true)
            ->get(['public_holiday_id', 'employee_id'])
            ->groupBy('public_holiday_id')
            ->map(fn ($g) => $g->pluck('employee_id')->all())
            ->all();
    }
}
