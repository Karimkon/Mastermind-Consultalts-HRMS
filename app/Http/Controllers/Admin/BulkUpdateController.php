<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fill in details on staff who already exist.
 *
 * This is deliberately not an importer. It never creates an employee: a row
 * whose staff number is not already on the register is reported and skipped.
 * 905 people are on the system with 805 of them missing a bank account, and
 * the risk being managed here is a spreadsheet quietly creating 805 duplicates
 * beside the real ones, not a missing record.
 *
 * Two rules make it safe to run against live payroll data:
 *
 *   1. A blank cell changes nothing. You can send a sheet of bank accounts
 *      today and line managers next week without the second sheet wiping what
 *      the first one set.
 *   2. Nothing is written until the changes have been shown. A wrong account
 *      number pays a stranger, so overwriting a value that is already there is
 *      counted and displayed apart from filling a blank, and needs its own
 *      confirmation.
 */
class BulkUpdateController extends Controller
{
    /** Sheet column => employee column. Everything else in the file is ignored. */
    private const FIELDS = [
        'bank_name'            => 'bank_name',
        'bank_account'         => 'bank_account',
        'bank_branch'          => 'bank_branch',
        'payment_mode'         => 'payment_mode',
        'mobile_money_number'  => 'mobile_money_number',
        'phone'                => 'phone',
        'national_id'          => 'national_id',
        'nssf_number'          => 'nssf_number',
        'tin_number'           => 'tin_number',
        'country'              => 'country',
    ];

    /** Spellings people actually type. */
    private const ALIASES = [
        'employee_number' => 'emp_number',   'staff_number' => 'emp_number',
        'staff_no'        => 'emp_number',   'emp_no'       => 'emp_number',
        'account_number'  => 'bank_account', 'account_no'   => 'bank_account',
        'acc_number'      => 'bank_account', 'bank_acc'     => 'bank_account',
        'branch'          => 'bank_branch',  'bank'         => 'bank_name',
        'momo'            => 'mobile_money_number', 'mobile_money' => 'mobile_money_number',
        'telephone'       => 'phone',        'mobile'       => 'phone',
        'tin'             => 'tin_number',   'nssf'         => 'nssf_number',
        'nin'             => 'national_id',
        'manager'         => 'manager_emp_number',
        'supervisor'      => 'manager_emp_number',
        'reports_to'      => 'manager_emp_number',
        'line_manager'    => 'manager_emp_number',
        'job_title'       => 'designation',  'title'        => 'designation',
        'position'        => 'designation',
    ];

    private function authorise(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'hr-admin']), 403,
            'Only an administrator or HR can bulk-update staff records.');
    }

    public function index()
    {
        $this->authorise();

        return view('admin.bulk-update.index', [
            'gaps' => $this->gaps(),
        ]);
    }

    /** What is actually missing, so the page says what it is worth sending. */
    private function gaps(): array
    {
        $active = Employee::where('status', 'active');

        return [
            'total'         => (clone $active)->count(),
            'bank_account'  => (clone $active)->where('payment_mode', 'bank')
                                  ->where(fn ($q) => $q->whereNull('bank_account')->orWhere('bank_account', ''))->count(),
            'manager_id'    => (clone $active)->whereNull('manager_id')->count(),
            'designation'   => (clone $active)->whereNull('designation_id')->count(),
            'phone'         => (clone $active)->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))->count(),
            'nssf_number'   => (clone $active)->where(fn ($q) => $q->whereNull('nssf_number')->orWhere('nssf_number', ''))->count(),
            'tin_number'    => (clone $active)->where(fn ($q) => $q->whereNull('tin_number')->orWhere('tin_number', ''))->count(),
        ];
    }

    /**
     * A sheet pre-filled with the staff who are missing something.
     *
     * Downloading a blank template and typing 805 staff numbers by hand is how
     * numbers end up against the wrong person, so the register fills that column
     * in and the columns to complete are left empty.
     */
    public function template(Request $request)
    {
        $this->authorise();

        $only = $request->query('missing');   // e.g. bank_account

        $employees = Employee::with(['user', 'designation', 'manager'])
            ->where('status', 'active')
            ->when($only === 'bank_account', fn ($q) => $q->where('payment_mode', 'bank')
                ->where(fn ($w) => $w->whereNull('bank_account')->orWhere('bank_account', '')))
            ->when($only === 'manager_id', fn ($q) => $q->whereNull('manager_id'))
            ->when($only === 'designation', fn ($q) => $q->whereNull('designation_id'))
            ->orderBy('emp_number')
            ->get();

        $name = 'staff-update-' . ($only ?: 'all') . '-' . now()->format('Ymd') . '.csv';

        return response()->stream(function () use ($employees) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'emp_number', 'full_name', 'bank_name', 'bank_account', 'bank_branch',
                'payment_mode', 'mobile_money_number', 'phone', 'national_id',
                'nssf_number', 'tin_number', 'manager_emp_number', 'designation', 'country',
            ]);

            foreach ($employees as $e) {
                fputcsv($out, [
                    $e->emp_number,
                    // Present so a human can see who each row is. It is never
                    // written back - names are not edited through this screen.
                    $e->full_name,
                    $e->bank_name, $e->bank_account, $e->bank_branch,
                    $e->payment_mode, $e->mobile_money_number, $e->phone,
                    $e->national_id, $e->nssf_number, $e->tin_number,
                    $e->manager?->emp_number,
                    $e->designation?->title,
                    $e->country,
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    public function preview(Request $request)
    {
        $this->authorise();

        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $report = $this->analyse($request->file('csv_file')->getRealPath());

        // Held in the session rather than re-uploaded, so the sheet that is
        // applied is byte-for-byte the one that was shown.
        session(['bulk_update_rows' => $report['apply']]);

        return view('admin.bulk-update.preview', [
            'report' => $report,
            'gaps'   => $this->gaps(),
        ]);
    }

    public function apply(Request $request)
    {
        $this->authorise();

        $rows = session('bulk_update_rows');

        if (! $rows) {
            return redirect()->route('admin.bulk-update.index')
                ->with('error', 'That preview has expired. Upload the sheet again.');
        }

        // Overwriting a value that is already there is a separate decision from
        // filling a blank: a wrong account number pays a stranger.
        $allowOverwrite = $request->boolean('allow_overwrite');
        $changed = 0;
        $heldBack = 0;

        DB::transaction(function () use ($rows, $allowOverwrite, &$changed, &$heldBack) {
            foreach ($rows as $row) {
                $employee = Employee::find($row['employee_id']);

                if (! $employee) {
                    continue;
                }

                // Without permission to overwrite, the blanks on a row are still
                // filled - only the fields that would replace something are held
                // back. Skipping the whole row would lose good data because one
                // cell disagreed.
                $data = $allowOverwrite ? $row['changes'] : $row['fills'];

                if (! $allowOverwrite) {
                    $heldBack += count($row['overwrites']);
                }

                if (! $data) {
                    continue;
                }

                $employee->fill($data)->save();
                $changed++;
            }
        });

        session()->forget('bulk_update_rows');

        return redirect()->route('admin.bulk-update.index')->with('success',
            "{$changed} staff record(s) updated."
            . ($heldBack ? " {$heldBack} field(s) left alone because they already had a value." : ''));
    }

    /**
     * Read the sheet and work out what each row would do. Writes nothing.
     */
    private function analyse(string $path): array
    {
        $handle = fopen($path, 'r');
        $headerRow = fgetcsv($handle);

        // Matched by heading, not position, so columns may be reordered or left
        // out entirely without the rest of the row shifting.
        $columns = [];
        foreach ($headerRow ?: [] as $i => $label) {
            $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $label), '_'));
            $key = self::ALIASES[$key] ?? $key;
            if ($key !== '') $columns[$key] = $i;
        }

        $problems = [];
        $apply = [];
        $lineNo = 1;
        $unchanged = 0;

        if (! isset($columns['emp_number'])) {
            fclose($handle);

            return [
                'apply' => [], 'problems' => [[
                    'line' => 1,
                    'emp_number' => '',
                    'reason' => 'The sheet has no emp_number column, so rows cannot be matched to staff.',
                ]], 'unchanged' => 0, 'fills' => 0, 'overwrites' => 0,
            ];
        }

        $designations = Designation::pluck('id', 'title');

        while (($line = fgetcsv($handle)) !== false) {
            $lineNo++;

            $get = function (string $key) use ($line, $columns) {
                $i = $columns[$key] ?? null;
                return $i === null ? null : trim((string) ($line[$i] ?? ''));
            };

            $empNumber = $get('emp_number');

            if ($empNumber === '' || $empNumber === null) {
                continue;   // blank padding row at the end of a spreadsheet
            }

            $employee = Employee::where('emp_number', $empNumber)->first();

            if (! $employee) {
                // Never created. A typo in a staff number would otherwise add a
                // second record beside the real person.
                $problems[] = [
                    'line' => $lineNo, 'emp_number' => $empNumber,
                    'reason' => 'No staff member has this number. Nothing was created.',
                ];
                continue;
            }

            $fills = [];        // filling a blank
            $overwrites = [];   // replacing something already there

            foreach (self::FIELDS as $sheetKey => $column) {
                $value = $get($sheetKey);

                if ($value === null || $value === '') {
                    continue;   // a blank cell means "leave this alone"
                }

                if ($column === 'payment_mode') {
                    $value = Employee::normalisePaymentMode($value, $get('mobile_money_number') ?: $employee->mobile_money_number);
                }

                $current = (string) ($employee->$column ?? '');

                if ($current === (string) $value) {
                    continue;
                }

                if ($current === '') {
                    $fills[$column] = $value;
                } else {
                    $overwrites[$column] = ['from' => $current, 'to' => $value];
                }
            }

            // ── Line manager, given as their staff number ────────────────

            $managerNumber = $get('manager_emp_number');

            if ($managerNumber) {
                $manager = Employee::where('emp_number', $managerNumber)->first();

                if (! $manager) {
                    $problems[] = [
                        'line' => $lineNo, 'emp_number' => $empNumber,
                        'reason' => "Line manager '{$managerNumber}' is not a staff number on the register.",
                    ];
                } elseif ($manager->id === $employee->id) {
                    $problems[] = [
                        'line' => $lineNo, 'emp_number' => $empNumber,
                        'reason' => 'Somebody cannot be their own line manager.',
                    ];
                } elseif ((string) $employee->manager_id === (string) $manager->id) {
                    // already correct
                } elseif ($employee->manager_id) {
                    $overwrites['manager_id'] = [
                        'from' => $employee->manager?->emp_number ?? $employee->manager_id,
                        'to'   => $manager->emp_number,
                    ];
                } else {
                    $fills['manager_id'] = $manager->id;
                }
            }

            // ── Designation, matched by title ────────────────────────────

            $designation = $get('designation');

            if ($designation) {
                $id = $designations[$designation] ?? null;

                if (! $id) {
                    $problems[] = [
                        'line' => $lineNo, 'emp_number' => $empNumber,
                        'reason' => "'{$designation}' is not a job title on the system. Add it first, or correct the spelling.",
                    ];
                } elseif ((string) $employee->designation_id === (string) $id) {
                    // already correct
                } elseif ($employee->designation_id) {
                    $overwrites['designation_id'] = [
                        'from' => $employee->designation?->title ?? $employee->designation_id,
                        'to'   => $designation,
                    ];
                } else {
                    $fills['designation_id'] = $id;
                }
            }

            if (! $fills && ! $overwrites) {
                $unchanged++;
                continue;
            }

            $apply[] = [
                'line'        => $lineNo,
                'employee_id' => $employee->id,
                'emp_number'  => $employee->emp_number,
                'name'        => $employee->full_name,
                'fills'       => $fills,
                'overwrites'  => $overwrites,
                // What apply() writes when overwriting is allowed.
                'changes'     => $fills + collect($overwrites)->map(fn ($c, $k) => $this->resolve($k, $c['to']))->all(),
            ];
        }

        fclose($handle);

        return [
            'apply'      => $apply,
            'problems'   => $problems,
            'unchanged'  => $unchanged,
            'fills'      => collect($apply)->sum(fn ($r) => count($r['fills'])),
            'overwrites' => collect($apply)->sum(fn ($r) => count($r['overwrites'])),
        ];
    }

    /** manager_id and designation_id are displayed by name but stored by id. */
    private function resolve(string $column, $value)
    {
        return match ($column) {
            'manager_id'      => Employee::where('emp_number', $value)->value('id'),
            'designation_id'  => Designation::where('title', $value)->value('id'),
            default           => $value,
        };
    }
}
