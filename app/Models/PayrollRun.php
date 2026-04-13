<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollRun extends Model {
    protected $fillable = ['title','month','year','status','processed_by','processed_at','approved_by','approved_at','payment_date','notes'];
    protected $casts    = ['processed_at' => 'datetime', 'approved_at' => 'datetime', 'payment_date' => 'date'];
    public function payslips()   { return $this->hasMany(Payslip::class); }
    public function processor()  { return $this->belongsTo(User::class, 'processed_by'); }
    public function getStatusBadgeAttribute(): string {
        return match($this->status) {
            'draft'      => '<span class="badge-gray">Draft</span>',
            'processing' => '<span class="badge-yellow">Processing</span>',
            'processed'  => '<span class="badge-blue">Processed</span>',
            'approved'   => '<span class="badge-green">Approved</span>',
            'paid'       => '<span class="badge-green">Paid</span>',
            default      => '',
        };
    }
}
