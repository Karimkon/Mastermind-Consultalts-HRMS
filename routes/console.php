<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Leave balances — seed every January 1st at 00:30
Schedule::command('hrms:seed-leave-balances')->yearlyOn(1, 1, '00:30');

// Auto-resume employee status after leave ends — run daily at 00:05
Schedule::command('hrms:auto-resume-leave-status')->dailyAt('00:05');

// Certification expiry alerts — daily at 08:00
Schedule::command('hrms:cert-expiry-alert')->dailyAt('08:00');

// Recurring meeting instances — generate daily at 01:00
Schedule::command('hrms:generate-recurring-meetings')->dailyAt('01:00');
