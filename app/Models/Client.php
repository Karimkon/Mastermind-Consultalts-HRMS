<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PayrollRun;

class Client extends Model
{
    protected $fillable = [
        'user_id', 'account_manager_id', 'company_name', 'contact_person',
        'phone', 'email',
        'industry', 'address', 'deployment_area', 'work_area', 'status', 'notes',
        'payment_day', 'work_site_address', 'work_site_lat', 'work_site_lng', 'geo_fence_radius',
        // Payroll formula settings
        'gross_up_paye', 'gpa_wmc_rate', 'billing_rate_multiplier', 'payroll_type',
    ];

    protected $casts = [
        'work_site_lat'          => 'float',
        'work_site_lng'          => 'float',
        'payment_day'            => 'integer',
        'geo_fence_radius'       => 'integer',
        'gross_up_paye'          => 'boolean',
        'gpa_wmc_rate'           => 'float',
        'billing_rate_multiplier'=> 'float',
    ];

    /** Human-readable payroll type label */
    public function getPayrollTypeLabelAttribute(): string
    {
        return match($this->payroll_type ?? 'daily') {
            'hourly'  => 'Hourly (Casual)',
            'daily'   => 'Daily (Casual)',
            'monthly' => 'Monthly (Contract)',
            'mixed'   => 'Mixed (Casual + Contract)',
            default   => ucfirst($this->payroll_type ?? 'daily'),
        };
    }

    public function user()           { return $this->belongsTo(User::class); }
    public function accountManager() { return $this->belongsTo(User::class, 'account_manager_id'); }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'client_employee_assignments')
                    ->withPivot('notes', 'assigned_by')
                    ->withTimestamps();
    }

    public function payrollRuns()
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function transferHistory()
    {
        return $this->hasMany(EmployeeClientTransfer::class)->orderByDesc('effective_date');
    }

    public function jobPostings()
    {
        return $this->belongsToMany(JobPosting::class, 'client_job_assignments')
                    ->withPivot('notes', 'assigned_by')
                    ->withTimestamps();
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->status === 'active'
            ? '<span class="badge-green">Active</span>'
            : '<span class="badge-gray">Inactive</span>';
    }
}
