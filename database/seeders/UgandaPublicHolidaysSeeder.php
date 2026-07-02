<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UgandaPublicHolidaysSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('public_holidays')->where('country', 'UG')->delete();

        $holidays = [];

        // Easter Sunday dates per year (used to derive Good Friday & Easter Monday)
        $easter = [
            2024 => '2024-03-31',
            2025 => '2025-04-20',
            2026 => '2026-04-05',
            2027 => '2027-03-28',
            2028 => '2028-04-16',
            2029 => '2029-04-01',
            2030 => '2030-04-21',
            2031 => '2031-04-13',
            2032 => '2032-03-28',
            2033 => '2033-04-17',
            2034 => '2034-04-09',
            2035 => '2035-03-25',
        ];

        // Eid al-Fitr (end of Ramadan) approximate dates for Uganda
        $eidFitr = [
            2024 => '2024-04-10',
            2025 => '2025-03-30',
            2026 => '2026-03-20',
            2027 => '2027-03-09',
            2028 => '2028-02-26',
            2029 => '2029-02-14',
            2030 => '2030-02-04',
            2031 => '2031-01-25',
            2032 => '2032-01-14',
            2033 => '2033-01-03',
            2034 => '2034-12-13',
            2035 => '2035-12-03',
        ];

        // Eid al-Adha approximate dates for Uganda
        $eidAdha = [
            2024 => '2024-06-17',
            2025 => '2025-06-07',
            2026 => '2026-05-27',
            2027 => '2027-05-16',
            2028 => '2028-05-05',
            2029 => '2029-04-25',
            2030 => '2030-04-14',
            2031 => '2031-04-04',
            2032 => '2032-03-24',
            2033 => '2033-03-13',
            2034 => '2034-03-02',
            2035 => '2035-02-20',
        ];

        foreach (range(2024, 2035) as $year) {
            $fixed = [
                ['date' => "$year-01-01", 'name' => "New Year's Day",         'type' => 'national'],
                ['date' => "$year-01-26", 'name' => 'Liberation Day',         'type' => 'national'],
                ['date' => "$year-03-08", 'name' => "International Women's Day", 'type' => 'national'],
                ['date' => "$year-05-01", 'name' => 'Labour Day',              'type' => 'national'],
                ['date' => "$year-06-03", 'name' => "Martyrs' Day",           'type' => 'national'],
                ['date' => "$year-06-09", 'name' => 'National Heroes Day',    'type' => 'national'],
                ['date' => "$year-10-09", 'name' => 'Independence Day',       'type' => 'national'],
                ['date' => "$year-12-25", 'name' => 'Christmas Day',          'type' => 'national'],
                ['date' => "$year-12-26", 'name' => 'Boxing Day',             'type' => 'national'],
            ];

            foreach ($fixed as $h) {
                $holidays[] = array_merge($h, ['year' => $year, 'country' => 'UG', 'is_paid' => true]);
            }

            // Easter-based
            if (isset($easter[$year])) {
                $easterSun   = Carbon::parse($easter[$year]);
                $goodFriday  = $easterSun->copy()->subDays(2);
                $easterMonday= $easterSun->copy()->addDay();

                $holidays[] = ['date' => $goodFriday->toDateString(),   'name' => 'Good Friday',   'type' => 'religious', 'year' => $year, 'country' => 'UG', 'is_paid' => true];
                $holidays[] = ['date' => $easterMonday->toDateString(), 'name' => 'Easter Monday', 'type' => 'religious', 'year' => $year, 'country' => 'UG', 'is_paid' => true];
            }

            // Islamic
            if (isset($eidFitr[$year])) {
                $holidays[] = ['date' => $eidFitr[$year], 'name' => 'Eid al-Fitr',  'type' => 'religious', 'year' => $year, 'country' => 'UG', 'is_paid' => true];
            }
            if (isset($eidAdha[$year])) {
                $holidays[] = ['date' => $eidAdha[$year], 'name' => 'Eid al-Adha',  'type' => 'religious', 'year' => $year, 'country' => 'UG', 'is_paid' => true];
            }
        }

        $now = now();
        foreach ($holidays as &$h) {
            $h['created_at'] = $now;
            $h['updated_at'] = $now;
        }

        // Insert ignoring duplicates
        foreach (array_chunk($holidays, 50) as $chunk) {
            DB::table('public_holidays')->insertOrIgnore($chunk);
        }

        $this->command->info('Uganda public holidays seeded for 2024–2035 (' . count($holidays) . ' entries).');
    }
}
