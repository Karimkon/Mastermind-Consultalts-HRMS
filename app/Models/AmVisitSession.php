<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AmVisitSession extends Model
{
    protected $table = 'am_visit_sessions';

    protected $fillable = [
        'user_id',
        'client_id',
        'lat_in',
        'lng_in',
        'lat_out',
        'lng_out',
        'clocked_in_at',
        'clocked_out_at',
        'site_address',
        'notes',
    ];

    protected $casts = [
        'clocked_in_at'  => 'datetime',
        'clocked_out_at' => 'datetime',
        'lat_in'         => 'float',
        'lng_in'         => 'float',
        'lat_out'        => 'float',
        'lng_out'        => 'float',
    ];

    /**
     * The AM (User) who owns this visit session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The client being visited.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Duration in decimal hours between clock-in and clock-out.
     * Returns null if not yet clocked out.
     */
    public function getDurationHoursAttribute(): ?float
    {
        if (!$this->clocked_in_at || !$this->clocked_out_at) {
            return null;
        }

        return round(
            Carbon::parse($this->clocked_in_at)->diffInMinutes(Carbon::parse($this->clocked_out_at)) / 60,
            2
        );
    }
}
