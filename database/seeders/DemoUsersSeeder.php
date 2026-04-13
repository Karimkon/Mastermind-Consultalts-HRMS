<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{User, Employee, Department, Designation};
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Departments 1-6
        $deptIds = Department::pluck('id', 'name');
        $hrDeptId = $deptIds->first() ?? 1;

        // -----------------------------------------------
        // ROLE USERS (super-admin and hr-admin skip if exist)
        // -----------------------------------------------

        // 1. super-admin (already exists — skip)
        $admin = User::firstOrCreate(
            ['email' => 'admin@mastermind.co.za'],
            ['name' => 'System Admin', 'password' => Hash::make('Admin@1234'), 'status' => 'active']
        );
        $admin->syncRoles('super-admin');

        // 2. hr-admin (already exists — skip)
        $hr = User::firstOrCreate(
            ['email' => 'hr@mastermind.co.za'],
            ['name' => 'Sarah Johnson', 'password' => Hash::make('Hr@1234'), 'status' => 'active']
        );
        $hr->syncRoles('hr-admin');

        // 3. manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@mastermind.co.za'],
            ['name' => 'David Nkosi', 'password' => Hash::make('Manager@1234'), 'status' => 'active']
        );
        $manager->syncRoles('manager');
        $this->createEmployee($manager, 'David', 'Nkosi', 'EMP0003', 3); // Engineering dept

        // 4. payroll-officer
        $payroll = User::firstOrCreate(
            ['email' => 'payroll@mastermind.co.za'],
            ['name' => 'Grace Dlamini', 'password' => Hash::make('Payroll@1234'), 'status' => 'active']
        );
        $payroll->syncRoles('payroll-officer');
        $this->createEmployee($payroll, 'Grace', 'Dlamini', 'EMP0004', 2); // Finance dept

        // 5. recruiter
        $recruiter = User::firstOrCreate(
            ['email' => 'recruiter@mastermind.co.za'],
            ['name' => 'Thabo Mokoena', 'password' => Hash::make('Recruit@1234'), 'status' => 'active']
        );
        $recruiter->syncRoles('recruiter');
        $this->createEmployee($recruiter, 'Thabo', 'Mokoena', 'EMP0005', 1); // HR dept

        // 6. employee
        $emp = User::firstOrCreate(
            ['email' => 'employee@mastermind.co.za'],
            ['name' => 'Sipho Williams', 'password' => Hash::make('Employee@1234'), 'status' => 'active']
        );
        $emp->syncRoles('employee');
        $this->createEmployee($emp, 'Sipho', 'Williams', 'EMP0006', 6); // Sales dept

        // -----------------------------------------------
        // 8-10 ADDITIONAL DEMO EMPLOYEES
        // -----------------------------------------------
        $additionalEmployees = [
            ['first' => 'Nomvula',  'last' => 'Khumalo',  'email' => 'nomvula@mastermind.co.za',  'num' => 'EMP0007', 'dept' => 1, 'gender' => 'female'],
            ['first' => 'Lungelo',  'last' => 'Zulu',     'email' => 'lungelo@mastermind.co.za',   'num' => 'EMP0008', 'dept' => 2, 'gender' => 'male'],
            ['first' => 'Amahle',   'last' => 'Ndlovu',   'email' => 'amahle@mastermind.co.za',    'num' => 'EMP0009', 'dept' => 3, 'gender' => 'female'],
            ['first' => 'Bongani',  'last' => 'Sithole',  'email' => 'bongani@mastermind.co.za',   'num' => 'EMP0010', 'dept' => 4, 'gender' => 'male'],
            ['first' => 'Zanele',   'last' => 'Mthembu',  'email' => 'zanele@mastermind.co.za',    'num' => 'EMP0011', 'dept' => 5, 'gender' => 'female'],
            ['first' => 'Sifiso',   'last' => 'Dube',     'email' => 'sifiso@mastermind.co.za',    'num' => 'EMP0012', 'dept' => 6, 'gender' => 'male'],
            ['first' => 'Lerato',   'last' => 'Molefe',   'email' => 'lerato@mastermind.co.za',    'num' => 'EMP0013', 'dept' => 3, 'gender' => 'female'],
            ['first' => 'Mandla',   'last' => 'Ntuli',    'email' => 'mandla@mastermind.co.za',    'num' => 'EMP0014', 'dept' => 2, 'gender' => 'male'],
            ['first' => 'Precious', 'last' => 'Mahlangu', 'email' => 'precious@mastermind.co.za', 'num' => 'EMP0015', 'dept' => 4, 'gender' => 'female'],
            ['first' => 'Sibusiso', 'last' => 'Mhlongo',  'email' => 'sibusiso@mastermind.co.za', 'num' => 'EMP0016', 'dept' => 5, 'gender' => 'male'],
        ];

        foreach ($additionalEmployees as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['first'] . ' ' . $data['last'],
                    'password' => Hash::make('Employee@1234'),
                    'status'   => 'active',
                ]
            );
            $user->syncRoles('employee');
            $this->createEmployee($user, $data['first'], $data['last'], $data['num'], $data['dept'], $data['gender'] ?? null);
        }

        $this->command->info('Demo users and employees created successfully!');
    }

    private function createEmployee(User $user, string $first, string $last, string $empNum, int $deptId, ?string $gender = null): void
    {
        if (Employee::where('user_id', $user->id)->exists()) {
            return;
        }

        // Skip if emp_number already taken
        if (Employee::where('emp_number', $empNum)->exists()) {
            return;
        }

        try {
            Employee::create([
                'user_id'          => $user->id,
                'emp_number'       => $empNum,
                'first_name'       => $first,
                'last_name'        => $last,
                'department_id'    => $deptId,
                'designation_id'   => null,
                'hire_date'        => now()->subMonths(rand(6, 36))->format('Y-m-d'),
                'employment_type'  => 'full_time',
                'status'           => 'active',
                'gender'           => $gender,
                'phone'            => '+27 ' . rand(60, 82) . ' ' . rand(100, 999) . ' ' . rand(1000, 9999),
                'country'          => 'South Africa',
            ]);
        } catch (\Exception $e) {
            $this->command->warn("Could not create employee for {$first} {$last}: " . $e->getMessage());
        }
    }
}
