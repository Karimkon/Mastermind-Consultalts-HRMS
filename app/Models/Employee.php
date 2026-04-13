<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id','emp_number','department_id','designation_id','manager_id',
        'first_name','last_name','phone','personal_email','date_of_birth','gender',
        'national_id','address','city','country','emergency_contact_name',
        'emergency_contact_phone','hire_date','end_date','employment_type','status',
        'salary_grade','bank_name','bank_account','bank_branch',
        'tax_number','bio',
    ];

    protected $casts = ['hire_date' => 'date', 'end_date' => 'date', 'date_of_birth' => 'date'];

    public function user()        { return $this->belongsTo(User::class); }
    public function department()  { return $this->belongsTo(Department::class); }
    public function designation() { return $this->belongsTo(Designation::class); }
    public function manager()     { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function subordinates(){ return $this->hasMany(Employee::class, 'manager_id'); }
    public function documents()   { return $this->hasMany(EmployeeDocument::class); }
    public function history()           { return $this->hasMany(EmploymentHistory::class)->orderByDesc('start_date'); }
    public function employmentHistory() { return $this->hasMany(EmploymentHistory::class)->orderByDesc('start_date'); }
    public function attendanceLogs(){ return $this->hasMany(AttendanceLog::class); }
    public function leaveRequests() { return $this->hasMany(LeaveRequest::class); }
    public function leaveBalances() { return $this->hasMany(LeaveBalance::class); }
    public function salary()        { return $this->hasOne(EmployeeSalary::class)->latest(); }
    public function salaryGrade()   { return $this->belongsTo(SalaryGrade::class); }
    public function payslips()      { return $this->hasMany(Payslip::class); }
    public function enrollments()   { return $this->hasMany(TrainingEnrollment::class); }
    public function certifications(){ return $this->hasMany(Certification::class); }
    public function kpis()          { return $this->hasMany(EmployeeKpi::class); }
    public function reviews()       { return $this->hasMany(PerformanceReview::class); }
    public function meetings()        { return $this->belongsToMany(Meeting::class, 'meeting_participants', 'employee_id', 'meeting_id'); }
    public function onboardingTasks() { return $this->hasMany(OnboardingTask::class)->orderBy('sort_order'); }
    public function exitWorkflow()    { return $this->hasOne(ExitWorkflow::class); }
    public function goals()           { return $this->hasMany(EmployeeGoal::class); }
    public function pips()            { return $this->hasMany(Pip::class); }
    public function assessments()     { return $this->hasMany(TrainingAssessment::class); }

    public function getFullNameAttribute(): string { return "{$this->first_name} {$this->last_name}"; }

    public function getAvatarUrlAttribute(): string
    {
        return $this->user?->avatar_url
            ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . '&background=1e40af&color=fff&size=128';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'active'     => '<span class="badge-green">Active</span>',
            'on_leave'   => '<span class="badge-yellow">On Leave</span>',
            'terminated' => '<span class="badge-red">Terminated</span>',
            'suspended'  => '<span class="badge-orange">Suspended</span>',
            default      => '<span class="badge-gray">Unknown</span>',
        };
    }
}
