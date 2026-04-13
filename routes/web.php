<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\{LoginController, MfaController};
use App\Http\Controllers\{DashboardController, ProfileController, AjaxController, EmployeeController, AttendanceController, LeaveController, PayrollController, RecruitmentController, PerformanceController, TrainingController, MeetingController, ReportController};
use App\Http\Controllers\Admin\{UserController, DepartmentController, SettingController, RoleController, AuditLogController, DocumentationController};
use App\Http\Controllers\Employee\{OnboardingController, ExitController};
use App\Http\Controllers\Performance\{GoalController, PipController};
use App\Http\Controllers\Training\AssessmentController;
use App\Http\Controllers\Recruitment\OfferController;

// Landing page (public)
Route::get('/', function () {
    if (auth()->check()) return redirect()->route('dashboard');
    return view('welcome');
})->name('home');

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// MFA challenge (no auth required — user is in mfa_pending state)
Route::get('/mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
Route::post('/mfa/verify', [MfaController::class, 'verify'])->name('mfa.verify');

Route::middleware(['auth','mfa'])->group(function () {

    // MFA setup (inside auth — user must be logged in to configure)
    Route::get('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/mfa/enable', [MfaController::class, 'enable'])->name('mfa.enable');
    Route::post('/mfa/disable', [MfaController::class, 'disable'])->name('mfa.disable');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::put('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');

    // AJAX
    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::get('employees/search', [AjaxController::class, 'searchEmployees'])->name('employees.search');
        Route::get('notifications', [AjaxController::class, 'notifications'])->name('notifications');
        Route::post('notifications/read', [AjaxController::class, 'markNotificationsRead'])->name('notifications.read');
        Route::get('charts/attendance', [AjaxController::class, 'attendanceChart'])->name('charts.attendance');
        Route::get('charts/headcount', [AjaxController::class, 'headcountChart'])->name('charts.headcount');
    });

    // Employees — HR/Admin/Manager only
    Route::middleware('role:super-admin|hr-admin|manager|payroll-officer')->group(function () {
        Route::resource('employees', EmployeeController::class);
        Route::get('employees/{employee}/documents', [EmployeeController::class, 'documents'])->name('employees.documents');
        Route::post('employees/{employee}/documents', [EmployeeController::class, 'storeDocument'])->name('employees.documents.store');
        Route::get('employees/{employee}/history', [EmployeeController::class, 'history'])->name('employees.history');
        Route::post('employees/{employee}/history', [EmployeeController::class, 'storeHistory'])->name('employees.history.store');
        // Onboarding
        Route::get('employees/{employee}/onboarding', [OnboardingController::class, 'index'])->name('employees.onboarding');
        Route::post('employees/{employee}/onboarding', [OnboardingController::class, 'store'])->name('employees.onboarding.store');
        Route::post('onboarding/{task}/complete', [OnboardingController::class, 'complete'])->name('employees.onboarding.complete');
        Route::delete('onboarding/{task}', [OnboardingController::class, 'destroy'])->name('employees.onboarding.destroy');
        // Exit workflow
        Route::get('employees/{employee}/exit', [ExitController::class, 'show'])->name('employees.exit.show');
        Route::get('employees/{employee}/exit/create', [ExitController::class, 'create'])->name('employees.exit.create');
        Route::post('employees/{employee}/exit', [ExitController::class, 'store'])->name('employees.exit.store');
        Route::put('employees/{employee}/exit', [ExitController::class, 'update'])->name('employees.exit.update');
    });

    // Attendance — all roles (employees see own, HR/manager see all — scoped in controller)
    Route::resource('attendance', AttendanceController::class);
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::get('attendance-report', [AttendanceController::class, 'report'])->name('attendance.report');
    Route::middleware('role:super-admin|hr-admin|manager')->group(function () {
        Route::get('shifts', [AttendanceController::class, 'shifts'])->name('attendance.shifts');
        Route::post('shifts', [AttendanceController::class, 'storeShift'])->name('attendance.shifts.store');
        Route::get('holidays', [AttendanceController::class, 'holidays'])->name('attendance.holidays');
        Route::post('holidays', [AttendanceController::class, 'storeHoliday'])->name('attendance.holidays.store');
    });

    // Leaves — all roles (employees see own only — scoped in controller)
    Route::resource('leaves', LeaveController::class);
    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
    Route::post('leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leaves.cancel');
    Route::get('leave-types', [LeaveController::class, 'types'])->name('leaves.types');
    Route::post('leave-types', [LeaveController::class, 'storeType'])->name('leaves.types.store');
    Route::get('leave-balance', [LeaveController::class, 'balance'])->name('leaves.balance');

    // Payroll — payroll-officer, hr-admin, super-admin only
    Route::middleware('role:super-admin|hr-admin|payroll-officer')->group(function () {
        Route::resource('payroll', PayrollController::class);
        Route::post('payroll/{payroll}/process', [PayrollController::class, 'process'])->name('payroll.process');
        Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
        Route::post('payroll/{payroll}/mark-paid', [PayrollController::class, 'markPaid'])->name('payroll.mark-paid');
        Route::get('payroll/{payroll}/payslips', [PayrollController::class, 'payslips'])->name('payroll.payslips');
        Route::get('payroll/{payroll}/bank-export', [PayrollController::class, 'bankExport'])->name('payroll.bank-export');
        Route::get('salary', [PayrollController::class, 'salaryIndex'])->name('salary.index');
        Route::get('salary/create', [PayrollController::class, 'salaryCreate'])->name('salary.create');
        Route::post('salary', [PayrollController::class, 'salaryStore'])->name('salary.store');
        Route::get('salary/{salary}', [PayrollController::class, 'salaryShow'])->name('salary.show');
        Route::get('salary/{salary}/edit', [PayrollController::class, 'salaryEdit'])->name('salary.edit');
        Route::put('salary/{salary}', [PayrollController::class, 'salaryUpdate'])->name('salary.update');
        Route::get('salary-grades', [PayrollController::class, 'gradesIndex'])->name('salary.grades');
        Route::post('salary-grades', [PayrollController::class, 'gradesStore'])->name('salary.grades.store');
        Route::get('salary-components', [PayrollController::class, 'componentsIndex'])->name('salary.components');
        Route::post('salary-components', [PayrollController::class, 'componentsStore'])->name('salary.components.store');
    });
    // Payslip PDF — employee can download own payslip only (controller scopes by employee_id)
    Route::get('payroll/{payroll}/payslips/{employee}/pdf', [PayrollController::class, 'payslipPdf'])->name('payroll.payslip.pdf');

    // Recruitment - Jobs
    Route::prefix('recruitment')->name('recruitment.')->group(function () {
        Route::get('jobs', [RecruitmentController::class, 'jobsIndex'])->name('jobs.index');
        Route::get('jobs/create', [RecruitmentController::class, 'jobsCreate'])->name('jobs.create');
        Route::post('jobs', [RecruitmentController::class, 'jobsStore'])->name('jobs.store');
        Route::get('jobs/{job}', [RecruitmentController::class, 'jobsShow'])->name('jobs.show');
        Route::get('jobs/{job}/edit', [RecruitmentController::class, 'jobsEdit'])->name('jobs.edit');
        Route::put('jobs/{job}', [RecruitmentController::class, 'jobsUpdate'])->name('jobs.update');
        Route::delete('jobs/{job}', [RecruitmentController::class, 'jobsDestroy'])->name('jobs.destroy');

        Route::get('candidates', [RecruitmentController::class, 'candidatesIndex'])->name('candidates.index');
        Route::get('candidates/create', [RecruitmentController::class, 'candidatesCreate'])->name('candidates.create');
        Route::post('candidates', [RecruitmentController::class, 'candidatesStore'])->name('candidates.store');
        Route::get('candidates/{candidate}', [RecruitmentController::class, 'candidatesShow'])->name('candidates.show');
        Route::put('candidates/{candidate}', [RecruitmentController::class, 'candidatesUpdate'])->name('candidates.update');
        Route::post('candidates/{candidate}/offer', [OfferController::class, 'store'])->name('candidates.offer.store');
        Route::post('candidates/{candidate}/offer/accept', [OfferController::class, 'accept'])->name('candidates.offer.accept');
        Route::post('candidates/{candidate}/offer/reject', [OfferController::class, 'reject'])->name('candidates.offer.reject');

        Route::get('interviews', [RecruitmentController::class, 'interviewsIndex'])->name('interviews.index');
        Route::get('interviews/create', [RecruitmentController::class, 'interviewsCreate'])->name('interviews.create');
        Route::post('interviews', [RecruitmentController::class, 'interviewsStore'])->name('interviews.store');
        Route::get('interviews/{interview}', [RecruitmentController::class, 'interviewsShow'])->name('interviews.show');
        Route::put('interviews/{interview}', [RecruitmentController::class, 'interviewsUpdate'])->name('interviews.update');
    });

    // Performance
    Route::get('performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::get('performance/create', [PerformanceController::class, 'create'])->name('performance.create');
    Route::post('performance', [PerformanceController::class, 'store'])->name('performance.store');
    Route::get('performance/{performance}', [PerformanceController::class, 'show'])->name('performance.show');
    Route::delete('performance/{performance}', [PerformanceController::class, 'destroy'])->name('performance.destroy');
    Route::get('kpis', [PerformanceController::class, 'kpis'])->name('performance.kpis');
    Route::post('kpis', [PerformanceController::class, 'storeKpi'])->name('performance.kpis.store');
    Route::delete('kpis/{kpi}', [PerformanceController::class, 'destroyKpi'])->name('performance.kpis.destroy');
    Route::get('performance-cycles', [PerformanceController::class, 'cyclesIndex'])->name('performance.cycles.index');
    Route::get('performance-cycles/create', [PerformanceController::class, 'cyclesCreate'])->name('performance.cycles.create');
    Route::post('performance-cycles', [PerformanceController::class, 'cyclesStore'])->name('performance.cycles.store');
    Route::get('performance-cycles/{cycle}/edit', [PerformanceController::class, 'cyclesEdit'])->name('performance.cycles.edit');
    Route::put('performance-cycles/{cycle}', [PerformanceController::class, 'cyclesUpdate'])->name('performance.cycles.update');
    // Goals
    Route::get('goals', [GoalController::class, 'index'])->name('goals.index');
    Route::post('goals', [GoalController::class, 'store'])->name('goals.store');
    Route::put('goals/{goal}', [GoalController::class, 'update'])->name('goals.update');
    Route::put('goals/{goal}/progress', [GoalController::class, 'updateProgress'])->name('goals.progress');
    Route::delete('goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');
    // PIPs
    Route::get('pips', [PipController::class, 'index'])->name('pips.index');
    Route::get('pips/create', [PipController::class, 'create'])->name('pips.create');
    Route::post('pips', [PipController::class, 'store'])->name('pips.store');
    Route::get('pips/{pip}', [PipController::class, 'show'])->name('pips.show');
    Route::put('pips/{pip}', [PipController::class, 'update'])->name('pips.update');
    Route::delete('pips/{pip}', [PipController::class, 'destroy'])->name('pips.destroy');

    // Training
    Route::resource('training', TrainingController::class);
    Route::post('training/{training}/enroll', [TrainingController::class, 'enroll'])->name('training.enroll');
    Route::post('training/enrollment/{enrollment}/progress', [TrainingController::class, 'updateProgress'])->name('training.progress');
    Route::post('training/{training}/assessments', [AssessmentController::class, 'store'])->name('training.assessments.store');
    Route::get('certifications', [TrainingController::class, 'certifications'])->name('training.certifications');
    Route::post('certifications', [TrainingController::class, 'storeCertification'])->name('training.certifications.store');

    // Meetings
    Route::resource('meetings', MeetingController::class);
    Route::post('meetings/{meeting}/rsvp', [MeetingController::class, 'rsvp'])->name('meetings.rsvp');
    Route::post('meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
    Route::get('calendar', [MeetingController::class, 'calendar'])->name('meetings.calendar');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('employees', [ReportController::class, 'employees'])->name('employees');
        Route::get('attendance', [ReportController::class, 'attendance'])->name('attendance');
        Route::get('leave', [ReportController::class, 'leave'])->name('leave');
        Route::get('payroll', [ReportController::class, 'payroll'])->name('payroll');
        Route::get('performance', [ReportController::class, 'performance'])->name('performance');
        Route::get('training', [ReportController::class, 'training'])->name('training');
        Route::get('export', [ReportController::class, 'export'])->name('export');
    });

    // Export routes
    Route::get('export/employees', [ReportController::class, 'exportEmployees'])->name('export.employees');
    Route::get('export/attendance', [ReportController::class, 'exportAttendance'])->name('export.attendance');
    Route::get('export/leave', [ReportController::class, 'exportLeave'])->name('export.leave');
    // Import routes
    Route::get('import', [ReportController::class, 'importForm'])->name('import.form');
    Route::post('import/employees', [ReportController::class, 'importEmployees'])->name('import.employees');

    // Admin
    Route::prefix('admin')->name('admin.')->middleware('role:super-admin|hr-admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
        Route::post('designations', [DepartmentController::class, 'storeDesignation'])->name('designations.store');
        Route::delete('designations/{designation}', [DepartmentController::class, 'destroyDesignation'])->name('designations.destroy');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        // Roles
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        // Audit logs
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        // System documentation PDF
        Route::get('documentation/pdf', [DocumentationController::class, 'pdf'])->name('documentation.pdf');
    });
});