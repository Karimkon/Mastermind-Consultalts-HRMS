<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Employee;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Put bank and mobile-money details onto employee records from the payment
 * schedules that already get prepared every month.
 *
 * Those schedules are the only place these details exist: 439 Roofings staff
 * are all set to Bank Transfer and not one carries an account number, so the
 * payment-readiness screen blocks every one of them. The details are retyped
 * into a workbook each month instead of living on the employee.
 *
 * Nothing is matched by guesswork. A name is matched on its parts, in any
 * order, and only where exactly one employee fits. Anything less certain is
 * printed for a person to decide, because the cost of being wrong here is
 * one person's wages landing in another person's account.
 */
class ImportPaymentAccounts extends Command
{
    protected $signature = 'payroll:import-accounts
        {file : .xlsx holding the Bank Schedule and/or Mobile Money Schedule sheets}
        {--client= : Client name or id the staff belong to}
        {--apply : Write the details. Without this it only reports}
        {--header=17 : 1-based row the column headings sit on}';

    protected $description = 'Import bank and mobile money details from a payment schedule';

    /** Column headings, lowercased, mapped to what they mean. */
    private const COLUMNS = [
        'staff names'    => 'staff',
        'bank names'     => 'provider',
        'mobile name'    => 'provider',
        'bank account'   => 'account',
        'mobile account' => 'account',
        'account names'  => 'holder',
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

        $rows = $this->readSchedules($path);
        if (! $rows) {
            $this->error('No schedule rows found. Check --header, or that the sheet names contain "Schedule".');
            return self::FAILURE;
        }

        $this->info(sprintf('%d rows from the workbook, %s', count($rows), $client->company_name));

        $staff = Employee::whereHas('clients', fn ($q) => $q->where('clients.id', $client->id))->get();
        $this->line(sprintf('%d employees on this client', $staff->count()));

        [$matched, $unsure] = $this->match($rows, $staff);

        $this->newLine();
        $this->info(sprintf('%d matched to exactly one employee', count($matched)));
        $this->warn(sprintf('%d left for a person to decide', count($unsure)));

        if ($unsure) {
            $this->newLine();
            $this->line('Not imported:');
            foreach ($unsure as $u) {
                $this->line(sprintf('  %-30s %-10s %-18s %s',
                    $u['row']['staff'], strtoupper($u['row']['provider']), $u['row']['account'], $u['why']));
            }
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Nothing written. Add --apply to write the ' . count($matched) . ' matched rows.');
            return self::SUCCESS;
        }

        $written = $this->write($matched);

        $this->newLine();
        $this->info("Written to {$written} employees.");

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
     * Every "… Schedule" sheet in the workbook, read by its own headings.
     *
     * The headings are read rather than assumed because the bank sheet and
     * the mobile money sheet differ ("Bank Account" against "Mobile
     * Account"), and because a blank first column shifts everything right.
     */
    private function readSchedules(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $headerRow = (int) $this->option('header');
        $out = [];

        foreach ($book->getAllSheets() as $sheet) {
            if (! str_contains(strtolower($sheet->getTitle()), 'schedule')) continue;

            $grid = $sheet->toArray(null, true, false, false);
            if (! isset($grid[$headerRow - 1])) continue;

            $map = [];
            foreach ($grid[$headerRow - 1] as $i => $heading) {
                $key = strtolower(trim((string) $heading));
                if (isset(self::COLUMNS[$key])) $map[self::COLUMNS[$key]] = $i;
            }

            if (! isset($map['staff'], $map['account'])) continue;

            foreach ($grid as $r => $row) {
                if ($r < $headerRow) continue;

                $staff   = trim((string) ($row[$map['staff']] ?? ''));
                $account = preg_replace('/\D/', '', (string) ($row[$map['account']] ?? ''));

                // The table ends where the totals and signatures begin.
                if ($staff === '' || $account === '') continue;

                $out[] = [
                    'sheet'    => trim($sheet->getTitle()),
                    'row'      => $r + 1,
                    'staff'    => $staff,
                    'provider' => trim((string) ($row[$map['provider'] ?? -1] ?? '')),
                    'account'  => $account,
                    'holder'   => trim((string) ($row[$map['holder'] ?? -1] ?? '')),
                ];
            }
        }

        return $out;
    }

    /** Name parts, lowercased and sorted, so order and punctuation stop mattering. */
    private function parts(string $name): array
    {
        $n = strtolower(preg_replace('/[^a-z ]/i', ' ', $name));
        $p = array_values(array_filter(explode(' ', $n), fn ($x) => strlen($x) > 1));
        sort($p);

        return $p;
    }

    /** @return array{0: array<int,array>, 1: array<int,array>} matched, unsure */
    private function match(array $rows, $staff): array
    {
        $index = [];
        foreach ($staff as $e) $index[] = ['emp' => $e, 'parts' => $this->parts($e->full_name)];

        $matched = $unsure = [];

        foreach ($rows as $row) {
            $want = $this->parts($row['staff']);
            $hits = [];

            foreach ($index as $candidate) {
                $have = $candidate['parts'];
                if ($want === $have) { $hits = [$candidate['emp']]; break; }

                // All of the shorter name's parts appear in the longer one,
                // and at least two of them: "Haruna James Mafabi" is the
                // "James Haruna" on file, "Atiku Ratibu" is not "Atiku
                // Geoffrey".
                $common = array_intersect($want, $have);
                if (count($common) >= 2 && (count($common) === count($want) || count($common) === count($have))) {
                    $hits[] = $candidate['emp'];
                }
            }

            if (count($hits) === 1) {
                $matched[] = ['row' => $row, 'emp' => $hits[0]];
            } else {
                $unsure[] = [
                    'row' => $row,
                    'why' => $hits === [] ? 'no employee of that name' : count($hits) . ' employees could be this person',
                ];
            }
        }

        return [$matched, $unsure];
    }

    /**
     * Mobile money and bank details go to different fields, and the payment
     * mode has to follow — a row in the mobile money schedule whose employee
     * is still marked Bank Transfer would be left out of both files.
     */
    private function write(array $matched): int
    {
        $written = 0;

        foreach ($matched as $m) {
            $row = $m['row'];
            $emp = $m['emp'];
            $provider = strtoupper($row['provider']);

            $attributes = ['account_name' => $row['holder'] ?: null];

            if (in_array($provider, ['MTN', 'AIRTEL'], true)) {
                $attributes['mobile_money_number'] = $row['account'];
                $attributes['payment_mode'] = strtolower($provider);
            } else {
                $attributes['bank_account'] = $row['account'];
                $attributes['bank_name'] = $row['provider'] ?: $emp->bank_name;
                $attributes['payment_mode'] = 'bank';
            }

            $emp->fill($attributes)->save();
            $written++;
        }

        return $written;
    }
}
