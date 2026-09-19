<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HolidayPayApproval;
use App\Models\HolidayWork;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The public holiday calendar.
 *
 * A once-a-year job, which is exactly why it is worth having here: somebody
 * remembers in a meeting that next year's dates were never entered, and the
 * alternative is a note-to-self that never gets actioned.
 *
 * Two things this calendar feeds, and both are why it has to be right:
 *
 *   * **Monthly staff** have holidays counted toward their worked days, so a
 *     missing holiday shortens their month and underpays them.
 *   * **Casual staff** earn double for an approved holiday they worked, through
 *     HolidayPayApproval and HolidayWork.
 *
 * Deletion is therefore guarded rather than free. Both of those tables cascade on
 * delete, so removing a holiday that has been used takes the pay decision and the
 * attendance record with it, silently — leaving a payslip that paid somebody
 * double for a day that no longer exists.
 */
class PublicHolidayApiController extends Controller
{
    /**
     * Uganda's fixed-date public holidays.
     *
     * Only the ones that fall on the same date every year. Good Friday, Easter
     * Monday and both Eids move, so they are entered by hand — guessing them
     * would put wrong dates into a calendar that decides pay.
     *
     * Archbishop Janani Luwum Day is deliberately absent: an existing migration
     * removes it from this table, so the company does not observe it and adding
     * it back here would undo that decision every January.
     *
     * @var array<string, string>
     */
    private const FIXED = [
        '01-01' => "New Year's Day",
        '01-26' => 'NRM Liberation Day',
        '03-08' => "International Women's Day",
        '05-01' => 'Labour Day',
        '06-03' => "Martyrs' Day",
        '06-09' => "National Heroes' Day",
        '10-09' => 'Independence Day',
        '12-25' => 'Christmas Day',
        '12-26' => 'Boxing Day',
    ];

    private function mayManage(Request $request): bool
    {
        return $request->user()->hasAnyRole(['super-admin', 'hr-admin']);
    }

    public function index(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        $holidays = PublicHoliday::where('year', $year)->orderBy('date')->get();

        $approvals = HolidayPayApproval::whereIn('public_holiday_id', $holidays->pluck('id'))
            ->get()->groupBy('public_holiday_id');

        $worked = HolidayWork::whereIn('public_holiday_id', $holidays->pluck('id'))
            ->where('worked', true)->get()->groupBy('public_holiday_id');

        return response()->json([
            'data' => $holidays->map(function (PublicHoliday $h) use ($approvals, $worked) {
                $inUse = ($approvals[$h->id] ?? collect())->isNotEmpty()
                    || ($worked[$h->id] ?? collect())->isNotEmpty();

                return [
                    'id' => $h->id,
                    'name' => $h->name,
                    'date' => $h->date?->toDateString(),
                    'day_of_week' => $h->date?->format('l'),
                    'type' => $h->type,
                    'is_paid' => (bool) $h->is_paid,
                    // A holiday landing on a Sunday changes nothing for most
                    // people, and saying so saves a question.
                    'falls_on_weekend' => $h->date?->isWeekend() ?? false,
                    // Whether it can still be removed, decided here so the app
                    // does not have to know which tables cascade.
                    'in_use' => $inUse,
                    'can_delete' => ! $inUse,
                ];
            })->values(),
            'year' => $year,
            'can_manage' => $this->mayManage($request),
            // What a fresh year would add, so the button can say how many.
            'missing_fixed' => $this->missingFixed($year)->count(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->mayManage($request), 403, 'Only HR or an administrator can change the holiday calendar.');

        $data = $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'type' => 'required|in:national,religious',
            'is_paid' => 'nullable|boolean',
        ]);

        $date = Carbon::parse($data['date']);

        // Keyed on the date, as the web is: two holidays on one day is a
        // duplicate rather than two events, and re-entering one corrects it.
        //
        // Found with whereDate rather than updateOrCreate's equality match. The
        // column is a real DATE in MySQL, so equality works there — but the
        // attribute is cast to `date` and SQLite has no date type, so the stored
        // value carries a time and the match silently fails, turning a correction
        // into an insert that hits the unique index. whereDate is right on both.
        $holiday = PublicHoliday::whereDate('date', $date->toDateString())
            ->where('country', 'UG')
            ->first() ?? new PublicHoliday;

        $holiday->fill([
            'date' => $date->toDateString(),
            'name' => $data['name'],
            'type' => $data['type'],
            'year' => $date->year,
            'is_paid' => $request->boolean('is_paid', true),
            'country' => 'UG',
        ])->save();

        return response()->json(['data' => ['id' => $holiday->id]], 201);
    }

    /**
     * Fill in a year's fixed-date holidays.
     *
     * The once-a-year job reduced to one tap. Idempotent: it adds only what is
     * missing, so pressing it twice changes nothing and pressing it on a
     * half-filled year completes it without disturbing what is there.
     */
    public function seedYear(Request $request)
    {
        abort_unless($this->mayManage($request), 403, 'Only HR or an administrator can change the holiday calendar.');

        $year = (int) $request->input('year', now()->year);

        if ($year < 2020 || $year > 2100) {
            return response()->json(['message' => 'That is not a year this calendar covers.'], 422);
        }

        $added = [];

        foreach ($this->missingFixed($year) as $date => $name) {
            PublicHoliday::create([
                'date' => $date,
                'name' => $name,
                'type' => 'national',
                'year' => $year,
                'is_paid' => true,
                'country' => 'UG',
            ]);

            $added[] = $name;
        }

        return response()->json([
            'data' => [
                'added' => count($added),
                'names' => $added,
                // Said plainly, because somebody who taps this and sees nine
                // holidays appear may reasonably think the year is now complete.
                'note' => 'Good Friday, Easter Monday and both Eids move each year and must be added by hand.',
            ],
        ]);
    }

    public function destroy(Request $request, PublicHoliday $holiday)
    {
        abort_unless($this->mayManage($request), 403, 'Only HR or an administrator can change the holiday calendar.');

        $approvals = HolidayPayApproval::where('public_holiday_id', $holiday->id)->count();
        $worked = HolidayWork::where('public_holiday_id', $holiday->id)->count();

        if ($approvals || $worked) {
            return response()->json([
                'message' => sprintf(
                    '%s carries %d pay decision(s) and %d attendance record(s). Deleting it would '
                    .'erase them and leave the payslips that used it unexplainable.',
                    $holiday->name, $approvals, $worked,
                ),
            ], 422);
        }

        $holiday->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * The fixed holidays a year does not have yet, as date => name.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function missingFixed(int $year): \Illuminate\Support\Collection
    {
        // Normalised to Y-m-d before comparing, for the same reason: what comes
        // back carries a time on SQLite and does not on MySQL.
        $existing = PublicHoliday::where('year', $year)->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        return collect(self::FIXED)
            ->mapWithKeys(fn (string $name, string $md) => ["{$year}-{$md}" => $name])
            ->reject(fn (string $name, string $date) => in_array($date, $existing, true));
    }
}
