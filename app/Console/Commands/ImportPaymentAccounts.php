<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Employee;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Put the details that only exist in the monthly payroll workbooks onto the
 * employee records: bank accounts, mobile money, TINs and NSSF numbers.
 *
 * These workbooks are the only place a lot of this lives. 439 Roofings staff
 * were all set to Bank Transfer with not one account number between them, so
 * payment readiness blocked every one; 1,325 employees carry 237 TINs. The
 * details get retyped into a spreadsheet every month instead of living on the
 * person they belong to.
 *
 * Every sheet in the book is read, and each one contributes whatever its
 * headings offer — a bank schedule gives accounts, a payroll sheet gives TIN
 * and NSSF numbers, and they are merged per person. Columns are found by
 * their heading, never by position: these books have different numbers of
 * blank leading columns per sheet and counting them has been wrong twice.
 *
 * Nothing is matched by guesswork. Names match on their parts, in any order,
 * and only where exactly one employee fits. Anything less certain is printed
 * for a person to decide, because the cost of being wrong is one person's
 * wages landing in another person's account.
 */
class ImportPaymentAccounts extends Command
{
    protected $signature = 'payroll:import-accounts
        {file : .xlsx payroll workbook}
        {--client= : Client name or id the staff belong to}
        {--apply : Write the details. Without this it only reports}';

    protected $description = 'Import bank, mobile money, TIN and NSSF details from a payroll workbook';

    /** Headings, lowercased, and what they mean. */
    private const HEADINGS = [
        'name'             => 'staff',
        'names'            => 'staff',
        'staff name'       => 'staff',
        'staff names'      => 'staff',

        'bank name'        => 'provider',
        'bank names'       => 'provider',
        'mobile name'      => 'provider',
        'network'          => 'provider',
        'bank'             => 'provider',

        'bank account'     => 'account',
        'account number'   => 'account',
        'mobile account'   => 'account',
        'mobile number'    => 'account',

        'account names'    => 'holder',
        'names registered' => 'holder',

        'branch'           => 'branch',

        'tin number'       => 'tin',
        'tin numbers'      => 'tin',
        'nssf number'      => 'nssf',
        'nssf numbers'     => 'nssf',
    ];

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("No such file: {$path}");
            return self::FAILURE;
        }

        $client = $this->resolveClient();
        if (! $client) return self::FAILURE;

        $people = $this->read($path);
        if (! $people) {
            $this->error('No usable rows. No sheet had a name column alongside something to import.');
            return self::FAILURE;
        }

        $this->info(sprintf('%d people in the workbook — %s', count($people), $client->company_name));

        $staff = Employee::whereHas('clients', fn ($q) => $q->where('clients.id', $client->id))->get();
        $this->line(sprintf('%d employees on this client', $staff->count()));

        [$matched, $unsure] = $this->match($people, $staff);

        $this->newLine();
        $this->info(sprintf('%d matched to exactly one employee', count($matched)));
        if ($unsure) $this->warn(sprintf('%d left for a person to decide', count($unsure)));

        foreach ($unsure as $u) {
            $this->line(sprintf('  %-30s %s', $u['person']['staff'], $u['why']));
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Nothing written. Add --apply to write the ' . count($matched) . ' matched people.');
            $this->table(['field', 'would fill'], $this->coverage($matched));
            return self::SUCCESS;
        }

        $this->table(['field', 'written'], $this->write($matched));

        return self::SUCCESS;
    }

    private function resolveClient(): ?Client
    {
        $given = $this->option('client');
        if (! $given) { $this->error('--client is required.'); return null; }

        $client = is_numeric($given)
            ? Client::find($given)
            : Client::where('company_name', 'like', '%' . $given . '%')->first();

        if (! $client) $this->error("No client matching '{$given}'.");

        return $client;
    }

    /**
     * Every sheet, merged by person.
     *
     * A workbook has the same people on several sheets — the payroll sheet
     * carries their TIN, the bank schedule their account — so each sheet adds
     * what it knows to the person rather than producing a row of its own.
     */
    private function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $people = [];

        foreach ($book->getAllSheets() as $sheet) {
            // Formulas are not calculated: these books carry per-person
            // payslip sheets whose formulas do not all evaluate, and one bad
            // cell would stop the whole import. Nothing read here — names,
            // account numbers, TINs — is ever a formula.
            $grid = $sheet->toArray(null, false, false, false);

            [$headerRow, $map] = $this->findHeadings($grid);
            if ($headerRow < 0 || ! isset($map['staff'])) continue;

            // A name column alone is not worth reading.
            if (count($map) < 2) continue;

            foreach ($grid as $r => $row) {
                if ($r <= $headerRow) continue;

                $staff = trim((string) ($row[$map['staff']] ?? ''));
                if ($staff === '') continue;

                $values = [];
                foreach ($map as $key => $i) {
                    if ($key === 'staff') continue;
                    $v = trim((string) ($row[$i] ?? ''));
                    if ($v !== '') $values[$key] = $v;
                }

                if (! $values) continue;

                $k = $this->key($staff);
                $people[$k]['staff'] = $people[$k]['staff'] ?? $staff;
                foreach ($values as $key => $v) {
                    // First sheet to state something wins; later sheets fill gaps.
                    $people[$k][$key] = $people[$k][$key] ?? $v;
                }
            }
        }

        return array_values(array_filter($people, fn ($p) => count($p) > 1));
    }

    /** @return array{0:int,1:array<string,int>} */
    private function findHeadings(array $grid): array
    {
        foreach ($grid as $r => $row) {
            $map = [];
            foreach ($row as $i => $cell) {
                $h = strtolower(trim(preg_replace('/\s+/', ' ', (string) $cell)));
                if (isset(self::HEADINGS[$h])) $map[self::HEADINGS[$h]] ??= $i;
            }
            if (isset($map['staff']) && count($map) >= 2) return [$r, $map];
        }

        return [-1, []];
    }

    /** Name parts, lowercased and sorted, so order and punctuation stop mattering. */
    private function key(string $name): string
    {
        $p = array_values(array_filter(
            explode(' ', strtolower(preg_replace('/[^a-z ]/i', ' ', $name))),
            fn ($x) => strlen($x) > 1
        ));
        sort($p);

        return implode(' ', $p);
    }

    /** @return array{0:array<int,array>,1:array<int,array>} */
    private function match(array $people, $staff): array
    {
        $index = [];
        foreach ($staff as $e) $index[] = ['emp' => $e, 'parts' => explode(' ', $this->key($e->full_name))];

        $matched = $unsure = [];

        foreach ($people as $person) {
            $want = explode(' ', $this->key($person['staff']));
            $hits = [];

            foreach ($index as $c) {
                if ($want === $c['parts']) { $hits = [$c['emp']]; break; }

                // All of the shorter name's parts appear in the longer one,
                // and at least two: "Haruna James Mafabi" is the "James
                // Haruna" on file; "Atiku Ratibu" is not "Atiku Geoffrey".
                $common = array_intersect($want, $c['parts']);
                if (count($common) >= 2
                    && (count($common) === count($want) || count($common) === count($c['parts']))) {
                    $hits[] = $c['emp'];
                }
            }

            if (count($hits) === 1) $matched[] = ['person' => $person, 'emp' => $hits[0]];
            else $unsure[] = [
                'person' => $person,
                'why' => $hits === [] ? 'no employee of that name' : count($hits) . ' employees could be this person',
            ];
        }

        return [$matched, $unsure];
    }

    /** What each matched person would set on their record. */
    private function attributes(array $person, Employee $emp): array
    {
        $out = [];

        if (isset($person['holder'])) $out['account_name'] = $person['holder'];
        if (isset($person['branch'])) $out['bank_branch']  = $person['branch'];
        if (isset($person['tin']))    $out['tin_number']   = preg_replace('/\D/', '', $person['tin']);
        if (isset($person['nssf']))   $out['nssf_number']  = preg_replace('/\D/', '', $person['nssf']);

        if (isset($person['account'])) {
            $digits = preg_replace('/\D/', '', $person['account']);
            $provider = strtoupper($person['provider'] ?? '');

            // Mobile money and bank details go to different fields, and the
            // payment mode has to follow: somebody in the mobile money
            // schedule still marked Bank Transfer appears in neither file.
            if (in_array($provider, ['MTN', 'AIRTEL'], true)) {
                $out['mobile_money_number'] = $digits;
                $out['payment_mode'] = strtolower($provider);
            } elseif ($provider !== '') {
                $out['bank_account'] = $digits;
                $out['bank_name'] = $person['provider'];
                $out['payment_mode'] = 'bank';
            }
        }

        // Never blank something that is already filled in.
        return array_filter($out, fn ($v, $k) => $v !== '' && $v !== null && blank($emp->$k), ARRAY_FILTER_USE_BOTH);
    }

    private function coverage(array $matched): array
    {
        $tally = [];
        foreach ($matched as $m)
            foreach ($this->attributes($m['person'], $m['emp']) as $k => $v)
                $tally[$k] = ($tally[$k] ?? 0) + 1;

        ksort($tally);

        return array_map(fn ($k, $n) => [$k, $n], array_keys($tally), $tally);
    }

    private function write(array $matched): array
    {
        $tally = [];

        foreach ($matched as $m) {
            $attributes = $this->attributes($m['person'], $m['emp']);
            if (! $attributes) continue;

            $m['emp']->fill($attributes)->save();
            foreach ($attributes as $k => $v) $tally[$k] = ($tally[$k] ?? 0) + 1;
        }

        ksort($tally);

        return array_map(fn ($k, $n) => [$k, $n], array_keys($tally), $tally);
    }
}
