<?php
namespace App\Http\Controllers;

use App\Models\{Client, Employee, HolidayPayApproval, HolidayWork, PublicHoliday};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Public holiday pay: decide whether each holiday is paid, and record who
 * actually worked it.
 *
 * Two separate acts, deliberately:
 *   - HR / Admin approve or reject paying a holiday for a client. Until that
 *     happens nobody is paid for merely staying home.
 *   - The account manager who runs the site ticks which of their employees
 *     worked it. Those are paid double once the holiday is approved.
 */
class HolidayPayController extends Controller
{
    private const APPROVER_ROLES = ['super-admin', 'hr-admin', 'manager'];

    private function canApprove(): bool
    {
        return auth()->user()->hasAnyRole(self::APPROVER_ROLES);
    }

    /** Clients the signed-in user may act for; null means all. */
    private function scopedClientIds(): ?\Illuminate\Support\Collection
    {
        $user = auth()->user();
        if ($user->hasAnyRole(self::APPROVER_ROLES)) return null;
        if ($user->hasRole('account-manager')) {
            return Client::where('account_manager_id', $user->id)->pluck('id');
        }
        return collect();
    }

    public function index(Request $request)
    {
        $user      = auth()->user();
        $clientIds = $this->scopedClientIds();
        abort_if($clientIds !== null && $clientIds->isEmpty(), 403,
            'You do not manage any client sites.');

        $year  = (int) ($request->year ?: now()->year);
        $month = $request->filled('month') ? (int) $request->month : null;

        $clients = Client::when($clientIds !== null, fn($q) => $q->whereIn('id', $clientIds))
            ->orderBy('company_name')->get();

        $clientId = $request->client_id
            ? (int) $request->client_id
            : ($clientIds !== null ? $clientIds->first() : null);

        $holidays = PublicHoliday::where('country', 'UG')->where('year', $year)
            ->when($month, fn($q) => $q->whereMonth('date', $month))
            ->orderBy('date')->get();

        // Existing decisions and worked-counts, fetched once for the whole page.
        $approvals = HolidayPayApproval::whereIn('public_holiday_id', $holidays->pluck('id'))
            ->where(fn($q) => $q->where('client_id', $clientId)->orWhereNull('client_id'))
            ->with('decider')->get()->keyBy('public_holiday_id');

        $workedCounts = HolidayWork::whereIn('public_holiday_id', $holidays->pluck('id'))
            ->where('worked', true)
            ->when($clientId, fn($q) => $q->whereHas('employee.clients',
                fn($c) => $c->where('clients.id', $clientId)))
            ->selectRaw('public_holiday_id, COUNT(*) c')
            ->groupBy('public_holiday_id')->pluck('c', 'public_holiday_id');

        return view('holiday-pay.index', [
            'holidays'     => $holidays,
            'approvals'    => $approvals,
            'workedCounts' => $workedCounts,
            'clients'      => $clients,
            'clientId'     => $clientId,
            'year'         => $year,
            'month'        => $month,
            'canApprove'   => $this->canApprove(),
            // Anything already past with no decision is what the prompt nags about.
            'awaiting'     => $this->awaitingDecision($clientId),
        ]);
    }

    /** Holidays that have already happened and still have no decision. */
    public static function awaitingDecision(?int $clientId): \Illuminate\Support\Collection
    {
        return PublicHoliday::where('country', 'UG')
            ->whereDate('date', '<=', now()->toDateString())
            ->whereDate('date', '>=', now()->subMonths(3)->toDateString())
            ->orderByDesc('date')->get()
            ->reject(fn($h) => HolidayPayApproval::where('public_holiday_id', $h->id)
                ->where(fn($q) => $q->where('client_id', $clientId)->orWhereNull('client_id'))
                ->whereIn('status', ['approved', 'rejected'])->exists())
            ->values();
    }

    /** Approve or reject paying one holiday for one client. */
    public function decide(Request $request, PublicHoliday $holiday)
    {
        abort_unless($this->canApprove(), 403, 'Only HR or an administrator can decide holiday pay.');

        $data = $request->validate([
            'status'    => 'required|in:approved,rejected',
            'client_id' => 'nullable|exists:clients,id',
            'note'      => 'nullable|string|max:255',
        ]);

        HolidayPayApproval::updateOrCreate(
            ['public_holiday_id' => $holiday->id, 'client_id' => $data['client_id'] ?: null],
            [
                'status'     => $data['status'],
                'decided_by' => auth()->id(),
                'decided_at' => now(),
                'note'       => $data['note'] ?? null,
            ]
        );

        $verb = $data['status'] === 'approved' ? 'approved for payment' : 'rejected';
        return back()->with('success', "{$holiday->name} {$verb}.");
    }

    /** Screen listing a client's employees so the AM can tick who worked. */
    public function work(Request $request, PublicHoliday $holiday)
    {
        $clientIds = $this->scopedClientIds();
        $clientId  = (int) $request->client_id;
        abort_if(!$clientId, 400, 'Pick a client first.');
        abort_if($clientIds !== null && !$clientIds->contains($clientId), 403);

        $client = Client::findOrFail($clientId);

        // Only casual rates can earn holiday pay — a monthly salary already
        // covers the day, so listing them here would only invite mistakes.
        $employees = $client->employees()
            ->whereIn('employees.status', ['active', 'on_leave'])
            ->with(['department', 'salary'])
            ->orderBy('first_name')->get()
            ->filter(fn($e) => in_array($e->salary?->salary_type ?? 'monthly', ['daily', 'hourly'], true))
            ->values();

        $workedIds = HolidayWork::where('public_holiday_id', $holiday->id)
            ->where('worked', true)->pluck('employee_id')->all();

        return view('holiday-pay.work', compact('holiday', 'client', 'employees', 'workedIds'));
    }

    /** Save which employees worked the holiday. */
    public function storeWork(Request $request, PublicHoliday $holiday)
    {
        $clientIds = $this->scopedClientIds();
        $clientId  = (int) $request->client_id;
        abort_if($clientIds !== null && !$clientIds->contains($clientId), 403);

        $client   = Client::findOrFail($clientId);
        $selected = collect($request->input('employee_ids', []))->map(fn($id) => (int) $id);

        // Scope the wipe to this client so saving one site never clears another.
        $clientEmployeeIds = $client->employees()->pluck('employees.id');

        HolidayWork::where('public_holiday_id', $holiday->id)
            ->whereIn('employee_id', $clientEmployeeIds)->delete();

        foreach ($selected->intersect($clientEmployeeIds) as $employeeId) {
            HolidayWork::create([
                'public_holiday_id' => $holiday->id,
                'employee_id'       => $employeeId,
                'worked'            => true,
                'recorded_by'       => auth()->id(),
            ]);
        }

        $count = $selected->intersect($clientEmployeeIds)->count();
        return redirect()
            ->route('holiday-pay.index', ['client_id' => $clientId, 'year' => $holiday->year])
            ->with('success', "{$count} employee(s) recorded as having worked {$holiday->name}.");
    }
}
