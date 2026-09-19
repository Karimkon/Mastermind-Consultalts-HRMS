<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\HolidayPayApproval;
use App\Models\HolidayWork;
use App\Models\PublicHoliday;
use Illuminate\Http\Request;

/**
 * Public holiday pay.
 *
 * Two separate decisions, made by two different people, and keeping them apart is
 * the point of the module:
 *
 *   1. **Is this holiday paid at all, for this client?** HR decides, once.
 *   2. **Who actually worked it?** The account manager marks that off, per person.
 *
 * Payroll pays double only where both are true. An employee marked as having
 * worked a holiday nobody approved is still paid — at the normal rate, with the
 * gap surfaced on the payslip, so a missed decision shows up as a discrepancy
 * rather than as silence.
 *
 * Only casual rates appear here. A monthly salary already covers the day, so
 * listing monthly staff would invite a mistake that costs real money.
 */
class HolidayPayApiController extends Controller
{
    /** Holidays in a year, each with its decision and how many worked it. */
    public function index(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);

        $holidays = PublicHoliday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        $approvals = HolidayPayApproval::query()
            ->whereIn('public_holiday_id', $holidays->pluck('id'))
            ->get()
            ->groupBy('public_holiday_id');

        $worked = HolidayWork::query()
            ->whereIn('public_holiday_id', $holidays->pluck('id'))
            ->where('worked', true)
            ->get()
            ->groupBy('public_holiday_id');

        return response()->json([
            'data' => $holidays->map(fn (PublicHoliday $h) => [
                'id' => $h->id,
                'name' => $h->name,
                'date' => $h->date?->toDateString(),
                'is_past' => $h->date?->isPast() ?? false,
                // A holiday can be decided once for everybody (client_id null) or
                // per client, so the phone shows the count rather than pretending
                // there is a single answer.
                'decisions' => ($approvals[$h->id] ?? collect())->map(fn ($a) => [
                    'client_id' => $a->client_id,
                    'status' => $a->status,
                    'note' => $a->note,
                ])->values(),
                'worked_count' => ($worked[$h->id] ?? collect())->count(),
            ])->values(),
            'can_decide' => $this->canDecide($request),
        ]);
    }

    /** HR approves or rejects paying one holiday, for everybody or one client. */
    public function decide(Request $request, PublicHoliday $holiday)
    {
        abort_unless($this->canDecide($request), 403, 'Only HR or an administrator can decide holiday pay.');

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'client_id' => 'nullable|exists:clients,id',
            'note' => 'nullable|string|max:255',
        ]);

        HolidayPayApproval::updateOrCreate(
            // ?? not ?: — a `nullable` rule does not put the key in the
            // validated array at all when the field is absent, which a JSON
            // client will simply omit where a form posts an empty string.
            ['public_holiday_id' => $holiday->id, 'client_id' => ($data['client_id'] ?? null) ?: null],
            [
                'status' => $data['status'],
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'note' => $data['note'] ?? null,
            ]
        );

        return response()->json(['data' => ['status' => $data['status']]]);
    }

    /**
     * The people who could have worked a given holiday for a given client.
     *
     * Filtered to daily and hourly rates for the reason above.
     */
    public function roster(Request $request, PublicHoliday $holiday)
    {
        $clientId = (int) $request->query('client_id');

        if (! $clientId) {
            return response()->json(['message' => 'Pick a client first.'], 422);
        }

        $client = Client::findOrFail($clientId);

        $employees = $client->employees()
            ->whereIn('employees.status', ['active', 'on_leave'])
            ->with(['department', 'salary'])
            ->orderBy('first_name')
            ->get()
            ->filter(fn ($e) => in_array($e->salary?->salary_type ?? 'monthly', ['daily', 'hourly'], true))
            ->values();

        $workedIds = HolidayWork::where('public_holiday_id', $holiday->id)
            ->where('worked', true)
            ->pluck('employee_id')
            ->all();

        return response()->json([
            'data' => [
                'holiday' => ['id' => $holiday->id, 'name' => $holiday->name, 'date' => $holiday->date?->toDateString()],
                'client' => ['id' => $client->id, 'name' => $client->name],
                'employees' => $employees->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'department' => $e->department?->name,
                    'salary_type' => $e->salary?->salary_type,
                    'worked' => in_array($e->id, $workedIds, true),
                ])->values(),
            ],
        ]);
    }

    /**
     * Record who worked.
     *
     * The whole roster is sent, not just the ticked names, because "nobody
     * worked" and "nobody has said yet" are different facts and a list of ticks
     * alone cannot tell them apart.
     */
    public function storeWork(Request $request, PublicHoliday $holiday)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'employee_ids' => 'present|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'worked_ids' => 'present|array',
            'worked_ids.*' => 'integer|exists:employees,id',
        ]);

        foreach ($data['employee_ids'] as $employeeId) {
            HolidayWork::updateOrCreate(
                ['public_holiday_id' => $holiday->id, 'employee_id' => $employeeId],
                [
                    'worked' => in_array($employeeId, $data['worked_ids'], true),
                    'recorded_by' => $request->user()->id,
                ]
            );
        }

        return response()->json([
            'data' => ['worked' => count($data['worked_ids']), 'of' => count($data['employee_ids'])],
        ]);
    }

    private function canDecide(Request $request): bool
    {
        return $request->user()->hasAnyRole(['super-admin', 'hr-admin', 'md']);
    }
}
