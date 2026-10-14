<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ClientsExport;
use App\Http\Controllers\Controller;
use App\Imports\ClientsImport;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeClientTransfer;
use App\Models\HolidayPayApproval;
use App\Models\HolidayWork;
use App\Models\JobPosting;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\RoleAssignments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class AdminClientController extends Controller
{
    public function index()
    {
        $clients = Client::with('user')
            ->withCount(['employees', 'jobPostings'])
            ->orderBy('company_name')
            ->paginate(20);

        return view('admin.clients.index', compact('clients'));
    }

    public function create()
    {
        return view('admin.clients.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'industry' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->contact_person,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);
        $user->syncRoles('client');

        $client = Client::create([
            'user_id' => $user->id,
            'company_name' => $request->company_name,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'email' => $request->client_email,
            'industry' => $request->industry,
            'address' => $request->address,
            'deployment_area' => $request->deployment_area,
            'work_area' => $request->work_area,
            'status' => 'active',
            'notes' => $request->notes,
        ]);

        return redirect()->route('admin.clients.show', $client)
            ->with('success', "Client '{$client->company_name}' created successfully.");
    }

    public function show(Client $client)
    {
        $client->load(['user', 'employees.user', 'employees.department', 'jobPostings.department']);

        $availableEmployees = Employee::with('user')
            ->whereNotIn('id', $client->employees()->pluck('employees.id'))
            ->where('status', 'active')
            ->get();

        $availableJobs = JobPosting::whereNotIn('id', $client->jobPostings()->pluck('job_postings.id'))
            ->where('status', 'open')
            ->get();

        return view('admin.clients.show', compact('client', 'availableEmployees', 'availableJobs'));
    }

    public function edit(Client $client)
    {
        return view('admin.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string',
            'payment_day' => 'nullable|integer|min:1|max:31',
            'work_site_address' => 'nullable|string|max:255',
            'work_site_lat' => 'nullable|numeric|between:-90,90',
            'work_site_lng' => 'nullable|numeric|between:-180,180',
            'geo_fence_radius' => 'nullable|integer|min:10|max:5000',
        ]);

        $client->update($request->only(
            'company_name', 'contact_person', 'phone', 'industry', 'address',
            'deployment_area', 'work_area', 'status', 'notes',
            'payment_day', 'work_site_address', 'work_site_lat', 'work_site_lng', 'geo_fence_radius'
        ));
        // Checkbox: paired with a hidden 0 in the form so unticking is recorded.
        $client->update(['attendance_enabled' => $request->boolean('attendance_enabled')]);

        if ($request->filled('client_email')) {
            $client->update(['email' => $request->client_email]);
        }

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function assignEmployee(Request $request, Client $client)
    {
        $request->validate(['employee_id' => 'required|exists:employees,id']);

        $client->employees()->syncWithoutDetaching([
            $request->employee_id => ['assigned_by' => auth()->id(), 'notes' => $request->notes],
        ]);

        // Record transfer history
        EmployeeClientTransfer::create([
            'employee_id' => $request->employee_id,
            'client_id' => $client->id,
            'type' => 'assignment',
            'effective_date' => now()->toDateString(),
            'reason' => $request->notes ?: 'Assigned to client',
            'recorded_by' => auth()->id(),
        ]);

        // Mark future leave requests from this employee as needing client approval
        LeaveRequest::where('employee_id', $request->employee_id)
            ->where('status', 'pending')
            ->update(['client_approval_required' => true, 'client_approval_status' => 'pending']);

        return back()->with('success', 'Employee assigned to client.');
    }

    public function unassignEmployee(Client $client, Employee $employee)
    {
        $client->employees()->detach($employee->id);
        EmployeeClientTransfer::create([
            'employee_id' => $employee->id,
            'client_id' => $client->id,
            'type' => 'removal',
            'effective_date' => now()->toDateString(),
            'reason' => 'Removed from client',
            'recorded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Employee removed from client.');
    }

    public function assignJob(Request $request, Client $client)
    {
        $request->validate(['job_posting_id' => 'required|exists:job_postings,id']);

        $client->jobPostings()->syncWithoutDetaching([
            $request->job_posting_id => ['assigned_by' => auth()->id(), 'notes' => $request->notes],
        ]);

        return back()->with('success', 'Job posting assigned to client.');
    }

    public function unassignJob(Client $client, JobPosting $jobPosting)
    {
        $client->jobPostings()->detach($jobPosting->id);

        return back()->with('success', 'Job posting removed from client.');
    }

    public function accountManagers()
    {
        // All users with account-manager role
        $managers = User::role('account-manager')
            ->with(['managedClients' => function ($q) {
                $q->withCount('employees');
            }])
            ->orderBy('name')
            ->get();

        // All clients for the assignment dropdown, with the headcount that
        // would move with each one.
        $allClients = Client::with('accountManager:id,name')
            ->withCount('employees')
            ->orderBy('company_name')
            ->get();
        $allManagers = $managers;

        // Unassigned clients (no AM yet)
        $unassigned = Client::whereNull('account_manager_id')->orderBy('company_name')->get();

        // Anybody with a login who is NOT already an account manager, so an
        // existing member of staff can be promoted instead of having a second
        // account created for them. Duplicate logins are how one person ended
        // up holding a client under a name nobody signs into.
        $promotable = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'account-manager'))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.account-managers.index', compact(
            'managers', 'allClients', 'unassigned', 'promotable'
        ));
    }

    /**
     * Make an existing user an account manager.
     *
     * A role grant, not a new account. The Add button used to lead to the
     * user-create form, so the only way to appoint somebody who already had a
     * login was to give them a second one.
     */
    public function promoteAccountManager(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user = User::findOrFail($request->user_id);

        app(RoleAssignments::class)->grant(
            $user, 'account-manager', $request->input('reason') ?: 'Appointed from Account Managers'
        );

        return back()->with('success', $user->name.' is now an account manager.');
    }

    /**
     * Move every client, and therefore every employee, from one account
     * manager to another.
     *
     * The employees need no separate update: an account manager reaches staff
     * through their clients -- managedClients() then the client_employee
     * assignments -- so the client moving is the employees moving. Doing it in
     * one statement also means there is no window where a client belongs to
     * nobody.
     */
    public function transferClients(Request $request)
    {
        $data = $request->validate([
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id|different:from_user_id',
        ]);

        $from = User::findOrFail($data['from_user_id']);
        $to = User::findOrFail($data['to_user_id']);

        abort_unless($to->hasRole('account-manager'), 422,
            'Clients can only be transferred to another account manager.');

        $clientIds = Client::where('account_manager_id', $from->id)->pluck('id');

        if ($clientIds->isEmpty()) {
            return back()->with('error', $from->name.' has no clients to transfer.');
        }

        $staff = DB::table('client_employee_assignments')
            ->whereIn('client_id', $clientIds)
            ->distinct()
            ->count('employee_id');

        Client::whereIn('id', $clientIds)->update(['account_manager_id' => $to->id]);

        return back()->with('success', sprintf(
            '%d client(s) and %d employee(s) moved from %s to %s.',
            $clientIds->count(), $staff, $from->name, $to->name
        ));
    }

    /**
     * Stop somebody being an account manager.
     *
     * Their clients have to go somewhere first. Revoking the role on its own
     * would leave the clients pointing at a user who can no longer see them --
     * the staff would still be on the books, with nobody able to run their
     * payroll or action their leave, and nothing on screen to say why.
     *
     * The user account itself is untouched. People keep their login, and
     * history -- payroll runs processed, assignments made -- stays attached to
     * them.
     */
    public function removeAccountManager(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'transfer_to' => 'nullable|exists:users,id|different:user_id',
        ]);

        $user = User::findOrFail($data['user_id']);
        $clients = Client::where('account_manager_id', $user->id)->get();

        // ?? null, because a `nullable` rule leaves the key out of the
        // validated array entirely when the field is not submitted -- and an
        // empty successor box is the ordinary case, not the exotic one.
        $successorId = $data['transfer_to'] ?? null;

        if ($clients->isNotEmpty()) {
            if (! $successorId) {
                return back()->with('error', sprintf(
                    '%s still holds %d client(s). Choose who takes them over first.',
                    $user->name, $clients->count()
                ));
            }

            $to = User::findOrFail($successorId);

            abort_unless($to->hasRole('account-manager'), 422,
                'Clients can only be handed to another account manager.');

            Client::where('account_manager_id', $user->id)
                ->update(['account_manager_id' => $to->id]);
        }

        app(RoleAssignments::class)->revoke(
            $user, 'account-manager', $request->input('reason') ?: 'Stood down from Account Managers'
        );

        return back()->with('success', sprintf(
            '%s is no longer an account manager.%s',
            $user->name,
            $clients->isNotEmpty()
                ? ' Their '.$clients->count().' client(s) moved to '.User::find($successorId)->name.'.'
                : ''
        ));
    }

    // ── Client Export ─────────────────────────────────────────────────
    public function exportClients()
    {
        return Excel::download(new ClientsExport, 'clients_'.date('Y_m_d').'.xlsx');
    }

    // ── Client Import ─────────────────────────────────────────────────
    public function importClients(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
        Excel::import(new ClientsImport, $request->file('file'));

        return back()->with('success', 'Clients imported successfully.');
    }

    // ── Public Holidays Management ────────────────────────────────────
    public function publicHolidays(Request $request)
    {
        $year = (int) ($request->year ?? date('Y'));
        $holidays = PublicHoliday::where('year', $year)
            ->orderBy('date')
            ->get();

        $years = range(2024, 2035);

        return view('admin.public-holidays.index', compact('holidays', 'year', 'years'));
    }

    public function storePublicHoliday(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'type' => 'required|in:national,religious',
            'is_paid' => 'boolean',
        ]);

        $date = Carbon::parse($request->date);

        PublicHoliday::updateOrCreate(
            ['date' => $date->toDateString(), 'country' => 'UG'],
            [
                'name' => $request->name,
                'type' => $request->type,
                'year' => $date->year,
                'is_paid' => $request->boolean('is_paid', true),
                'country' => 'UG',
            ]
        );

        return back()->with('success', "Holiday '{$request->name}' saved.");
    }

    public function destroyPublicHoliday(PublicHoliday $holiday)
    {
        // `holiday_pay_approvals` and `holiday_work` both cascade on delete, so
        // removing a holiday that has been used takes the pay decision and the
        // record of who worked it with it — silently. A payslip that paid somebody
        // double for that day then refers to a holiday nobody can find, and the
        // approval that authorised it is gone.
        //
        // Once either exists the holiday is part of what somebody was paid, so it
        // stops being deletable. An unused holiday added by mistake still is.
        $approvals = HolidayPayApproval::where('public_holiday_id', $holiday->id)->count();
        $worked = HolidayWork::where('public_holiday_id', $holiday->id)->count();

        if ($approvals || $worked) {
            return back()->with('error', sprintf(
                '%s cannot be deleted: it carries %d pay decision(s) and %d attendance record(s). '
                .'Deleting it would erase them and leave the payslips that used it unexplainable.',
                $holiday->name, $approvals, $worked,
            ));
        }

        $holiday->delete();

        return back()->with('success', 'Holiday deleted.');
    }

    /**
     * Move ONE client to another account manager.
     *
     * The counterpart to transferClients, which moves somebody's whole book.
     * Moving one client is the common case -- rebalancing a single account --
     * and it was only reachable through a panel labelled "Assign Client"
     * sitting at the bottom of the page.
     *
     * The employees come with it. An account manager reaches staff through
     * their clients, so repointing the client repoints everybody placed there;
     * there is no second list to keep in step, which is exactly why there is no
     * separate "and their employees" switch.
     */
    public function assignAccountManager(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'account_manager_id' => 'nullable|exists:users,id',
        ]);

        $client = Client::findOrFail($data['client_id']);
        $from = $client->accountManager?->name ?? 'nobody';

        // Handing a client to somebody who is not an account manager leaves it
        // pointing at a login that cannot open it -- the staff then have nobody
        // to run their payroll or action their leave. The whole-book transfer
        // has always refused this; this path used to accept any user id at all.
        $target = null;

        if (! empty($data['account_manager_id'])) {
            $target = User::findOrFail($data['account_manager_id']);

            if (! $target->hasRole('account-manager')) {
                return back()->with('error',
                    $target->name.' is not an account manager, so '.$client->company_name
                    .' would be left with nobody who can open it. Appoint them first.');
            }
        }

        $moved = $client->employees()->count();

        $client->update(['account_manager_id' => $target?->id]);

        return back()->with('success', sprintf(
            '%s moved from %s to %s. %s %s with it.',
            $client->company_name,
            $from,
            $target?->name ?? 'nobody -- it now has no account manager',
            number_format($moved),
            $moved === 1 ? 'employee went' : 'employees went',
        ));
    }
}
