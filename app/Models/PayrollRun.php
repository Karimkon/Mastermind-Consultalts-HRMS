<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model {
    protected $fillable = [
        'title','month','year','status','client_id',
        'processed_by','processed_at','approved_by','approved_at',
        'payment_date','notes','locked_at','locked_by',
    ];
    protected $casts = [
        'processed_at' => 'datetime',
        'approved_at'  => 'datetime',
        'locked_at'    => 'datetime',
        'payment_date' => 'date',
    ];

    public function payslips()   { return $this->hasMany(Payslip::class); }
    public function processor()  { return $this->belongsTo(User::class, 'processed_by'); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function locker()     { return $this->belongsTo(User::class, 'locked_by'); }
    public function client()     { return $this->belongsTo(Client::class); }

    public function isLocked(): bool  { return !is_null($this->locked_at); }
    public function isEditable(): bool { return !$this->isLocked() && !in_array($this->status, ['paid']); }

    public function getStatusBadgeAttribute(): string {
        if ($this->isLocked()) {
            return '<span class="badge-red"><i class="fas fa-lock mr-1"></i>Locked</span>';
        }
        return match($this->status) {
            'draft'      => '<span class="badge-gray">Draft</span>',
            'processing' => '<span class="badge-yellow">Processing</span>',
            'processed'  => '<span class="badge-blue">Processed</span>',
            'approved'   => '<span class="badge-green">Approved</span>',
            'paid'       => '<span class="badge-green"><i class="fas fa-check mr-1"></i>Paid</span>',
            default      => '',
        };
    }
}
