<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthApiController,
    DashboardApiController,
    EmployeeApiController,
    AttendanceApiController,
    LeaveApiController,
    PayrollApiController,
    RecruitmentApiController,
    AiApiController,
    PerformanceApiController,
    TrainingApiController,
    MeetingApiController,
    ProfileApiController,
    NotificationApiController,
    AdminApiController,
    ClientPortalApiController,
    SelfServiceApiController,
};

// ============================================================
// PUBLIC — Auth
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('login',       [AuthApiController::class, 'login']);
    Route::post('mfa/verify',  [AuthApiController::class, 'mfaVerify']);
});

// ============================================================
// AUTHENTICATED
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('auth/logout', [AuthApiController::class, 'logout']);
    Route::get('auth/me',      [AuthApiController::class, 'me']);

    // Dashboard
    Route::get('dashboard', [DashboardApiController::class, 'index']);

    // Profile
    Route::get('profile',           [ProfileApiController::class, 'show']);
    Route::put('profile',           [ProfileApiController::class, 'update']);
    Route::put('profile/password',  [ProfileApiController::class, 'updatePassword']);
    Route::post('profile/avatar',   [ProfileApiController::class, 'updateAvatar']);

    // Employees
    Route::get('employees',              [EmployeeApiController::class, 'index']);
    Route::post('employees',             [EmployeeApiController::class, 'store']);
    Route::get('employees/{employee}',   [EmployeeApiController::class, 'show']);
    Route::put('employees/{employee}',   [EmployeeApiController::class, 'update']);
    Route::delete('employees/{employee}',[EmployeeApiController::class, 'destroy']);
    Route::get('employees/{employee}/documents', [EmployeeApiController::class, 'documents']);
    Route::post('employees/{employee}/documents',[EmployeeApiController::class, 'uploadDocument']);
    Route::get('departments',   [EmployeeApiController::class, 'departments']);
    Route::get('designations',  [EmployeeApiController::class, 'designations']);

    // Attendance
    Route::get('attendance',          [AttendanceApiController::class, 'index']);
    Route::get('attendance/today',    [AttendanceApiController::class, 'today']);
    Route::post('attendance/clock-in',[AttendanceApiController::class, 'clockIn']);
    Route::post('attendance/clock-out',[AttendanceApiController::class, 'clockOut']);
    Route::get('attendance/report',   [AttendanceApiController::class, 'report']);

    // Leaves
    Route::get('leaves',                   [LeaveApiController::class, 'index']);
    Route::post('leaves',                  [LeaveApiController::class, 'store']);
    Route::get('leaves/{leave}',           [LeaveApiController::class, 'show']);
    Route::put('leaves/{leave}',           [LeaveApiController::class, 'update']);
    Route::delete('leaves/{leave}',        [LeaveApiController::class, 'destroy']);
    Route::post('leaves/{leave}/approve',  [LeaveApiController::class, 'approve']);
    Route::post('leaves/{leave}/reject',   [LeaveApiController::class, 'reject']);
    Route::post('leaves/{leave}/cancel',   [LeaveApiController::class, 'cancel']);
    Route::get('leave-types',              [LeaveApiController::class, 'types']);
    Route::get('leave-balance',            [LeaveApiController::class, 'balance']);

    // Payroll
    Route::get('payroll',                  [PayrollApiController::class, 'index']);
    Route::get('payroll/{payroll}',        [PayrollApiController::class, 'show']);
    Route::post('payroll/{payroll}/process',[PayrollApiController::class, 'process']);
    Route::post('payroll/{payroll}/approve',[PayrollApiController::class, 'approve']);
    Route::get('payroll/{payroll}/payslips',[PayrollApiController::class, 'payslips']);
    Route::get('my-payslips',              [PayrollApiController::class, 'myPayslips']);

    // Recruitment - Jobs
    Route::get('recruitment/jobs',             [RecruitmentApiController::class, 'jobsIndex']);
    Route::post('recruitment/jobs',            [RecruitmentApiController::class, 'jobsStore']);
    Route::get('recruitment/jobs/{job}',       [RecruitmentApiController::class, 'jobsShow']);
    Route::put('recruitment/jobs/{job}',       [RecruitmentApiController::class, 'jobsUpdate']);
    Route::delete('recruitment/jobs/{job}',    [RecruitmentApiController::class, 'jobsDestroy']);

    // Recruitment - Candidates
    Route::get('recruitment/candidates',               [RecruitmentApiController::class, 'candidatesIndex']);
    Route::post('recruitment/candidates',              [RecruitmentApiController::class, 'candidatesStore']);
    Route::get('recruitment/candidates/{candidate}',   [RecruitmentApiController::class, 'candidatesShow']);
    Route::put('recruitment/candidates/{candidate}',   [RecruitmentApiController::class, 'candidatesUpdate']);
    Route::post('recruitment/candidates/{candidate}/offer',        [RecruitmentApiController::class, 'offerStore']);
    Route::post('recruitment/candidates/{candidate}/offer/accept', [RecruitmentApiController::class, 'offerAccept']);
    Route::post('recruitment/candidates/{candidate}/offer/reject', [RecruitmentApiController::class, 'offerReject']);

    // Recruitment - Interviews
    Route::get('recruitment/interviews',              [RecruitmentApiController::class, 'interviewsIndex']);
    Route::post('recruitment/interviews',             [RecruitmentApiController::class, 'interviewsStore']);
    Route::put('recruitment/interviews/{interview}',  [RecruitmentApiController::class, 'interviewsUpdate']);

    // AI Recruitment
    Route::post('recruitment/ai/score/{candidate}',     [AiApiController::class, 'scoreCandidate']);
    Route::post('recruitment/ai/shortlist/{job}',       [AiApiController::class, 'shortlistCandidates']);
    Route::post('recruitment/ai/questions/{candidate}', [AiApiController::class, 'interviewQuestions']);

    // Performance
    Route::get('performance',          [PerformanceApiController::class, 'index']);
    Route::get('kpis',                 [PerformanceApiController::class, 'kpis']);
    Route::get('performance/cycles',   [PerformanceApiController::class, 'cycles']);
    Route::get('goals',                [PerformanceApiController::class, 'goals']);
    Route::post('goals',               [PerformanceApiController::class, 'storeGoal']);
    Route::put('goals/{goal}',         [PerformanceApiController::class, 'updateGoal']);
    Route::delete('goals/{goal}',      [PerformanceApiController::class, 'destroyGoal']);

    // Training
    Route::get('training',                          [TrainingApiController::class, 'index']);
    Route::get('training/{training}',               [TrainingApiController::class, 'show']);
    Route::post('training/{training}/enroll',       [TrainingApiController::class, 'enroll']);
    Route::get('certifications',                    [TrainingApiController::class, 'certifications']);

    // Meetings
    Route::get('meetings',                   [MeetingApiController::class, 'index']);
    Route::post('meetings',                  [MeetingApiController::class, 'store']);
    Route::get('meetings/{meeting}',         [MeetingApiController::class, 'show']);
    Route::put('meetings/{meeting}',         [MeetingApiController::class, 'update']);
    Route::delete('meetings/{meeting}',      [MeetingApiController::class, 'destroy']);
    Route::post('meetings/{meeting}/rsvp',   [MeetingApiController::class, 'rsvp']);
    Route::get('calendar',                   [MeetingApiController::class, 'calendar']);

    // Notifications
    Route::get('notifications',               [NotificationApiController::class, 'index']);
    Route::post('notifications/read',         [NotificationApiController::class, 'markRead']);
    Route::delete('notifications/{id}',       [NotificationApiController::class, 'destroy']);

    // Employee self-service
    Route::get('my/documents',               [SelfServiceApiController::class, 'documents']);
    Route::post('my/documents',              [SelfServiceApiController::class, 'uploadDocument']);
    Route::delete('my/documents/{document}', [SelfServiceApiController::class, 'deleteDocument']);
    Route::put('my/nok',                     [SelfServiceApiController::class, 'updateNok']);

    // Reports (served by existing controllers)
    Route::get('reports/employees',   [EmployeeApiController::class, 'report']);
    Route::get('reports/attendance',  [AttendanceApiController::class, 'report']);
    Route::get('reports/leave',       [LeaveApiController::class, 'report']);

    // ============================================================
    // ADMIN ROUTES
    // ============================================================
    Route::prefix('admin')->group(function () {
        Route::get('users',                    [AdminApiController::class, 'usersIndex']);
        Route::post('users',                   [AdminApiController::class, 'usersStore']);
        Route::get('users/{user}',             [AdminApiController::class, 'usersShow']);
        Route::put('users/{user}',             [AdminApiController::class, 'usersUpdate']);
        Route::delete('users/{user}',          [AdminApiController::class, 'usersDestroy']);

        Route::get('departments',              [AdminApiController::class, 'departmentsIndex']);
        Route::post('departments',             [AdminApiController::class, 'departmentsStore']);
        Route::put('departments/{department}', [AdminApiController::class, 'departmentsUpdate']);
        Route::delete('departments/{department}',[AdminApiController::class, 'departmentsDestroy']);

        Route::get('roles', [AdminApiController::class, 'roles']);
        Route::get('audit', [AdminApiController::class, 'audit']);

        Route::get('clients',                  [AdminApiController::class, 'clientsIndex']);
        Route::post('clients',                 [AdminApiController::class, 'clientsStore']);
        Route::get('clients/{client}',         [AdminApiController::class, 'clientsShow']);
        Route::put('clients/{client}',         [AdminApiController::class, 'clientsUpdate']);
        Route::post('clients/{client}/assign-employee',                        [AdminApiController::class, 'clientsAssignEmployee']);
        Route::delete('clients/{client}/unassign-employee/{employee}',         [AdminApiController::class, 'clientsUnassignEmployee']);
        Route::post('clients/{client}/assign-job',                             [AdminApiController::class, 'clientsAssignJob']);
        Route::delete('clients/{client}/unassign-job/{job}',                   [AdminApiController::class, 'clientsUnassignJob']);
    });

    // ============================================================
    // CLIENT PORTAL ROUTES
    // ============================================================
    Route::prefix('client')->group(function () {
        Route::get('dashboard',                               [ClientPortalApiController::class, 'dashboard']);
        Route::get('jobs',                                    [ClientPortalApiController::class, 'assignedJobs']);
        Route::get('leaves',                                  [ClientPortalApiController::class, 'leaves']);
        Route::post('leaves/{leave}/approve',                 [ClientPortalApiController::class, 'approveLeave']);
        Route::post('leaves/{leave}/reject',                  [ClientPortalApiController::class, 'rejectLeave']);
        Route::get('recruitment',                             [ClientPortalApiController::class, 'recruitment']);
        Route::post('recruitment/{candidate}/approve',        [ClientPortalApiController::class, 'approveCandidate']);
        Route::post('recruitment/{candidate}/reject',         [ClientPortalApiController::class, 'rejectCandidate']);
    });
});
