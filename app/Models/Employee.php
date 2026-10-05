<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{

    /**
     * Who may be paid at all.
     *
     * This rule lived in PayrollService::processRun() and again in
     * AccountManagerController, and was about to be written a third time for the
     * selection screen. Three copies of the test that decides whether somebody
     * gets paid is two too many — they drift, and the drift is silent.
     *
     * Not to be confused with being SELECTED for a run. This is the floor: a
     * blacklisted or terminated employee cannot be paid however they are ticked.
     */
    public function scopePayrollEligible($query)
    {
        $today = now()->toDateString();

        return $query
            ->whereIn('status', ['active', 'on_leave'])
            ->where('is_blacklisted', false)
            ->where(function ($q) use ($today) {
                $q->where('on_hold', false)
                  ->orWhere(function ($q2) use ($today) {
                      $q2->where('on_hold', true)
                         ->whereNotNull('hold_end_date')
                         ->where('hold_end_date', '<', $today);
                  });
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('contract_end_date')->orWhere('contract_end_date', '>=', $today);
            });
    }

    /** The same rule for one employee already in memory. */
    public function isPayrollEligible(): bool
    {
        if (! in_array($this->status, ['active', 'on_leave'], true)) {
            return false;
        }
        if ($this->is_blacklisted) {
            return false;
        }
        if ($this->on_hold && (! $this->hold_end_date || $this->hold_end_date->toDateString() >= now()->toDateString())) {
            return false;
        }
        if ($this->contract_end_date && $this->contract_end_date->toDateString() < now()->toDateString()) {
            return false;
        }

        return true;
    }

    /** Why they cannot be paid, for a screen that has to explain itself. */
    public function payrollIneligibilityReason(): ?string
    {
        if ($this->is_blacklisted) {
            return 'Blacklisted';
        }
        if ($this->on_hold && (! $this->hold_end_date || $this->hold_end_date->toDateString() >= now()->toDateString())) {
            return 'On hold';
        }
        if ($this->status === 'terminated') {
            return 'Terminated';
        }
        if ($this->status === 'suspended') {
            return 'Suspended';
        }
        if ($this->contract_end_date && $this->contract_end_date->toDateString() < now()->toDateString()) {
            return 'Contract ended ' . $this->contract_end_date->format('d M Y');
        }

        return null;
    }
    use SoftDeletes;

    protected $fillable = [
        // Identity
        'user_id','emp_number','payroll_number','title','first_name','middle_name','last_name',
        // Job
        'department_id','designation_id','org_position_id','manager_id','hire_date','end_date',
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
        'charge_wht','wht_percentage',
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
        'probation_notes','probation_alert_sent_at',
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
        'probation_alert_sent_at' => 'datetime',
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
        'charge_wht'                  => 'boolean',
        'wht_percentage'              => 'decimal:2',
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
    public function orgPosition() { return $this->belongsTo(OrgPosition::class, 'org_position_id'); }
    public function subordinates(){ return $this->hasMany(Employee::class, 'manager_id'); }

    /**
     * Everybody below this person in the reporting line, however deep.
     *
     * A line manager needs their whole branch, not only direct reports —
     * otherwise a supervisor two rungs up cannot see a leave request they are
     * accountable for.
     *
     * One query for the tree, then walked in memory: 1,200-odd rows of
     * (id, manager_id) is nothing, and the recursive alternative is a query per
     * level. Cycles are guarded, because a reporting line that loops back on
     * itself would otherwise spin here forever and the data is hand-entered.
     *
     * @return array<int,int>
     */
    public function descendantIds(): array
    {
        $childrenOf = [];
        foreach (static::query()->whereNotNull('manager_id')->pluck('manager_id', 'id') as $childId => $managerId) {
            $childrenOf[$managerId][] = $childId;
        }

        $found   = [];
        $pending = [$this->id];

        while ($pending) {
            foreach ($childrenOf[array_pop($pending)] ?? [] as $childId) {
                if ($childId === $this->id || isset($found[$childId])) continue;
                $found[$childId] = true;
                $pending[] = $childId;
            }
        }

        return array_keys($found);
    }

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

    // ── Payment channel ───────────────────────────────────────────────
    // payment_mode has been written by several screens over time with
    // different spellings ("Bank", "bank", "bank_transfer", "mobile_money").
    // Everything that pays money out must go through paymentChannel(), never
    // read payment_mode directly, or rows silently drop out of the bank files.

    public const PAYMENT_CHANNELS = [
        'bank'   => 'Bank Transfer (EFT)',
        'mtn'    => 'MTN Mobile Money',
        'airtel' => 'Airtel Mobile Money',
        'cash'   => 'Cash',
        'cheque' => 'Cheque',
    ];

    /** MNO prefixes as allocated by UCC (local part, without the leading 0). */
    private const MTN_PREFIXES    = ['76', '77', '78', '79', '39', '31'];
    private const AIRTEL_PREFIXES = ['70', '74', '75', '20'];

    /**
     * Normalised payout channel: bank|mtn|airtel|cash|cheque|unassigned.
     * Legacy "mobile_money" rows are resolved to the real network from the
     * subscriber number; if that cannot be determined the employee is
     * "unassigned" so the readiness report flags them instead of them
     * disappearing from every payment file.
     */
    public function paymentChannel(): string
    {
        $raw = str_replace([' ', '-'], '_', strtolower(trim((string) $this->payment_mode)));

        return match ($raw) {
            'mtn', 'mtn_momo', 'mtn_mobile_money'          => 'mtn',
            'airtel', 'airtel_money', 'airtel_mobile_money' => 'airtel',
            'cash'                                          => 'cash',
            'cheque', 'check'                               => 'cheque',
            'mobile_money', 'momo', 'mobile'                => $this->detectMno() ?? 'unassigned',
            default                                         => 'bank',
        };
    }

    public function paymentChannelLabel(): string
    {
        return self::PAYMENT_CHANNELS[$this->paymentChannel()] ?? 'Not set';
    }

    /**
     * Map whatever a form or spreadsheet supplied onto a canonical payment_mode.
     * Every write path (forms, imports, API) must run through this so the
     * column never drifts back into mixed spellings.
     */
    public static function normalisePaymentMode(?string $raw, ?string $mobileNumber = null): string
    {
        $value = str_replace([' ', '-'], '_', strtolower(trim((string) $raw)));

        $mode = match ($value) {
            'mtn', 'mtn_momo', 'mtn_mobile_money'           => 'mtn',
            'airtel', 'airtel_money', 'airtel_mobile_money' => 'airtel',
            'cash'                                          => 'cash',
            'cheque', 'check'                               => 'cheque',
            'mobile_money', 'momo', 'mobile'                => 'mobile_money',
            default                                         => 'bank',
        };

        // "Mobile money" without a network: resolve it from the subscriber number.
        if ($mode === 'mobile_money') {
            $probe = new static(['mobile_money_number' => $mobileNumber]);
            $mode  = $probe->detectMno() ?? 'mobile_money';
        }

        return $mode;
    }

    /** Which network does the employee's number belong to? Null when undeterminable. */
    public function detectMno(): ?string
    {
        $msisdn = $this->payoutNumber();
        if ($msisdn === '') return null;

        $prefix = substr($msisdn, 3, 2);
        if (in_array($prefix, self::MTN_PREFIXES, true))    return 'mtn';
        if (in_array($prefix, self::AIRTEL_PREFIXES, true)) return 'airtel';
        return null;
    }

    /**
     * Mobile money number in the 256XXXXXXXXX form the bank requires.
     * Returns '' when there is no usable number.
     */
    public function payoutNumber(): string
    {
        $digits = preg_replace('/\D/', '', (string) ($this->mobile_money_number ?: $this->phone));
        $digits = ltrim($digits, '0');                                  // 00256… / 07… → 256… / 7…
        if (str_starts_with($digits, '256')) $digits = substr($digits, 3);

        return strlen($digits) === 9 ? '256' . $digits : '';
    }

    /**
     * Why this employee cannot be paid, or null when they are payable.
     * Used by the payroll payment-readiness screen.
     */
    public function payoutIssue(): ?string
    {
        return match ($this->paymentChannel()) {
            'bank' => match (true) {
                blank($this->bank_account) => 'No bank account number',
                blank($this->bank_name)    => 'No bank name',
                default                    => null,
            },
            'mtn', 'airtel' => $this->payoutNumber() === ''
                ? 'No valid mobile money number'
                : null,
            'unassigned' => 'Mobile money selected but the network could not be determined from the number',
            default      => null,   // cash / cheque are handled outside the bank files
        };
    }

    public function isPayable(): bool
    {
        return $this->payoutIssue() === null;
    }
}
