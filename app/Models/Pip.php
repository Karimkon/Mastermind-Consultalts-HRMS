<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Pip extends Model
{
    protected $table    = 'pips';
    protected $fillable = ['employee_id','cycle_id','title','description','start_date','end_date','objectives','status','outcome','created_by'];
    protected $casts    = ['start_date' => 'date', 'end_date' => 'date', 'objectives' => 'array'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function cycle()    { return $this->belongsTo(PerformanceCycle::class); }
}
