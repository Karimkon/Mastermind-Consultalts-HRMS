<?php
namespace App\Exports;

use App\Models\LeaveRequest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeaveExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return LeaveRequest::with(['employee', 'leaveType'])->orderByDesc('created_at')->get();
    }

    public function headings(): array
    {
        return ['Employee', 'Leave Type', 'From', 'To', 'Days', 'Status', 'Reason'];
    }

    public function map($leave): array
    {
        $emp = $leave->employee;
        return [
            $emp ? $emp->first_name . ' ' . $emp->last_name : '',
            $leave->leaveType?->name ?? '',
            $leave->from_date,
            $leave->to_date,
            $leave->days_count,
            $leave->status,
            $leave->reason,
        ];
    }
}
