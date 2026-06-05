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

// Document & certification expiry alerts (Phase 4) — daily at 08:15
Schedule::command('hrms:document-expiry-alerts')->dailyAt('08:15');

// Contract expiry alerts — daily at 08:30 (30-day and 15-day warnings to AM + HR)
Schedule::command('hrms:contract-expiry-alerts')->dailyAt('08:30');

// Auto-release employee holds whose end date has passed — daily at 00:10
Schedule::command('hrms:auto-release-holds')->dailyAt('00:10');

// Auto-terminate employees whose end_date or contract_end_date has passed — daily at 00:15
Schedule::command('hrms:auto-terminate-employees')->dailyAt('00:15');

// Recurring meeting instances — generate daily at 01:00
Schedule::command('hrms:generate-recurring-meetings')->dailyAt('01:00');
