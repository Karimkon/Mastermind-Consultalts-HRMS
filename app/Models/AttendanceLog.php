<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'clock_in', 'clock_out', 'status',
        'overtime_hours', 'lat', 'lng', 'note', 'approved_by',
        'client_id', 'clock_out_lat', 'clock_out_lng', 'distance_metres',
    ];

    protected $casts = [
        'date'             => 'date',
        'clock_in'         => 'datetime',
        'clock_out'        => 'datetime',
        'distance_metres'  => 'float',
        'clock_out_lat'    => 'float',
        'clock_out_lng'    => 'float',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function client()   { return $this->belongsTo(Client::class); }
}
