<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = ['employee_id', 'date', 'clock_in', 'clock_out', 'status', 'overtime_hours', 'lat', 'lng', 'note', 'approved_by'];
    protected $casts    = ['date' => 'date', 'clock_in' => 'datetime', 'clock_out' => 'datetime'];
    public function employee() { return $this->belongsTo(Employee::class); }
}