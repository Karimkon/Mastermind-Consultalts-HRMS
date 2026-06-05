<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollComment extends Model
{
    protected $fillable = ['payroll_run_id','user_id','action','from_status','to_status','comment'];

    public function user()      { return $this->belongsTo(User::class); }
    public function payrollRun(){ return $this->belongsTo(PayrollRun::class); }
}
