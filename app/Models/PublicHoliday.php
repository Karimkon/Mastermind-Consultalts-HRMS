<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PublicHoliday extends Model
{
    protected $fillable = ['date', 'name', 'type', 'country', 'is_paid', 'year'];

    protected $casts = [
        'date'    => 'date',
        'is_paid' => 'boolean',
        'year'    => 'integer',
    ];

    public static function forMonth(int $year, int $month, string $country = 'UG'): \Illuminate\Support\Collection
    {
        return static::where('year', $year)
            ->where('country', $country)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();
    }

    public static function countInRange(string $from, string $to, string $country = 'UG'): int
    {
        return static::where('country', $country)
            ->where('is_paid', true)
            ->whereBetween('date', [$from, $to])
            ->count();
    }

    public static function datesInRange(string $from, string $to, string $country = 'UG'): array
    {
        return static::where('country', $country)
            ->where('is_paid', true)
            ->whereBetween('date', [$from, $to])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();
    }
}
