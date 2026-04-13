<?php
namespace App\Exports;

use App\Models\AttendanceLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        return AttendanceLog::with('employee')
            ->when($this->filters['employee_id'] ?? null, fn($q, $v) => $q->where('employee_id', $v))
            ->when($this->filters['month'] ?? null, fn($q, $v) => $q->whereMonth('date', $v))
            ->when($this->filters['year'] ?? null, fn($q, $v) => $q->whereYear('date', $v))
            ->orderBy('date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return ['Emp No', 'Name', 'Date', 'Clock In', 'Clock Out', 'Status', 'Overtime Hours'];
    }

    public function map($log): array
    {
        return [
            $log->employee?->emp_number ?? '',
            $log->employee ? $log->employee->first_name . ' ' . $log->employee->last_name : '',
            $log->date,
            $log->clock_in  ? \Carbon\Carbon::parse($log->clock_in)->format('H:i') : '',
            $log->clock_out ? \Carbon\Carbon::parse($log->clock_out)->format('H:i') : '',
            $log->status,
            $log->overtime_hours,
        ];
    }
}
