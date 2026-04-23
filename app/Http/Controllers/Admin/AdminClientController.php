<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Client, Employee, JobPosting, User};
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
            'user_id'        => $user->id,
            'company_name'   => $request->company_name,
            'contact_person' => $request->contact_person,
            'industry'       => $request->industry,
            'address'        => $request->address,
            'status'         => 'active',
            'notes'          => $request->notes,
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
            'company_name'   => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'industry'       => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'status'         => 'required|in:active,inactive',
            'notes'          => 'nullable|string',
        ]);

        $client->update($request->only('company_name', 'contact_person', 'industry', 'address', 'status', 'notes'));

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function assignEmployee(Request $request, Client $client)
    {
        $request->validate(['employee_id' => 'required|exists:employees,id']);

        $client->employees()->syncWithoutDetaching([
            $request->employee_id => ['assigned_by' => auth()->id(), 'notes' => $request->notes],
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
}
