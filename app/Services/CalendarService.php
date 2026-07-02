<?php
namespace App\Services;

use App\Models\PublicHoliday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CalendarService
{
    /**
     * Get public holidays for a given payroll month.
     */
    public function holidaysInMonth(int $year, int $month): \Illuminate\Support\Collection
    {
        return PublicHoliday::forMonth($year, $month);
    }

    /**
     * Calendar days in the month.
     */
    public function calendarDaysInMonth(int $year, int $month): int
    {
        return Carbon::create($year, $month, 1)->daysInMonth;
    }

    /**
     * Weekdays (Mon–Fri) in the month, excluding public holidays that fall on weekdays.
     * NOT used for pro-rata (which uses calendar days), but useful for reporting.
     */
    public function workingDaysInMonth(int $year, int $month): int
    {
        $start  = Carbon::create($year, $month, 1);
        $end    = $start->copy()->endOfMonth();
        $count  = 0;
        $period = CarbonPeriod::create($start, $end);
        foreach ($period as $day) {
            if (!$day->isWeekend()) $count++;
        }
        return $count;
    }

    /**
     * Pro-rata daily rate: Annual salary ÷ 365
     */
    public function dailyRate(float $annualSalary): float
    {
        return $annualSalary / 365;
    }

    /**
     * Compute gross pay for given days worked (includes public holidays).
     * Formula: (annual ÷ 365) × days_worked
     */
    public function proRataGross(float $annualSalary, int $daysWorked): float
    {
        return round(($annualSalary / 365) * $daysWorked, 0);
    }

    /**
     * For auto attendance-based runs: count attendace logs as present,
     * then add public holidays that fall on days NOT already recorded as present.
     * Returns [worked_days, holiday_bonus_days, effective_days].
     */
    public function effectiveDaysWorked(
        int $employeeId,
        int $year,
        int $month,
        int $baseWorkedDays
    ): array {
        $start = Carbon::create($year, $month, 1)->toDateString();
        $end   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // Public holiday dates in this month
        $holidayDates = PublicHoliday::datesInRange($start, $end);
        $bonusDays    = count($holidayDates); // all holidays paid by default

        return [
            'worked_days'        => $baseWorkedDays,
            'holiday_bonus_days' => $bonusDays,
            'effective_days'     => $baseWorkedDays + $bonusDays,
            'holiday_dates'      => $holidayDates,
        ];
    }

    /**
     * Get a full calendar summary for a payroll month.
     */
    public function monthSummary(int $year, int $month): array
    {
        $holidays    = $this->holidaysInMonth($year, $month);
        $calDays     = $this->calendarDaysInMonth($year, $month);
        $workingDays = $this->workingDaysInMonth($year, $month);

        return [
            'calendar_days'   => $calDays,
            'working_days'    => $workingDays,
            'holiday_count'   => $holidays->count(),
            'paid_holidays'   => $holidays->where('is_paid', true)->count(),
            'holidays'        => $holidays,
            'daily_divisor'   => 365,
        ];
    }
}
