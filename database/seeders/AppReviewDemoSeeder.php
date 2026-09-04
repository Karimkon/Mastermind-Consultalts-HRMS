<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Fills the App Store / Play Store review account with realistic data.
 *
 * Apple reviews the app signed in as the credentials given under App Review
 * Information. That account had no employee record, so every personal screen —
 * attendance, payslips, leave — came up empty, which is why the only screenshot
 * worth taking was the login page (the 2.3.3 rejection).
 *
 * This gives that account a full year of plausible history. Everything it
 * touches is scoped to the one review employee; no real staff record is read
 * or modified.
 *
 *   php artisan db:seed --class=AppReviewDemoSeeder
 *
 * Override the target account with env vars if you ever rotate the credentials:
 *   APP_REVIEW_EMAIL=... APP_REVIEW_PASSWORD=... php artisan db:seed --class=AppReviewDemoSeeder
 */
class AppReviewDemoSeeder extends Seeder
{
    /** Matches the Sign-In Information already saved in App Store Connect. */
    protected const DEFAULT_EMAIL = 'am@mastermind.autos';
    protected const DEFAULT_PASSWORD = 'Am@1234';
    protected const EMP_NUMBER = 'MM-REVIEW-01';

    public function run(): void
    {
        $email = env('APP_REVIEW_EMAIL', self::DEFAULT_EMAIL);
        $password = env('APP_REVIEW_PASSWORD', self::DEFAULT_PASSWORD);

        $user = $this->user($email, $password);
        $employee = $this->employee($user);

        $this->salary($employee);
        $this->leave($employee);
        $this->attendance($employee);
        $this->payslips($employee);

        $this->command->newLine();
        $this->command->info('App review account ready.');
        $this->command->line('  Email:    ' . $email);
        $this->command->line('  Password: ' . $password);
        $this->command->line('  Employee: ' . $employee->first_name . ' ' . $employee->last_name . ' (' . $employee->emp_number . ')');
        $this->command->line('  Data:     ' . AttendanceLog::where('employee_id', $employee->id)->count() . ' attendance days, '
            . Payslip::where('employee_id', $employee->id)->count() . ' payslips, '
            . LeaveRequest::where('employee_id', $employee->id)->count() . ' leave requests');
    }

    /** The login itself: active, no MFA, password known and verified. */
    protected function user(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = new User();
            $user->email = $email;
            $user->name = 'James Mugisha';
        }

        $user->password = Hash::make($password);   // cast to hashed on the model
        $user->status = 'active';
        $user->mfa_enabled = false;
        $user->mfa_secret = null;
        $user->mfa_confirmed_at = null;
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        // Reviewers need to see the everyday employee screens, so make sure the
        // account carries the employee role alongside whatever it already has.
        if (method_exists($user, 'hasRole') && ! $user->hasRole('employee')) {
            $user->assignRole('employee');
        }

        return $user;
    }

    /** Without an employee record every personal screen in the app is empty. */
    protected function employee(User $user): Employee
    {
        $employee = Employee::where('user_id', $user->id)->first()
            ?: Employee::where('emp_number', self::EMP_NUMBER)->first()
            ?: new Employee();

        $employee->user_id = $user->id;
        $employee->emp_number = $employee->emp_number ?: self::EMP_NUMBER;
        $employee->first_name = 'James';
        $employee->last_name = 'Mugisha';
        $employee->hire_date = $employee->hire_date ?: now()->subYears(3)->startOfMonth();
        $employee->employment_type = 'full_time';
        $employee->status = 'active';
        $employee->department_id = $employee->department_id
            ?: optional(Department::where('name', 'Human Resources')->first() ?: Department::first())->id;
        $employee->designation_id = $employee->designation_id
            ?: optional(Designation::where('title', 'HR Officer')->first() ?: Designation::first())->id;
        $employee->work_location = $employee->work_location ?: 'Kampala Head Office';
        $employee->save();

        return $employee;
    }

    protected function salary(Employee $employee): void
    {
        EmployeeSalary::updateOrCreate(
            ['employee_id' => $employee->id, 'is_current' => true],
            [
                'basic_salary' => 1_800_000,
                'salary_type' => 'monthly',
                'effective_from' => now()->subYear()->startOfYear(),
                'components' => [
                    ['name' => 'Transport Allowance', 'type' => 'allowance', 'amount' => 200_000],
                    ['name' => 'Airtime Allowance', 'type' => 'allowance', 'amount' => 50_000],
                ],
            ]
        );
    }

    /** Balances plus one approved and one pending request, so both states show. */
    protected function leave(Employee $employee): void
    {
        $annual = LeaveType::where('name', 'Annual Leave')->first() ?: LeaveType::first();
        $sick = LeaveType::where('name', 'Sick Leave')->first() ?: $annual;

        if (! $annual) {
            return;
        }

        LeaveBalance::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'year' => now()->year],
            ['total_days' => 21, 'used_days' => 6, 'pending_days' => 3]
        );

        LeaveBalance::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $sick->id, 'year' => now()->year],
            ['total_days' => 10, 'used_days' => 2, 'pending_days' => 0]
        );

        LeaveRequest::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $annual->id,
                'from_date' => now()->subMonths(2)->startOfMonth()->addDays(9)->toDateString(),
            ],
            [
                'to_date' => now()->subMonths(2)->startOfMonth()->addDays(14)->toDateString(),
                'days_count' => 4,
                'reason' => 'Family commitment upcountry.',
                'status' => 'approved',
            ]
        );

        LeaveRequest::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $annual->id,
                'from_date' => now()->addWeeks(3)->startOfWeek()->toDateString(),
            ],
            [
                'to_date' => now()->addWeeks(3)->startOfWeek()->addDays(2)->toDateString(),
                'days_count' => 3,
                'reason' => 'Short leave.',
                'status' => 'pending',
            ]
        );
    }

    /** Sixty working days of clock-ins, with a few late days so it looks real. */
    protected function attendance(Employee $employee): void
    {
        $day = now()->copy()->subDays(90);
        $today = now()->copy()->startOfDay();
        $index = 0;

        while ($day->lte($today)) {
            if ($day->isWeekend()) {
                $day->addDay();
                continue;
            }

            $index++;
            $late = $index % 11 === 0;
            $absent = $index % 23 === 0;

            AttendanceLog::updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $day->toDateString()],
                $absent
                    ? ['clock_in' => null, 'clock_out' => null, 'status' => 'absent']
                    : [
                        'clock_in' => $day->copy()->setTime($late ? 9 : 8, $late ? 12 : 2)->toDateTimeString(),
                        'clock_out' => $day->copy()->setTime(17, 6)->toDateTimeString(),
                        'status' => $late ? 'late' : 'present',
                    ]
            );

            $day->addDay();
        }
    }

    /** Three months of payslips, so the payslip list and detail both populate. */
    protected function payslips(Employee $employee): void
    {
        $basic = 1_800_000;
        $allowances = 250_000;
        $gross = $basic + $allowances;

        // Uganda: employee NSSF is 5% of gross, employer adds 10% on top.
        $employeeNssf = round($gross * 0.05);
        $employerNssf = round($gross * 0.10);
        $paye = round($this->paye($gross));
        $deductions = $employeeNssf + $paye;

        for ($back = 1; $back <= 3; $back++) {
            $month = now()->copy()->subMonths($back);

            $run = PayrollRun::firstOrCreate(
                ['month' => (int) $month->format('n'), 'year' => (int) $month->format('Y'), 'title' => $month->format('F Y') . ' Payroll'],
                ['status' => 'paid', 'payment_date' => $month->copy()->endOfMonth()->toDateString()]
            );

            Payslip::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                [
                    'basic_salary' => $basic,
                    'total_allowances' => $allowances,
                    'gross_salary' => $gross,
                    'tax_amount' => $paye,
                    'employee_nssf' => $employeeNssf,
                    'employer_nssf' => $employerNssf,
                    'total_deductions' => $deductions,
                    'net_salary' => $gross - $deductions,
                    'worked_days' => 22,
                    'absent_days' => 0,
                    'payment_status' => 'paid',
                    'component_details' => [
                        ['name' => 'Basic Salary', 'type' => 'earning', 'amount' => $basic],
                        ['name' => 'Transport Allowance', 'type' => 'earning', 'amount' => 200_000],
                        ['name' => 'Airtime Allowance', 'type' => 'earning', 'amount' => 50_000],
                        ['name' => 'PAYE', 'type' => 'deduction', 'amount' => $paye],
                        ['name' => 'NSSF (5%)', 'type' => 'deduction', 'amount' => $employeeNssf],
                    ],
                ]
            );
        }
    }

    /** Uganda PAYE on monthly chargeable income, resident rates. */
    protected function paye(float $gross): float
    {
        if ($gross <= 235_000) {
            return 0;
        }

        if ($gross <= 335_000) {
            return ($gross - 235_000) * 0.10;
        }

        if ($gross <= 410_000) {
            return 10_000 + ($gross - 335_000) * 0.20;
        }

        $tax = 25_000 + ($gross - 410_000) * 0.30;

        if ($gross > 10_000_000) {
            $tax += ($gross - 10_000_000) * 0.10;
        }

        return $tax;
    }
}
