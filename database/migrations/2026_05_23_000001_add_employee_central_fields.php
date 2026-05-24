<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {

            // Identity & Basic
            $table->string('title', 20)->nullable()->after('id');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('payroll_number', 50)->nullable()->unique()->after('emp_number');

            // Work Schedule / Job Profile
            $table->string('week_off_type', 30)->nullable()->after('employment_type');
            $table->string('week_off_day', 20)->nullable()->after('week_off_type');
            $table->string('holiday_calendar', 100)->nullable()->after('week_off_day');
            $table->boolean('contract_applicable')->default(false)->after('holiday_calendar');
            $table->boolean('is_expatriate')->default(false)->after('contract_applicable');

            // Placement
            $table->string('work_location', 100)->nullable()->after('address');
            $table->string('sub_department', 100)->nullable()->after('work_location');
            $table->string('employee_category', 50)->nullable()->after('sub_department');
            $table->string('class_name', 50)->nullable()->after('employee_category');
            $table->string('position_name', 100)->nullable()->after('class_name');
            $table->string('organization_unit', 100)->nullable()->after('position_name');

            // Personal Details (extended)
            $table->string('religion', 50)->nullable()->after('organization_unit');
            $table->string('nationality', 80)->nullable()->after('religion');
            $table->string('mother_tongue', 50)->nullable()->after('nationality');
            $table->string('marital_status', 20)->nullable()->after('gender');
            $table->unsignedTinyInteger('children_count')->default(0)->after('marital_status');
            $table->decimal('dependents_count', 8, 2)->default(0)->after('children_count');
            $table->date('anniversary_date')->nullable()->after('date_of_birth');

            // Insurance
            $table->boolean('insurance_relief')->default(false)->after('bio');

            // Statutory Identifiers
            $table->string('nssf_number', 30)->nullable()->after('insurance_relief');
            $table->string('tin_number', 30)->nullable()->after('nssf_number');
            $table->string('ifms_supplier_no', 30)->nullable()->after('tin_number');
            $table->string('pension_no', 30)->nullable()->after('ifms_supplier_no');

            // Statutory Deductions
            $table->boolean('charge_nssf')->default(true)->after('pension_no');
            $table->boolean('force_fixed_nssf')->default(false)->after('charge_nssf');
            $table->decimal('fixed_nssf_amount', 12, 2)->nullable()->after('force_fixed_nssf');
            $table->boolean('nssf_paid_by_employer')->default(false)->after('fixed_nssf_amount');
            $table->boolean('do_not_charge_nssf_employee')->default(false)->after('nssf_paid_by_employer');
            $table->decimal('voluntary_nssf', 12, 2)->nullable()->after('do_not_charge_nssf_employee');
            $table->boolean('charge_lst')->default(true)->after('voluntary_nssf');
            $table->boolean('lst_paid_by_employer')->default(false)->after('charge_lst');
            $table->boolean('tax_paid_by_employer')->default(false)->after('lst_paid_by_employer');
            $table->boolean('apply_special_tax')->default(false)->after('tax_paid_by_employer');
            $table->decimal('special_tax_percentage', 5, 2)->nullable()->after('apply_special_tax');

            // Salary Calculation Hours
            $table->string('payment_mode', 20)->default('bank')->after('bank_branch');
            $table->decimal('ot_calc_hours', 8, 2)->nullable()->after('payment_mode');
            $table->decimal('absenteeism_calc_hours', 8, 2)->nullable()->after('ot_calc_hours');
            $table->decimal('ot1_calc_hours', 8, 2)->nullable()->after('absenteeism_calc_hours');
            $table->decimal('ot2_calc_hours', 8, 2)->nullable()->after('ot1_calc_hours');
            $table->decimal('min_daily_working_hours', 8, 2)->nullable()->after('ot2_calc_hours');

            // Provident Fund
            $table->boolean('pf_applicable')->default(false)->after('min_daily_working_hours');
            $table->string('pf_deduction_type', 20)->nullable()->after('pf_applicable');
            $table->string('pf_calculate_on', 30)->nullable()->after('pf_deduction_type');
            $table->decimal('pf_employee_rate', 8, 4)->nullable()->after('pf_calculate_on');
            $table->decimal('pf_employer_rate', 8, 4)->nullable()->after('pf_employee_rate');
            $table->string('pf_scheme', 60)->nullable()->after('pf_employer_rate');
            $table->string('voluntary_pf_deduction_type', 20)->nullable()->after('pf_scheme');
            $table->string('voluntary_pf_calculate_on', 30)->nullable()->after('voluntary_pf_deduction_type');
            $table->decimal('voluntary_pf_amount', 12, 2)->nullable()->after('voluntary_pf_calculate_on');
            $table->boolean('do_not_deduct_voluntary_pf')->default(false)->after('voluntary_pf_amount');

            // Pension
            $table->boolean('pension_applicable')->default(false)->after('do_not_deduct_voluntary_pf');
            $table->string('pension_deduction_type', 20)->nullable()->after('pension_applicable');
            $table->string('pension_calculate_on', 30)->nullable()->after('pension_deduction_type');
            $table->decimal('pension_employee_rate', 8, 4)->nullable()->after('pension_calculate_on');
            $table->decimal('pension_employer_rate', 8, 4)->nullable()->after('pension_employee_rate');
            $table->string('pension_scheme', 60)->nullable()->after('pension_employer_rate');
            $table->string('voluntary_pension_deduction_type', 20)->nullable()->after('pension_scheme');
            $table->string('voluntary_pension_calculate_on', 30)->nullable()->after('voluntary_pension_deduction_type');
            $table->decimal('voluntary_pension_amount', 12, 2)->nullable()->after('voluntary_pension_calculate_on');
            $table->boolean('do_not_deduct_voluntary_pension')->default(false)->after('voluntary_pension_amount');

            // Blacklist
            $table->boolean('is_blacklisted')->default(false)->after('do_not_deduct_voluntary_pension');
            $table->date('blacklist_date')->nullable()->after('is_blacklisted');
            $table->text('blacklist_reason')->nullable()->after('blacklist_date');

            // Hold
            $table->boolean('on_hold')->default(false)->after('blacklist_reason');
            $table->date('hold_date')->nullable()->after('on_hold');
            $table->text('hold_reason')->nullable()->after('hold_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'title','middle_name','payroll_number',
                'week_off_type','week_off_day','holiday_calendar','contract_applicable','is_expatriate',
                'work_location','sub_department','employee_category','class_name','position_name','organization_unit',
                'religion','nationality','mother_tongue','marital_status','children_count','dependents_count','anniversary_date',
                'insurance_relief','nssf_number','tin_number','ifms_supplier_no','pension_no',
                'charge_nssf','force_fixed_nssf','fixed_nssf_amount','nssf_paid_by_employer','do_not_charge_nssf_employee',
                'voluntary_nssf','charge_lst','lst_paid_by_employer','tax_paid_by_employer','apply_special_tax','special_tax_percentage',
                'payment_mode','ot_calc_hours','absenteeism_calc_hours','ot1_calc_hours','ot2_calc_hours','min_daily_working_hours',
                'pf_applicable','pf_deduction_type','pf_calculate_on','pf_employee_rate','pf_employer_rate','pf_scheme',
                'voluntary_pf_deduction_type','voluntary_pf_calculate_on','voluntary_pf_amount','do_not_deduct_voluntary_pf',
                'pension_applicable','pension_deduction_type','pension_calculate_on','pension_employee_rate','pension_employer_rate','pension_scheme',
                'voluntary_pension_deduction_type','voluntary_pension_calculate_on','voluntary_pension_amount','do_not_deduct_voluntary_pension',
                'is_blacklisted','blacklist_date','blacklist_reason',
                'on_hold','hold_date','hold_reason',
            ]);
        });
    }
};
