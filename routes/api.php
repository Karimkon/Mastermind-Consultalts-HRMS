<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthApiController,
    ChangeApprovalApiController,
    ChatApiController,
    DashboardApiController,
    EmployeeApiController,
    AttendanceApiController,
    OfficeAttendanceApiController,
    OvertimeApiController,
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
    AmVisitApiController,
    BscApiController,
    AppraisalApiController,
    HolidayPayApiController,
    DevelopmentApiController,
    ConfigurationApiController,
    PublicHolidayApiController,
    ProbationApiController,
    AccountManagerApiController,
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

    // Staff messaging. The same threads, service and rules as the web panel -
    // only the way the caller proves who they are differs, and the attachment
    // URL with it, because a bearer token cannot be used against a route that
    // expects a session cookie.
    Route::prefix('chat')->group(function () {
        Route::get('/',                       [ChatApiController::class, 'index']);
        Route::get('unread',                  [ChatApiController::class, 'unread']);
        Route::get('contacts',                [ChatApiController::class, 'contacts']);
        Route::post('with/{user}',            [ChatApiController::class, 'withUser']);
        Route::get('attachments/{message}',   [ChatApiController::class, 'attachment']);
        Route::delete('messages/{message}',   [ChatApiController::class, 'destroy']);
        // Last, so 'unread' and 'contacts' are not swallowed by {conversation}.
        Route::get('{conversation}/messages', [ChatApiController::class, 'messages']);
        Route::post('{conversation}/send',    [ChatApiController::class, 'send']);
    });

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
    Route::post('payroll',                 [PayrollApiController::class, 'store']);
    Route::get('payroll/{payroll}',        [PayrollApiController::class, 'show']);
    Route::post('payroll/{payroll}/process',[PayrollApiController::class, 'process']);
    // Payroll approval is three stages by three different roles, and the phone
    // enforces the same order the web does. `approve` is the MD's final release
    // and is the only step that locks the run.
    Route::post('payroll/{payroll}/hr-approve', [PayrollApiController::class, 'hrApprove']);
    Route::post('payroll/{payroll}/finance-approve', [PayrollApiController::class, 'financeApprove']);
    Route::post('payroll/{payroll}/approve',[PayrollApiController::class, 'approve']);
    Route::get('payroll/{payroll}/payslips',[PayrollApiController::class, 'payslips']);
    Route::get('my-payslips',              [PayrollApiController::class, 'myPayslips']);
    Route::get('my-payslips/{payslip}/pdf',[PayrollApiController::class, 'downloadPayslipPdf']);

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

    // Change approvals — HR's queue of edits account managers have proposed.
    // Same role gate as the web screen; the controller re-checks.
    Route::get('change-approvals',                    [ChangeApprovalApiController::class, 'index']);
    Route::get('change-approvals/{change}',           [ChangeApprovalApiController::class, 'show']);
    Route::post('change-approvals/{change}/approve',  [ChangeApprovalApiController::class, 'approve']);
    Route::post('change-approvals/{change}/reject',   [ChangeApprovalApiController::class, 'reject']);

    // Training
    Route::get('training',                          [TrainingApiController::class, 'index']);
    Route::get('training/{training}',               [TrainingApiController::class, 'show']);
    Route::post('training/{training}/enroll',       [TrainingApiController::class, 'enroll']);
    Route::put('training/{training}/progress',      [TrainingApiController::class, 'updateProgress']);
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
    Route::get('reports/payroll',     [PayrollApiController::class, 'report']);
    Route::get('reports/performance', [PerformanceApiController::class, 'report']);
    Route::get('reports/training',    [TrainingApiController::class, 'report']);

    // ============================================================
    // OFFICE ATTENDANCE — head-office presence register.
    // Keyed on the user, so account managers (who have no employee record) can
    // use it. Never read by payroll.
    // ============================================================
    Route::get('office-attendance',            [OfficeAttendanceApiController::class, 'index']);
    Route::get('office-attendance/today',      [OfficeAttendanceApiController::class, 'today']);
    Route::post('office-attendance/clock-in',  [OfficeAttendanceApiController::class, 'clockIn']);
    Route::post('office-attendance/clock-out', [OfficeAttendanceApiController::class, 'clockOut']);

    // ============================================================
    // OVERTIME APPROVAL — payroll pays only what is approved here.
    // ============================================================
    Route::get('overtime',                [OvertimeApiController::class, 'index']);
    Route::post('overtime/{log}/approve', [OvertimeApiController::class, 'approve']);
    Route::post('overtime/{log}/reject',  [OvertimeApiController::class, 'reject']);

    // ============================================================
    // AM SITE VISITS
    // ============================================================
    Route::get('am-visits',              [AmVisitApiController::class, 'index']);
    Route::post('am-visits/clock-in',    [AmVisitApiController::class, 'clockIn']);
    Route::post('am-visits/clock-out',   [AmVisitApiController::class, 'clockOut']);
    Route::get('am-visits/active',       [AmVisitApiController::class, 'activeSessions']);
    Route::get('am-visits/clients',      [AmVisitApiController::class, 'clients']);

    // Account Manager — Employees, Leaves & Payroll
    Route::prefix('account-manager')->group(function () {
        Route::get('clients',                          [AccountManagerApiController::class, 'clients']);
        Route::get('employees',                        [AccountManagerApiController::class, 'employees']);
        Route::get('leaves',                           [AccountManagerApiController::class, 'leaves']);
        Route::post('leaves/{leave}/approve',          [AccountManagerApiController::class, 'approveLeave']);
        Route::post('leaves/{leave}/reject',           [AccountManagerApiController::class, 'rejectLeave']);
        Route::get('payroll',                          [AccountManagerApiController::class, 'payroll']);
        Route::get('payroll/{run}/payslips',           [AccountManagerApiController::class, 'payrollPayslips']);
        Route::get('salary-payments',                  [AccountManagerApiController::class, 'salaryPayments']);
        Route::post('salary-payments',                 [AccountManagerApiController::class, 'storeSalaryPayment']);
        Route::delete('salary-payments/{payment}',     [AccountManagerApiController::class, 'deleteSalaryPayment']);
    });

    // Admin — Payroll unlock
    Route::post('admin/payroll/{payroll}/unlock',      [AdminApiController::class, 'unlockPayroll']);

    // ============================================================
    // PUBLIC HOLIDAY CALENDAR
    // ============================================================
    // Feeds pay in two directions: monthly staff have holidays counted toward
    // their worked days, casual staff earn double for an approved one they
    // worked. Deleting is guarded because holiday_pay_approvals and holiday_work
    // both cascade — removing a used holiday erases the decision and the record
    // of who worked it.
    Route::get('public-holidays',                        [PublicHolidayApiController::class, 'index']);
    Route::post('public-holidays',                       [PublicHolidayApiController::class, 'store']);
    Route::post('public-holidays/seed',                  [PublicHolidayApiController::class, 'seedYear']);
    Route::delete('public-holidays/{holiday}',           [PublicHolidayApiController::class, 'destroy']);

    // ============================================================
    // CONFIGURATION — shifts, salary grades, salary components
    // ============================================================
    // Reading is open to anybody signed in: a supervisor should be able to check
    // a grace period without asking. Writing is HR's and payroll's, and the MD is
    // deliberately absent — whoever signs payroll off should not set its inputs.
    // Nothing is deletable; a component that has been used explains an old
    // payslip, so deactivating is the honest equivalent.
    Route::get('shifts',                                 [ConfigurationApiController::class, 'shifts']);
    Route::post('shifts',                                [ConfigurationApiController::class, 'storeShift']);
    Route::get('salary-grades',                          [ConfigurationApiController::class, 'grades']);
    Route::post('salary-grades',                         [ConfigurationApiController::class, 'storeGrade']);
    Route::get('salary-components',                      [ConfigurationApiController::class, 'components']);
    Route::post('salary-components',                     [ConfigurationApiController::class, 'storeComponent']);
    Route::post('salary-components/{component}/active',  [ConfigurationApiController::class, 'setComponentActive']);

    // ============================================================
    // HOLIDAY PAY
    // ============================================================
    // Two decisions by two people: HR says whether a holiday is paid, the account
    // manager says who actually worked it. Payroll pays double only where both
    // are true. Only casual rates appear in the roster — a monthly salary already
    // covers the day.
    Route::get('holiday-pay',                            [HolidayPayApiController::class, 'index']);
    Route::post('holiday-pay/{holiday}/decide',          [HolidayPayApiController::class, 'decide']);
    Route::get('holiday-pay/{holiday}/roster',           [HolidayPayApiController::class, 'roster']);
    Route::post('holiday-pay/{holiday}/work',            [HolidayPayApiController::class, 'storeWork']);

    // ============================================================
    // ONBOARDING & PIPs
    // ============================================================
    Route::get('onboarding',                             [DevelopmentApiController::class, 'onboarding']);
    Route::post('onboarding/{task}/complete',            [DevelopmentApiController::class, 'completeOnboardingTask']);
    Route::get('pips',                                   [DevelopmentApiController::class, 'pips']);

    // ============================================================
    // APPRAISALS (per-employee cards)
    // ============================================================
    // The scheme the BSC cycles below were replaced by. A card moves
    // draft -> with_appraiser -> with_manager -> with_employee -> completed,
    // and every guard here is the web controller's, not a reinterpretation of it.
    // Setting KPIs is absent on purpose: weights must total 100 across a table of
    // targets, which is deskwork.
    Route::get('appraisals',                         [AppraisalApiController::class, 'index']);
    Route::get('appraisals/mine',                    [AppraisalApiController::class, 'mine']);
    Route::get('appraisals/{appraisal}',             [AppraisalApiController::class, 'show']);
    // The employee's own account, before anybody rates them. Saving and
    // submitting are separate so a long card can be filled in over days.
    Route::post('appraisals/{appraisal}/self-assessment',        [AppraisalApiController::class, 'saveSelfAssessment']);
    Route::post('appraisals/{appraisal}/self-assessment/submit', [AppraisalApiController::class, 'submitSelfAssessment']);
    Route::post('appraisals/{appraisal}/score',      [AppraisalApiController::class, 'score']);
    Route::post('appraisals/{appraisal}/return',     [AppraisalApiController::class, 'returnToManager']);
    Route::post('appraisals/{appraisal}/confirm',    [AppraisalApiController::class, 'confirm']);
    Route::post('appraisals/{appraisal}/send-back',  [AppraisalApiController::class, 'sendBack']);
    Route::post('appraisals/{appraisal}/self',       [AppraisalApiController::class, 'selfAppraise']);

    // ============================================================
    // BSC APPRAISALS (superseded by the above; kept until the old cycles are
    // archived, since both sets of tables still exist)
    // ============================================================
    Route::get('bsc/cycles',                         [BscApiController::class, 'cycles']);
    Route::get('bsc/cycles/{cycle}',                 [BscApiController::class, 'showCycle']);
    Route::get('bsc/my-appraisal',                   [BscApiController::class, 'myAppraisal']);
    Route::get('bsc/team-appraisal',                 [BscApiController::class, 'teamAppraisal']);
    Route::get('bsc/entries/{entry}',                [BscApiController::class, 'showEntry']);
    Route::put('bsc/entries/{entry}',                [BscApiController::class, 'updateEntry']);
    Route::post('bsc/entries/{entry}/submit',        [BscApiController::class, 'submitEntry']);
    Route::post('bsc/entries/{entry}/approve',       [BscApiController::class, 'approveEntry']);

    // ============================================================
    // PROBATION
    // ============================================================
    Route::get('probation',                              [ProbationApiController::class, 'index']);
    Route::get('probation/{employee}',                   [ProbationApiController::class, 'show']);
    Route::post('probation/{employee}/set-end',          [ProbationApiController::class, 'setProbationEnd']);
    Route::post('probation/{employee}/confirm',          [ProbationApiController::class, 'confirm']);

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


/*
|--------------------------------------------------------------------------
| Blog publishing API (used by the AI writer)
|--------------------------------------------------------------------------
| Authenticated with a key from Admin -> Blog -> AI publishing keys:
|   Authorization: Bearer bmb_xxxxxxxx
| Every published post becomes a real, indexable page at /blog/{slug}.
*/
Route::prefix('blog')->name('api.blog.')->middleware('throttle:60,1')->group(function () {
    Route::get('/posts', [App\Http\Controllers\Api\BlogPublishController::class, 'index'])->name('index');
    Route::post('/posts', [App\Http\Controllers\Api\BlogPublishController::class, 'store'])->name('store');
    Route::get('/posts/{post}', [App\Http\Controllers\Api\BlogPublishController::class, 'show'])->name('show');
    Route::match(['put', 'patch'], '/posts/{post}', [App\Http\Controllers\Api\BlogPublishController::class, 'update'])->name('update');
    Route::delete('/posts/{post}', [App\Http\Controllers\Api\BlogPublishController::class, 'destroy'])->name('destroy');
    Route::post('/media', [App\Http\Controllers\Api\BlogPublishController::class, 'uploadMedia'])->name('media');
    Route::get('/categories', [App\Http\Controllers\Api\BlogPublishController::class, 'categories'])->name('categories');
});

/*
|--------------------------------------------------------------------------
| Blog feed for the mobile apps (public, read-only)
|--------------------------------------------------------------------------
*/
Route::prefix('blog')->name('api.blog.feed.')->group(function () {
    Route::get('/articles', [App\Http\Controllers\Api\BlogFeedController::class, 'index'])->name('index');
    Route::get('/topics', [App\Http\Controllers\Api\BlogFeedController::class, 'categories'])->name('topics');
    Route::get('/articles/{slug}', [App\Http\Controllers\Api\BlogFeedController::class, 'show'])->name('show');
});

/*
|--------------------------------------------------------------------------
| Careers app — external job seekers
|--------------------------------------------------------------------------
|
| A separate population on a separate guard. Nothing in this group can reach
| an employee record, a payslip or a role: `auth:job_seeker` only resolves
| tokens whose owner is a JobSeeker, and a JobSeeker has none of those things.
|
| The browse endpoints are deliberately open. Somebody who has just installed
| the app should see the jobs before being asked to create an account.
*/
Route::prefix('careers')->name('api.careers.')->group(function () {

    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/jobs', [App\Http\Controllers\Api\CareersApiController::class, 'jobs'])->name('jobs');
        Route::get('/jobs/{job}', [App\Http\Controllers\Api\CareersApiController::class, 'job'])->name('job');
        Route::get('/categories', [App\Http\Controllers\Api\CareersApiController::class, 'categories'])->name('categories');
        Route::get('/track/{code}', [App\Http\Controllers\Api\CareersApiController::class, 'track'])->name('track');
    });

    // Tighter limits on the two endpoints worth guessing at.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\CareersApiController::class, 'register'])->name('register');
        Route::post('/login', [App\Http\Controllers\Api\CareersApiController::class, 'login'])->name('login');
    });

    Route::middleware('auth:job_seeker')->group(function () {
        Route::get('/me', [App\Http\Controllers\Api\CareersApiController::class, 'me'])->name('me');
        Route::put('/me', [App\Http\Controllers\Api\CareersApiController::class, 'updateMe'])->name('me.update');
        Route::post('/logout', [App\Http\Controllers\Api\CareersApiController::class, 'logout'])->name('logout');

        Route::post('/jobs/{job}/apply', [App\Http\Controllers\Api\CareersApiController::class, 'apply'])->name('apply');
        Route::get('/applications', [App\Http\Controllers\Api\CareersApiController::class, 'applications'])->name('applications');
        Route::get('/applications/{candidate}', [App\Http\Controllers\Api\CareersApiController::class, 'application'])->name('application');

        Route::get('/notifications', [App\Http\Controllers\Api\CareersApiController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read', [App\Http\Controllers\Api\CareersApiController::class, 'markRead'])->name('notifications.read');
    });
});
