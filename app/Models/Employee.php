<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // Identity
        'user_id','emp_number','payroll_number','title','first_name','middle_name','last_name',
        // Job
        'department_id','designation_id','manager_id','hire_date','end_date',
        'employment_type','status','salary_grade',
        // Job Profile
        'week_off_type','week_off_day','holiday_calendar','contract_applicable','is_expatriate',
        'contract_start_date','contract_end_date','contract_notes',
        // Placement
        'work_location','sub_department','employee_category','class_name','position_name','organization_unit',
        // Personal
        'phone','personal_email','date_of_birth','gender','marital_status',
        'children_count','dependents_count','anniversary_date',
        'national_id','passport_number','address','city','country',
        'religion','nationality','mother_tongue','bio',
        // Emergency & NOK
        'emergency_contact_name','emergency_contact_phone',
        'next_of_kin_name','next_of_kin_relation','next_of_kin_phone','next_of_kin_email',
        // Insurance & Statutory IDs
        'insurance_relief','nssf_number','tin_number','ifms_supplier_no','pension_no',
        // Statutory Deductions
        'charge_nssf','force_fixed_nssf','fixed_nssf_amount','nssf_paid_by_employer',
        'do_not_charge_nssf_employee','voluntary_nssf',
        'charge_lst','lst_paid_by_employer','tax_paid_by_employer','charge_paye',
        'apply_special_tax','special_tax_percentage',
        // Banking & Salary Calculation
        'bank_name','bank_account','bank_branch','mobile_money_number','tax_number','payment_mode',
        'ot_calc_hours','absenteeism_calc_hours','ot1_calc_hours','ot2_calc_hours','min_daily_working_hours',
        // Provident Fund
        'pf_applicable','pf_deduction_type','pf_calculate_on','pf_employee_rate','pf_employer_rate','pf_scheme',
        'voluntary_pf_deduction_type','voluntary_pf_calculate_on','voluntary_pf_amount','do_not_deduct_voluntary_pf',
        // Pension
        'pension_applicable','pension_deduction_type','pension_calculate_on',
        'pension_employee_rate','pension_employer_rate','pension_scheme',
        'voluntary_pension_deduction_type','voluntary_pension_calculate_on',
        'voluntary_pension_amount','do_not_deduct_voluntary_pension',
        // Blacklist & Hold
        'is_blacklisted','blacklist_date','blacklist_reason',
        'on_hold','hold_date','hold_end_date','hold_reason',
        // Termination & Retirement
        'termination_reason','retirement_date','retirement_reason',
        // Probation
        'probation_end_date','probation_status','probation_confirmed_at','probation_confirmed_by',
    ];

    protected $casts = [
        'hire_date'              => 'date',
        'end_date'               => 'date',
        'contract_start_date'    => 'date',
        'contract_end_date'      => 'date',
        'retirement_date'        => 'date',
        'date_of_birth'          => 'date',
        'anniversary_date'       => 'date',
        'blacklist_date'         => 'date',
        'hold_date'              => 'date',
        'hold_end_date'          => 'date',
        'probation_end_date'     => 'date',
        'probation_confirmed_at' => 'datetime',
        'contract_applicable'         => 'boolean',
        'is_expatriate'               => 'boolean',
        'insurance_relief'            => 'boolean',
        'charge_nssf'                 => 'boolean',
        'force_fixed_nssf'            => 'boolean',
        'nssf_paid_by_employer'       => 'boolean',
        'do_not_charge_nssf_employee' => 'boolean',
        'charge_lst'                  => 'boolean',
        'lst_paid_by_employer'        => 'boolean',
        'tax_paid_by_employer'        => 'boolean',
        'charge_paye'                 => 'boolean',
        'apply_special_tax'           => 'boolean',
        'pf_applicable'               => 'boolean',
        'do_not_deduct_voluntary_pf'  => 'boolean',
        'pension_applicable'                => 'boolean',
        'do_not_deduct_voluntary_pension'   => 'boolean',
        'is_blacklisted'              => 'boolean',
        'on_hold'                     => 'boolean',
    ];

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
    public function assessments()       { return $this->hasMany(TrainingAssessment::class); }
    public function bscEntries()        { return $this->hasMany(\App\Models\BscEntry::class); }
    public function probationConfirmedBy() { return $this->belongsTo(User::class, 'probation_confirmed_by'); }
    public function clients()           { return $this->belongsToMany(Client::class, 'client_employee_assignments')->withTimestamps(); }
    public function clientTransfers()  { return $this->hasMany(EmployeeClientTransfer::class)->with('client')->orderByDesc('effective_date'); }

    public function getFullNameAttribute(): string { return "{$this->first_name} {$this->last_name}"; }

    public function getProbationStatusBadgeAttribute(): string
    {
        return match($this->probation_status) {
            'on_probation' => '<span class="badge-yellow">On Probation</span>',
            'passed'       => '<span class="badge-green">Passed</span>',
            'failed'       => '<span class="badge-red">Failed</span>',
            'extended'     => '<span class="badge-blue">Extended</span>',
            default        => '<span class="badge-gray">Not Set</span>',
        };
    }

    public function getProbationDaysLeftAttribute(): ?int
    {
        if (!$this->probation_end_date) return null;
        return max(0, now()->diffInDays($this->probation_end_date, false));
    }

    public function getIsOnProbationAttribute(): bool
    {
        return $this->probation_status === 'on_probation' && $this->probation_end_date?->isFuture();
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->user?->avatar_url
            ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . '&background=1e40af&color=fff&size=128';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'active'            => '<span class="badge-green">Active</span>',
            'on_leave'          => '<span class="badge-yellow">On Leave</span>',
            'terminated'        => '<span class="badge-red">Terminated</span>',
            'suspended'         => '<span class="badge-orange">Suspended</span>',
            'retired'           => '<span class="badge-gray">Retired</span>',
            'contract_expired'  => '<span class="badge-red">Contract Expired</span>',
            default             => '<span class="badge-gray">Unknown</span>',
        };
    }
}
