<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Department;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'employees.view','employees.create','employees.edit','employees.delete',
            'employees.documents','employees.history',
            'attendance.view','attendance.manage','attendance.clock',
            'leave.view','leave.apply','leave.approve','leave.manage',
            'payroll.view','payroll.process','payroll.approve','payroll.manage',
            'recruitment.view','recruitment.manage','recruitment.interview',
            'performance.view','performance.manage','performance.review',
            'training.view','training.manage','training.enroll',
            'meetings.view','meetings.manage',
            'reports.view','reports.export',
            'admin.users','admin.roles','admin.settings','admin.audit',
            'client-portal','client-leave-approve','client-shortlist',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());

        $hrAdmin = Role::firstOrCreate(['name' => 'hr-admin']);
        $hrAdmin->syncPermissions([
            'employees.view','employees.create','employees.edit','employees.delete',
            'employees.documents','employees.history',
            'attendance.view','attendance.manage',
            'leave.view','leave.apply','leave.approve','leave.manage',
            'payroll.view','payroll.process','payroll.manage',
            'recruitment.view','recruitment.manage','recruitment.interview',
            'performance.view','performance.manage','performance.review',
            'training.view','training.manage','training.enroll',
            'meetings.view','meetings.manage',
            'reports.view','reports.export',
        ]);

        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'employees.view',
            'attendance.view','attendance.manage',
            'leave.view','leave.approve',
            'payroll.view',
            'performance.view','performance.review',
            'training.view',
            'meetings.view','meetings.manage',
            'reports.view',
        ]);

        $payrollOfficer = Role::firstOrCreate(['name' => 'payroll-officer']);
        $payrollOfficer->syncPermissions([
            'employees.view',
            'payroll.view','payroll.process','payroll.approve','payroll.manage',
            'reports.view','reports.export',
        ]);

        $recruiter = Role::firstOrCreate(['name' => 'recruiter']);
        $recruiter->syncPermissions([
            'recruitment.view','recruitment.manage','recruitment.interview',
            'reports.view',
        ]);

        $employee = Role::firstOrCreate(['name' => 'employee']);
        $employee->syncPermissions([
            'attendance.clock',
            'leave.view','leave.apply',
            'payroll.view',
            'performance.view','performance.review',
            'training.view','training.enroll',
            'meetings.view',
        ]);

        // Default departments
        $depts = [
            ['name' => 'Human Resources',  'code' => 'HR'],
            ['name' => 'Finance',           'code' => 'FIN'],
            ['name' => 'Engineering',       'code' => 'ENG'],
            ['name' => 'Marketing',         'code' => 'MKT'],
            ['name' => 'Operations',        'code' => 'OPS'],
            ['name' => 'Sales',             'code' => 'SAL'],
        ];
        foreach ($depts as $dept) {
            Department::firstOrCreate(['name' => $dept['name']], array_merge($dept, ['is_active' => true]));
        }

        // Default leave types
        $leaveTypes = [
            ['name' => 'Annual Leave',    'code' => 'AL',  'days_allowed' => 21, 'is_paid' => true,  'color' => '#3b82f6'],
            ['name' => 'Sick Leave',      'code' => 'SL',  'days_allowed' => 10, 'is_paid' => true,  'color' => '#ef4444'],
            ['name' => 'Maternity Leave', 'code' => 'ML',  'days_allowed' => 90, 'is_paid' => true,  'color' => '#ec4899'],
            ['name' => 'Paternity Leave', 'code' => 'PL',  'days_allowed' => 5,  'is_paid' => true,  'color' => '#8b5cf6'],
            ['name' => 'Unpaid Leave',    'code' => 'UL',  'days_allowed' => 30, 'is_paid' => false, 'color' => '#6b7280'],
            ['name' => 'Study Leave',     'code' => 'STL', 'days_allowed' => 5,  'is_paid' => true,  'color' => '#f59e0b'],
        ];
        foreach ($leaveTypes as $lt) {
            \App\Models\LeaveType::firstOrCreate(['code' => $lt['code']], $lt);
        }

        // Default shifts
        \App\Models\Shift::firstOrCreate(['name' => 'Morning Shift'],   ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'grace_minutes' => 15]);
        \App\Models\Shift::firstOrCreate(['name' => 'Afternoon Shift'], ['start_time' => '12:00:00', 'end_time' => '21:00:00', 'grace_minutes' => 15]);
        \App\Models\Shift::firstOrCreate(['name' => 'Night Shift'],     ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'grace_minutes' => 15]);

        // Default settings
        $settings = [
            ['key' => 'company_name',   'value' => 'Mastermind Consultants', 'group' => 'general', 'label' => 'Company Name'],
            ['key' => 'company_email',  'value' => 'hr@mastermind.co.za',    'group' => 'general', 'label' => 'HR Email'],
            ['key' => 'company_phone',  'value' => '+27 11 000 0000',         'group' => 'general', 'label' => 'Phone'],
            ['key' => 'currency',       'value' => 'ZAR',                     'group' => 'payroll', 'label' => 'Currency'],
            ['key' => 'currency_symbol','value' => 'R',                       'group' => 'payroll', 'label' => 'Currency Symbol'],
            ['key' => 'tax_rate',       'value' => '25',                      'group' => 'payroll', 'label' => 'Default Tax Rate (%)'],
            ['key' => 'financial_year', 'value' => 'March',                   'group' => 'payroll', 'label' => 'Financial Year Start'],
        ];
        foreach ($settings as $s) {
            \App\Models\Setting::firstOrCreate(['key' => $s['key']], $s);
        }

        // Super Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@mastermind.co.za'],
            ['name' => 'System Admin', 'password' => bcrypt('Admin@1234'), 'status' => 'active']
        );
        $admin->syncRoles('super-admin');

        // HR Admin demo user
        $hr = User::firstOrCreate(
            ['email' => 'hr@mastermind.co.za'],
            ['name' => 'Sarah Johnson', 'password' => bcrypt('Hr@1234'), 'status' => 'active']
        );
        $hr->syncRoles('hr-admin');

        $client = Role::firstOrCreate(['name' => 'client']);
        $client->syncPermissions(['client-portal', 'client-leave-approve', 'client-shortlist']);

        $this->command->info('Roles, permissions, and seed data created!');
    }
}
