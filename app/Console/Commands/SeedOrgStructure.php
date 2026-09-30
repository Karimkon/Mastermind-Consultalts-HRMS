<?php
namespace App\Console\Commands;

use App\Models\{Client, Employee, OrgPosition};
use Illuminate\Console\Command;

/**
 * Builds Mastermind's own chain of command, and hangs each client site beneath
 * the Account Manager who looks after it.
 *
 * Safe to run again: positions are matched on title within their parent, so a
 * second run updates the tree instead of duplicating it.
 */
class SeedOrgStructure extends Command
{
    protected $signature   = 'hrms:seed-org-structure {--dry-run : Show the tree without writing it}';
    protected $description = "Create or refresh Mastermind's organisational structure";

    /**
     * The structure as given by the business. Nesting here is the nesting on
     * the chart; order within a level is the order shown.
     */
    private const STRUCTURE = [
        'Board of Directors' => [
            'Managing Director / CEO' => [
                // Two separate posts, both advising the MD directly rather than
                // running through the GM: Vivian holds Legal Officer, Halima is
                // the Consultant.
                'Legal Officer' => [],
                'Consultant'    => [],
                'General Manager' => [
                    'HR and Operations' => [
                        'Account Managers'  => [],
                        'Drivers'           => [],
                        'Fleet Officer'     => [],
                        'Office Admin'      => [],
                        'Security Officer'  => [],
                        'Sanitation Officer'=> [],
                    ],
                    'Accounts and Finance Officer' => [],
                    'Health and Safety'            => [],
                    'Compliance Officer'           => [],
                    'Counsellor'                   => [],
                    'Business Development Manager' => [
                        'Sales Team'                  => [],
                        'Marketing and Media Officer' => [],
                    ],
                    'IT Manager'                => [],
                    'Procurement and Logistics' => [],
                ],
            ],
        ],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->info('Dry run — nothing will be written.');
            $this->render(self::STRUCTURE, 0);
            return Command::SUCCESS;
        }

        $made = $this->build(self::STRUCTURE, null);
        $this->info("Mastermind structure: {$made} position(s) created or updated.");

        // Every client site hangs off the Account Managers box, because that is
        // where the reporting line actually runs: a Roofings loader reports to
        // Brenda, and Brenda sits under HR and Operations.
        $amBox = OrgPosition::whereNull('client_id')->where('title', 'Account Managers')->first();
        if (! $amBox) {
            $this->warn('No "Account Managers" position found; client sites not attached.');
            return Command::SUCCESS;
        }

        $clients = 0;
        foreach (Client::orderBy('company_name')->get() as $client) {
            $position = OrgPosition::updateOrCreate(
                ['client_id' => $client->id, 'parent_id' => $amBox->id],
                ['title' => $client->company_name, 'sort_order' => $clients, 'is_active' => true]
            );

            // Nobody is seated here from `account_manager_id`. That column holds
            // a login, and resolving it to an employee is exactly what would
            // have put the test record MM-REVIEW-01 into the Roofings box.
            // nameSiteSupervisors() seats the real people instead.
            $clients++;
        }

        $this->info("Client sites attached under Account Managers: {$clients}");

        $this->placeKnownPeople();
        $this->nameSiteSupervisors();

        return Command::SUCCESS;
    }

    /** Write one level, then recurse. Returns how many boxes were touched. */
    private function build(array $level, ?int $parentId, int $depth = 0): int
    {
        $count = 0;
        $order = 0;

        foreach ($level as $title => $children) {
            $position = OrgPosition::updateOrCreate(
                ['client_id' => null, 'parent_id' => $parentId, 'title' => $title],
                ['sort_order' => $order++, 'is_active' => true]
            );

            $this->line(str_repeat('  ', $depth) . '• ' . $title);
            $count++;

            if ($children) {
                $count += $this->build($children, $position->id, $depth + 1);
            }
        }

        return $count;
    }

    /**
     * Post held => staff number of the person holding it.
     *
     * Keyed on emp_number rather than name: "Julius Mbogo" is recorded as
     * "Mbogga Julius", and matching on a spelling that does not match is how
     * the wrong person ends up in a box. General Manager, IT Manager and
     * Counsellor are absent on purpose - those posts are vacant, and the chart
     * should say so.
     */
    private const HOLDERS = [
        'Managing Director / CEO'      => 'HQ002',   // Akol Deograceous
        'HR and Operations'            => 'HQ001',   // Ian Kirabo
        'Accounts and Finance Officer' => 'HQ006',   // Nakibuuka Florence
        'Business Development Manager' => 'HQ007',   // Lutwama Christopher
        'Health and Safety'            => 'HQ026',   // Mbogga Julius
        'Compliance Officer'           => 'HQ005',   // Nsubuga Ibrahim
        'Consultant'                   => 'HQ004',   // Muhammed Halima
        'Security Officer'             => 'HQ029',   // Brain Onen
        'Sanitation Officer'           => 'HQ028',   // Magoba Grace
        // Legal Officer is Vivian, who has no employee record yet - the post
        // stays visibly vacant rather than being filled with a guess.
    ];

    /**
     * Which employee supervises the staff placed at each client site.
     *
     * Keyed on staff number for the same reason the holders map is: the account
     * managers' names differ between their two accounts ("Brenda Kansiime" the
     * login, "Kasiime Brenda" the staff record), and matching on those would put
     * a thousand people under the wrong person or under nobody.
     */
    private const SITE_SUPERVISORS = [
        'Roofings Uganda Limited'             => 'HQ003',  // Kasiime Brenda
        'UNOC (Uganda National Oil Company)'  => 'HQ003',
        'Bidco (U) Limited'                   => 'HQ003',
        'Serena Kampala Hotel'                => 'HQ010',  // Namuleme Brenda Stella
        'Sheraton Kampala Hotel'              => 'HQ010',
        'ONOMO Hotel'                         => 'HQ010',
        'Mastermind Consult Ltd HQ'           => 'HQ008',  // Namuleme Violet
        'Stone Providers'                     => 'HQ008',
        'Lakeside Ltd'                        => 'HQ009',  // Omar Shamilah
        'Four Points by Sheraton'             => 'HQ009',
        'Discovery Trading Ltd'               => 'HQ009',
        'Discovery Coftea Ltd'                => 'HQ009',
        'Discovery Impex Ltd'                 => 'HQ009',
        'Africot Trading Ltd'                 => 'HQ009',
    ];

    private function placeKnownPeople(): void
    {
        foreach (self::HOLDERS as $title => $empNumber) {
            $position = OrgPosition::whereNull('client_id')->where('title', $title)->first();
            $employee = Employee::where('emp_number', $empNumber)->first();

            if (! $position) { $this->warn("  no position '{$title}'"); continue; }
            if (! $employee) { $this->warn("  no employee {$empNumber} for '{$title}'"); continue; }

            $employee->update(['org_position_id' => $position->id]);
            $this->line("  seated: {$employee->full_name} ({$empNumber}) → {$title}");
        }
    }

    /** Record which employee supervises each client site. */
    private function nameSiteSupervisors(): void
    {
        foreach (self::SITE_SUPERVISORS as $company => $empNumber) {
            $client   = Client::where('company_name', $company)->first();
            $employee = Employee::where('emp_number', $empNumber)->first();

            if (! $client)   { $this->warn("  no client '{$company}'");            continue; }
            if (! $employee) { $this->warn("  no employee {$empNumber} for '{$company}'"); continue; }

            $client->update(['supervisor_employee_id' => $employee->id]);

            // The account managers belong in the Account Managers box, which is
            // where their own reporting line runs: under HR and Operations, not
            // inside the client site they look after.
            if ($amBox = OrgPosition::whereNull('client_id')->where('title', 'Account Managers')->first()) {
                $employee->update(['org_position_id' => $amBox->id]);
            }

            $this->line("  site supervisor: {$company} → {$employee->full_name} ({$empNumber})");
        }
    }

    /** Print the tree for --dry-run. */
    private function render(array $level, int $depth): void
    {
        foreach ($level as $title => $children) {
            $this->line(str_repeat('   ', $depth) . ($depth ? '└─ ' : '') . $title);
            if ($children) $this->render($children, $depth + 1);
        }
    }
}
