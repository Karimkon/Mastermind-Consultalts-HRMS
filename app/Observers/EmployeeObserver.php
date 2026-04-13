<?php
namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Employee;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'created',
            'model_type' => 'Employee',
            'model_id'   => $employee->id,
            'new_values' => json_encode($employee->toArray()),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function updated(Employee $employee): void
    {
        if (empty($employee->getDirty())) return;
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'updated',
            'model_type' => 'Employee',
            'model_id'   => $employee->id,
            'old_values' => json_encode($employee->getOriginal()),
            'new_values' => json_encode($employee->getDirty()),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function deleted(Employee $employee): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'deleted',
            'model_type' => 'Employee',
            'model_id'   => $employee->id,
            'old_values' => json_encode($employee->toArray()),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
