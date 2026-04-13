<?php
namespace App\Providers;

use App\Models\{Employee, LeaveRequest, Payslip, User};
use App\Observers\{EmployeeObserver, LeaveRequestObserver, PayslipObserver, UserObserver};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Employee::observe(EmployeeObserver::class);
        LeaveRequest::observe(LeaveRequestObserver::class);
        Payslip::observe(PayslipObserver::class);
        User::observe(UserObserver::class);
    }
}
