<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Client, Employee, EmployeeClientTransfer, JobPosting, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            'company_name'   => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|string|min:8|confirmed',
            'industry'       => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $user = User::create([
            'name'     => $request->contact_person,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'status'   => 'active',
        ]);
        $user->syncRoles('client');

        $client = Client::create([
            'user_id'         => $user->id,
            'company_name'    => $request->company_name,
            'contact_person'  => $request->contact_person,
            'phone'           => $request->phone,
            'email'           => $request->client_email,
            'industry'        => $request->industry,
            'address'         => $request->address,
            'deployment_area' => $request->deployment_area,
            'work_area'       => $request->work_area,
            'status'          => 'active',
            'notes'           => $request->notes,
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
            'company_name'      => 'required|string|max:255',
            'contact_person'    => 'required|string|max:255',
            'industry'          => 'nullable|string|max:255',
            'address'           => 'nullable|string',
            'status'            => 'required|in:active,inactive',
            'notes'             => 'nullable|string',
            'payment_day'       => 'nullable|integer|min:1|max:31',
            'work_site_address' => 'nullable|string|max:255',
            'work_site_lat'     => 'nullable|numeric|between:-90,90',
            'work_site_lng'     => 'nullable|numeric|between:-180,180',
            'geo_fence_radius'  => 'nullable|integer|min:10|max:5000',
        ]);

        $client->update($request->only(
            'company_name', 'contact_person', 'phone', 'industry', 'address',
            'deployment_area', 'work_area', 'status', 'notes',
            'payment_day', 'work_site_address', 'work_site_lat', 'work_site_lng', 'geo_fence_radius'
        ));
        if ($request->filled('client_email')) $client->update(['email' => $request->client_email]);

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
            'employee_id'    => $request->employee_id,
            'client_id'      => $client->id,
            'type'           => 'assignment',
            'effective_date' => now()->toDateString(),
            'reason'         => $request->notes ?: 'Assigned to client',
            'recorded_by'    => auth()->id(),
        ]);

        // Mark future leave requests from this employee as needing client approval
        \App\Models\LeaveRequest::where('employee_id', $request->employee_id)
            ->where('status', 'pending')
            ->update(['client_approval_required' => true, 'client_approval_status' => 'pending']);

        return back()->with('success', 'Employee assigned to client.');
    }

    public function unassignEmployee(Client $client, Employee $employee)
    {
        $client->employees()->detach($employee->id);
        EmployeeClientTransfer::create([
            'employee_id'    => $employee->id,
            'client_id'      => $client->id,
            'type'           => 'removal',
            'effective_date' => now()->toDateString(),
            'reason'         => 'Removed from client',
            'recorded_by'    => auth()->id(),
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

        // All clients for the assignment dropdown
        $allClients  = Client::orderBy('company_name')->get();
        $allManagers = $managers;

        // Unassigned clients (no AM yet)
        $unassigned = Client::whereNull('account_manager_id')->orderBy('company_name')->get();

        return view('admin.account-managers.index', compact('managers', 'allClients', 'unassigned'));
    }

    public function assignAccountManager(Request $request)
    {
        $request->validate([
            'client_id'          => 'required|exists:clients,id',
            'account_manager_id' => 'nullable|exists:users,id',
        ]);
        Client::findOrFail($request->client_id)->update([
            'account_manager_id' => $request->account_manager_id ?: null,
        ]);
        return back()->with('success', 'Account Manager assignment updated.');
    }
}
