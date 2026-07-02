<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AmEmployeesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private Collection $employees) {}

    public function collection(): Collection { return $this->employees; }

    public function headings(): array
    {
        return [
            // Identity
            'Emp Number', 'Payroll Number', 'Title', 'First Name', 'Middle Name', 'Last Name',
            // Company
            'Company / Client', 'Department', 'Designation', 'Sub Department',
            'Position', 'Organisation Unit', 'Work Location', 'Employee Category',
            // Employment
            'Employment Type', 'Status', 'Hire Date', 'End Date',
            'Contract Start', 'Contract End', 'Salary Grade',
            // Statutory IDs
            'NSSF Number', 'TIN Number', 'National ID', 'Passport Number',
            'IFMS Supplier No', 'Pension No',
            // Personal
            'Date of Birth', 'Gender', 'Marital Status', 'Nationality',
            'Religion', 'Mother Tongue', 'Children Count', 'Dependents Count',
            // Contact
            'Phone', 'Personal Email', 'Address', 'City', 'Country',
            // Emergency & NOK
            'Emergency Contact Name', 'Emergency Phone',
            'Next of Kin Name', 'NOK Relation', 'NOK Phone', 'NOK Email',
            // Banking & Payment
            'Payment Mode', 'Bank Name', 'Bank Account', 'Bank Branch', 'Mobile Money Number',
            // Salary
            'Basic Salary (UGX)', 'Salary Type',
            // Statutory Deductions
            'Charge PAYE', 'Charge NSSF', 'NSSF Paid by Employer',
            'Charge LST', 'Tax Paid by Employer',
            // Blacklist / Hold
            'Is Blacklisted', 'Blacklist Reason', 'On Hold', 'Hold Reason',
        ];
    }

    public function map($emp): array
    {
        $client = $emp->clients->first();

        return [
            // Identity
            $emp->emp_number,
            $emp->payroll_number ?? '',
            $emp->title ?? '',
            $emp->first_name,
            $emp->middle_name ?? '',
            $emp->last_name,
            // Company
            $client?->company_name ?? '',
            $emp->department?->name ?? '',
            $emp->designation?->title ?? '',
            $emp->sub_department ?? '',
            $emp->position_name ?? '',
            $emp->organization_unit ?? '',
            $emp->work_location ?? '',
            $emp->employee_category ?? '',
            // Employment
            ucfirst(str_replace('_', ' ', $emp->employment_type ?? '')),
            ucfirst($emp->status ?? ''),
            $emp->hire_date ? \Carbon\Carbon::parse($emp->hire_date)->format('d M Y') : '',
            $emp->end_date ? \Carbon\Carbon::parse($emp->end_date)->format('d M Y') : '',
            $emp->contract_start_date ? \Carbon\Carbon::parse($emp->contract_start_date)->format('d M Y') : '',
            $emp->contract_end_date ? \Carbon\Carbon::parse($emp->contract_end_date)->format('d M Y') : '',
            $emp->salary_grade ?? '',
            // Statutory IDs
            $emp->nssf_number ?? '',
            $emp->tin_number ?? '',
            $emp->national_id ?? '',
            $emp->passport_number ?? '',
            $emp->ifms_supplier_no ?? '',
            $emp->pension_no ?? '',
            // Personal
            $emp->date_of_birth ? \Carbon\Carbon::parse($emp->date_of_birth)->format('d M Y') : '',
            ucfirst($emp->gender ?? ''),
            ucfirst(str_replace('_', ' ', $emp->marital_status ?? '')),
            $emp->nationality ?? '',
            $emp->religion ?? '',
            $emp->mother_tongue ?? '',
            $emp->children_count ?? '',
            $emp->dependents_count ?? '',
            // Contact
            $emp->phone ?? '',
            $emp->personal_email ?? $emp->user?->email ?? '',
            $emp->address ?? '',
            $emp->city ?? '',
            $emp->country ?? '',
            // Emergency & NOK
            $emp->emergency_contact_name ?? '',
            $emp->emergency_contact_phone ?? '',
            $emp->next_of_kin_name ?? '',
            $emp->next_of_kin_relation ?? '',
            $emp->next_of_kin_phone ?? '',
            $emp->next_of_kin_email ?? '',
            // Banking
            ucfirst(str_replace('_', ' ', $emp->payment_mode ?? '')),
            $emp->bank_name ?? '',
            $emp->bank_account ?? '',
            $emp->bank_branch ?? '',
            $emp->mobile_money_number ?? '',
            // Salary
            $emp->salary?->basic_salary ?? '',
            ucfirst($emp->salary?->salary_type ?? ''),
            // Statutory flags
            $emp->charge_paye ? 'Yes' : 'No',
            $emp->charge_nssf ? 'Yes' : 'No',
            $emp->nssf_paid_by_employer ? 'Yes' : 'No',
            $emp->charge_lst ? 'Yes' : 'No',
            $emp->tax_paid_by_employer ? 'Yes' : 'No',
            // Blacklist / Hold
            $emp->is_blacklisted ? 'Yes' : 'No',
            $emp->blacklist_reason ?? '',
            $emp->on_hold ? 'Yes' : 'No',
            $emp->hold_reason ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1e3a8a']],
            ],
        ];
    }
}
