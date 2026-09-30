<?php
namespace App\Console\Commands;

use App\Models\{Client, Employee};
use Illuminate\Console\Command;

/**
 * Gives outsourced staff the supervisor they already have in practice.
 *
 * An employee placed at a client site reports to the Account Manager who looks
 * after that site - all 439 Roofings staff report to Brenda Kansiime. That was
 * true operationally and simply absent from the data: 1,243 of 1,244 employee
 * records carried no supervisor at all.
 *
 * Only empty supervisors are filled unless --overwrite is passed, so a reporting
 * line somebody set by hand is never quietly replaced.
 */
class AssignClientSupervisors extends Command
{
    protected $signature = 'hrms:assign-client-supervisors
                            {--dry-run   : Report what would change without writing}
                            {--overwrite : Also replace supervisors that are already set}';

    protected $description = 'Make each client-site employee report to their Account Manager';

    public function handle(): int
    {
        $dry       = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        $assigned = 0;

        $clients = Client::with(['accountManager', 'supervisor'])->orderBy('company_name')->get();

        foreach ($clients as $client) {
            // `supervisor_employee_id` names the supervising employee outright.
            // The fall-back resolves the account manager's login to an employee,
            // which works only where those two happen to be linked - see the
            // migration that added the column for why that is not to be relied
            // on.
            $amEmployee = $client->supervisor
                ?? ($client->account_manager_id
                    ? Employee::where('user_id', $client->account_manager_id)->first()
                    : null);

            if (! $amEmployee) {
                $this->warn("  {$client->company_name}: no supervising employee resolved "
                    . '— skipped (set one on the client record)');
                continue;
            }

            $query = $client->employees()
                ->where('employees.id', '!=', $amEmployee->id)   // nobody reports to themselves
                // Anybody holding a post on Mastermind's own chart is excluded.
                // Head Office staff are assigned to the "Mastermind Consult Ltd
                // HQ" client like everyone else, so without this the MD would be
                // made to report to an account manager - and so would every
                // functional head. Their line comes from the chart instead.
                ->whereNotExists(function ($q) {
                    $q->selectRaw(1)
                      ->from('org_positions')
                      ->whereColumn('org_positions.id', 'employees.org_position_id')
                      ->whereNull('org_positions.client_id');
                })
                ->when(! $overwrite, fn($q) => $q->whereNull('manager_id'));

            $staff = $query->get();

            $this->line("  {$client->company_name}: {$staff->count()} staff → {$amEmployee->full_name}");

            if ($dry) {
                $assigned += $staff->count();
                continue;
            }

            foreach ($staff as $employee) {
                $employee->forceFill(['manager_id' => $amEmployee->id])->save();
                $assigned++;
            }
        }

        $stillEmpty = Employee::whereNull('manager_id')->count();

        $this->info($dry
            ? "Dry run: {$assigned} employee(s) would be given a supervisor. "
              . "Nothing was written."
            : "Done. Supervisors set: {$assigned}. "
              . "Employees still without one: {$stillEmpty}.");

        return Command::SUCCESS;
    }
}
