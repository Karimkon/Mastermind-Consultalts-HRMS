<?php
namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return Employee::with(['department', 'designation', 'user', 'clients'])->get();
    }

    public function headings(): array
    {
        return [
            // Identity
            'emp_number','payroll_number','title','first_name','middle_name','last_name',
            // Job
            'email','department','designation','employment_type','hire_date','end_date','status','salary_grade',
            // Placement
            'work_location','sub_department','employee_category','class_name','position_name','organization_unit',
            // Personal
            'phone','personal_email','date_of_birth','gender','marital_status',
            'children_count','dependents_count','nationality','religion','mother_tongue',
            'national_id','passport_number','address','city','country',
            // Emergency & NOK
            'emergency_contact_name','emergency_contact_phone',
            'next_of_kin_name','next_of_kin_relation','next_of_kin_phone',
            // Statutory
            'nssf_number','tin_number','ifms_supplier_no','pension_no','tax_number',
            // Banking
            'bank_name','bank_account','bank_branch','payment_mode',
            // Salary Calc
            'ot_calc_hours','absenteeism_calc_hours','min_daily_working_hours',
            // Bio
            'bio',
        ];
    }

    public function map($emp): array
    {
        return [
            $emp->emp_number,
            $emp->payroll_number ?? '',
            $emp->title ?? '',
            $emp->first_name,
            $emp->middle_name ?? '',
            $emp->last_name,
            $emp->user?->email ?? '',
            $emp->department?->name ?? '',
            $emp->designation?->title ?? '',
            $emp->employment_type ?? '',
            $emp->hire_date?->format('Y-m-d') ?? '',
            $emp->end_date?->format('Y-m-d') ?? '',
            $emp->status ?? '',
            $emp->salary_grade ?? '',
            $emp->work_location ?? '',
            $emp->sub_department ?? '',
            $emp->employee_category ?? '',
            $emp->class_name ?? '',
            $emp->position_name ?? '',
            $emp->organization_unit ?? '',
            $emp->phone ?? '',
            $emp->personal_email ?? '',
            $emp->date_of_birth?->format('Y-m-d') ?? '',
            $emp->gender ?? '',
            $emp->marital_status ?? '',
            $emp->children_count ?? '',
            $emp->dependents_count ?? '',
            $emp->nationality ?? '',
            $emp->religion ?? '',
            $emp->mother_tongue ?? '',
            $emp->national_id ?? '',
            $emp->passport_number ?? '',
            $emp->address ?? '',
            $emp->city ?? '',
            $emp->country ?? '',
            $emp->emergency_contact_name ?? '',
            $emp->emergency_contact_phone ?? '',
            $emp->next_of_kin_name ?? '',
            $emp->next_of_kin_relation ?? '',
            $emp->next_of_kin_phone ?? '',
            $emp->nssf_number ?? '',
            $emp->tin_number ?? '',
            $emp->ifms_supplier_no ?? '',
            $emp->pension_no ?? '',
            $emp->tax_number ?? '',
            $emp->bank_name ?? '',
            $emp->bank_account ?? '',
            $emp->bank_branch ?? '',
            $emp->payment_mode ?? '',
            $emp->ot_calc_hours ?? '',
            $emp->absenteeism_calc_hours ?? '',
            $emp->min_daily_working_hours ?? '',
            $emp->bio ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DBEAFE']]],
        ];
    }
}
