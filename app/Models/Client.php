<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'user_id', 'account_manager_id', 'company_name', 'contact_person',
        'industry', 'address', 'status', 'notes',
    ];

    public function user()           { return $this->belongsTo(User::class); }
    public function accountManager() { return $this->belongsTo(User::class, 'account_manager_id'); }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'client_employee_assignments')
                    ->withPivot('notes', 'assigned_by')
                    ->withTimestamps();
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
