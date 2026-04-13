<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Employee, AttendanceLog, LeaveRequest, LeaveBalance, LeaveType, Meeting, MeetingParticipant, JobPosting, TrainingCourse, TrainingEnrollment, PerformanceCycle, PayrollRun, User};
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found. Run DemoUsersSeeder first.');
            return;
        }

        $this->seedAttendance($employees);
        $this->seedLeaveBalances($employees);
        $this->seedLeaveRequests($employees);
        $this->seedMeetings();
        $this->seedJobPostings();
        $this->seedTrainingCourses($employees);
        $this->seedPerformanceCycle();
        $this->seedPayrollRun();

        $this->command->info('Demo data seeded successfully!');
    }

    private function seedAttendance($employees): void
    {
        $statuses = ['present','present','present','present','late','absent'];

        for ($i = 1; $i <= 14; $i++) {
            $date = Carbon::now()->subDays($i);

            // Skip weekends
            if ($date->isWeekend()) continue;

            foreach ($employees as $emp) {
                // Skip if already exists
                if (AttendanceLog::where('employee_id', $emp->id)->where('date', $date->format('Y-m-d'))->exists()) {
                    continue;
                }

                $status = $statuses[array_rand($statuses)];
                $clockIn = null;
                $clockOut = null;
                $overtime = 0;

                if ($status === 'present') {
                    $clockIn  = $date->copy()->setTime(8, rand(0, 10), 0);
                    $clockOut = $date->copy()->setTime(rand(17, 18), rand(0, 59), 0);
                    $overtime = $clockOut->hour >= 18 ? round(($clockOut->hour - 17) + $clockOut->minute / 60, 2) : 0;
                } elseif ($status === 'late') {
                    $clockIn  = $date->copy()->setTime(rand(9, 10), rand(0, 59), 0);
                    $clockOut = $date->copy()->setTime(17, rand(0, 59), 0);
                }

                AttendanceLog::create([
                    'employee_id'    => $emp->id,
                    'date'           => $date->format('Y-m-d'),
                    'clock_in'       => $clockIn,
                    'clock_out'      => $clockOut,
                    'status'         => $status,
                    'overtime_hours' => $overtime,
                ]);
            }
        }

        $this->command->info('Attendance records seeded.');
    }

    private function seedLeaveBalances($employees): void
    {
        $year = now()->year;
        $leaveTypes = LeaveType::whereIn('id', [1, 2])->get(); // Annual + Sick

        if ($leaveTypes->isEmpty()) {
            $leaveTypes = LeaveType::take(2)->get();
        }

        foreach ($employees as $emp) {
            foreach ($leaveTypes as $lt) {
                LeaveBalance::firstOrCreate(
                    ['employee_id' => $emp->id, 'leave_type_id' => $lt->id, 'year' => $year],
                    [
                        'total_days'   => $lt->code === 'AL' ? 21 : 10,
                        'used_days'    => 0,
                        'pending_days' => 0,
                    ]
                );
            }
        }

        $this->command->info('Leave balances seeded.');
    }

    private function seedLeaveRequests($employees): void
    {
        if ($employees->count() < 3) return;

        $leaveTypeId1 = LeaveType::where('code', 'AL')->value('id') ?? 1;
        $leaveTypeId2 = LeaveType::where('code', 'SL')->value('id') ?? 2;

        $requests = [
            // 3 pending
            ['emp_idx' => 0, 'type_id' => $leaveTypeId1, 'from' => now()->addDays(5),  'to' => now()->addDays(7),  'days' => 3, 'status' => 'pending',  'reason' => 'Family vacation'],
            ['emp_idx' => 1, 'type_id' => $leaveTypeId2, 'from' => now()->addDays(2),  'to' => now()->addDays(3),  'days' => 2, 'status' => 'pending',  'reason' => 'Medical appointment'],
            ['emp_idx' => 2, 'type_id' => $leaveTypeId1, 'from' => now()->addDays(10), 'to' => now()->addDays(12), 'days' => 3, 'status' => 'pending',  'reason' => 'Personal leave'],
            // 2 approved
            ['emp_idx' => 0, 'type_id' => $leaveTypeId1, 'from' => now()->subDays(20), 'to' => now()->subDays(17), 'days' => 4, 'status' => 'approved', 'reason' => 'Annual vacation'],
            ['emp_idx' => 1, 'type_id' => $leaveTypeId2, 'from' => now()->subDays(10), 'to' => now()->subDays(9),  'days' => 2, 'status' => 'approved', 'reason' => 'Sick leave'],
            // 1 rejected
            ['emp_idx' => 2, 'type_id' => $leaveTypeId1, 'from' => now()->subDays(5),  'to' => now()->subDays(3),  'days' => 3, 'status' => 'rejected', 'reason' => 'Personal trip'],
        ];

        foreach ($requests as $req) {
            $empIdx = min($req['emp_idx'], $employees->count() - 1);
            $emp = $employees[$empIdx];

            // Check if a similar one already exists to avoid dupes
            $exists = LeaveRequest::where('employee_id', $emp->id)
                ->where('from_date', $req['from']->format('Y-m-d'))
                ->exists();

            if ($exists) continue;

            LeaveRequest::create([
                'employee_id'      => $emp->id,
                'leave_type_id'    => $req['type_id'],
                'from_date'        => $req['from']->format('Y-m-d'),
                'to_date'          => $req['to']->format('Y-m-d'),
                'days_count'       => $req['days'],
                'reason'           => $req['reason'],
                'status'           => $req['status'],
                'rejection_reason' => $req['status'] === 'rejected' ? 'Operational requirements' : null,
                'actioned_at'      => in_array($req['status'], ['approved','rejected']) ? now() : null,
            ]);
        }

        $this->command->info('Leave requests seeded.');
    }

    private function seedMeetings(): void
    {
        $organizer = User::whereHas('roles', fn($q) => $q->whereIn('name', ['super-admin','hr-admin','manager']))->first();
        if (!$organizer) {
            $organizer = User::first();
        }

        $meetings = [
            [
                'title'       => 'Q2 HR Review Meeting',
                'description' => 'Quarterly HR performance and headcount review',
                'start_at'    => now()->addDays(2)->setTime(10, 0),
                'end_at'      => now()->addDays(2)->setTime(11, 30),
                'type'        => 'team',
                'location'    => 'Boardroom A',
            ],
            [
                'title'       => 'Payroll Finalisation',
                'description' => 'Monthly payroll sign-off session',
                'start_at'    => now()->addDays(4)->setTime(14, 0),
                'end_at'      => now()->addDays(4)->setTime(15, 0),
                'type'        => 'team',
                'location'    => 'Finance Office',
            ],
            [
                'title'       => 'Onboarding Orientation',
                'description' => 'New employee onboarding and orientation session',
                'start_at'    => now()->addDays(6)->setTime(9, 0),
                'end_at'      => now()->addDays(6)->setTime(12, 0),
                'type'        => 'training',
                'location'    => 'Training Room 1',
            ],
        ];

        foreach ($meetings as $data) {
            $exists = Meeting::where('title', $data['title'])
                ->where('start_at', $data['start_at'])
                ->exists();

            if (!$exists) {
                Meeting::create(array_merge($data, [
                    'organizer_id' => $organizer->id,
                    'status'       => 'scheduled',
                ]));
            }
        }

        $this->command->info('Meetings seeded.');
    }

    private function seedJobPostings(): void
    {
        $adminUser = User::whereHas('roles', fn($q) => $q->where('name', 'super-admin'))->first();
        $createdBy = $adminUser?->id;

        $postings = [
            [
                'title'            => 'Senior Software Engineer',
                'reference_number' => 'JOB-2026-001',
                'department_id'    => 3, // Engineering
                'employment_type'  => 'full_time',
                'location'         => 'Johannesburg, South Africa',
                'description'      => 'We are looking for a Senior Software Engineer to join our Engineering team.',
                'requirements'     => "- 5+ years of experience in PHP/Laravel\n- Strong knowledge of REST APIs\n- Experience with MySQL",
                'salary_min'       => 45000,
                'salary_max'       => 65000,
                'vacancies'        => 2,
                'deadline'         => now()->addDays(30)->format('Y-m-d'),
                'status'           => 'open',
            ],
            [
                'title'            => 'HR Business Partner',
                'reference_number' => 'JOB-2026-002',
                'department_id'    => 1, // HR
                'employment_type'  => 'full_time',
                'location'         => 'Cape Town, South Africa',
                'description'      => 'Seeking an experienced HR Business Partner to support our growing team.',
                'requirements'     => "- 3+ years HR experience\n- HRIS knowledge\n- Strong communication skills",
                'salary_min'       => 30000,
                'salary_max'       => 45000,
                'vacancies'        => 1,
                'deadline'         => now()->addDays(21)->format('Y-m-d'),
                'status'           => 'open',
            ],
            [
                'title'            => 'Financial Analyst',
                'reference_number' => 'JOB-2026-003',
                'department_id'    => 2, // Finance
                'employment_type'  => 'full_time',
                'location'         => 'Durban, South Africa',
                'description'      => 'Join our Finance team as a Financial Analyst driving business insights.',
                'requirements'     => "- BCom degree in Finance or Accounting\n- 2+ years experience\n- Proficient in Excel and financial modelling",
                'salary_min'       => 28000,
                'salary_max'       => 40000,
                'vacancies'        => 1,
                'deadline'         => now()->addDays(14)->format('Y-m-d'),
                'status'           => 'open',
            ],
        ];

        foreach ($postings as $posting) {
            $exists = JobPosting::where('reference_number', $posting['reference_number'])->exists();
            if (!$exists) {
                JobPosting::create(array_merge($posting, ['created_by' => $createdBy]));
            }
        }

        $this->command->info('Job postings seeded.');
    }

    private function seedTrainingCourses($employees): void
    {
        $courses = [
            [
                'title'          => 'Leadership & Management Essentials',
                'code'           => 'TRN-LDR-001',
                'description'    => 'Core leadership skills for team leads and managers.',
                'category'       => 'Leadership',
                'provider'       => 'Mastermind Learning Academy',
                'duration_hours' => 16,
                'cost'           => 2500,
                'is_mandatory'   => false,
                'is_active'      => true,
            ],
            [
                'title'          => 'Data Protection & POPIA Compliance',
                'code'           => 'TRN-COMP-001',
                'description'    => 'Understanding POPIA and data privacy obligations.',
                'category'       => 'Compliance',
                'provider'       => 'SA Compliance Institute',
                'duration_hours' => 8,
                'cost'           => 1200,
                'is_mandatory'   => true,
                'is_active'      => true,
            ],
            [
                'title'          => 'Microsoft Excel Advanced',
                'code'           => 'TRN-IT-001',
                'description'    => 'Advanced Excel for data analysis and reporting.',
                'category'       => 'IT Skills',
                'provider'       => 'Tech Skills SA',
                'duration_hours' => 12,
                'cost'           => 1800,
                'is_mandatory'   => false,
                'is_active'      => true,
            ],
        ];

        $createdCourses = [];
        foreach ($courses as $course) {
            $c = TrainingCourse::firstOrCreate(['code' => $course['code']], $course);
            $createdCourses[] = $c;
        }

        // Add some enrollments
        $enrollStatuses = ['enrolled', 'enrolled', 'in_progress', 'completed'];
        $sampleEmps = $employees->take(5);

        foreach ($createdCourses as $course) {
            foreach ($sampleEmps->take(3) as $emp) {
                $exists = TrainingEnrollment::where('employee_id', $emp->id)
                    ->where('course_id', $course->id)
                    ->exists();

                if (!$exists) {
                    $status = $enrollStatuses[array_rand($enrollStatuses)];
                    TrainingEnrollment::create([
                        'employee_id' => $emp->id,
                        'course_id'   => $course->id,
                        'status'      => $status,
                        'enrolled_at' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                        'completed_at'=> $status === 'completed' ? now()->subDays(rand(1, 7))->format('Y-m-d') : null,
                    ]);
                }
            }
        }

        $this->command->info('Training courses and enrollments seeded.');
    }

    private function seedPerformanceCycle(): void
    {
        $exists = PerformanceCycle::where('year', now()->year)->exists();
        if (!$exists) {
            PerformanceCycle::create([
                'name'       => 'FY' . now()->year . ' Performance Cycle',
                'year'       => now()->year,
                'start_date' => now()->startOfYear()->format('Y-m-d'),
                'end_date'   => now()->endOfYear()->format('Y-m-d'),
                'status'     => 'active',
            ]);
            $this->command->info('Performance cycle seeded.');
        } else {
            $this->command->info('Performance cycle already exists — skipped.');
        }
    }

    private function seedPayrollRun(): void
    {
        $lastMonth = now()->subMonth();
        $month = (int) $lastMonth->format('n');
        $year  = (int) $lastMonth->format('Y');

        $exists = PayrollRun::where('month', $month)->where('year', $year)->exists();
        if (!$exists) {
            PayrollRun::create([
                'title'        => $lastMonth->format('F Y') . ' Payroll',
                'month'        => $month,
                'year'         => $year,
                'status'       => 'processed',
                'processed_at' => now()->subDays(5),
                'notes'        => 'Auto-generated demo payroll run.',
            ]);
            $this->command->info('Payroll run seeded.');
        } else {
            $this->command->info('Payroll run already exists — skipped.');
        }
    }
}
