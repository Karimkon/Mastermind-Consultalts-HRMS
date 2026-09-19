<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The decision on whether one public holiday is paid, for one client.
 *
 * No row, or a row still `pending`, means nobody is paid for simply staying
 * home that day — the holiday only becomes payable once someone approves it.
 * Someone who actually worked is paid regardless; see PayrollService.
 */
class HolidayPayApproval extends Model
{
    protected $table = 'holiday_pay_approvals';

    protected $fillable = [
        'public_holiday_id', 'client_id', 'status',
        'decided_by', 'decided_at', 'note',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function holiday() { return $this->belongsTo(PublicHoliday::class, 'public_holiday_id'); }
    public function client()  { return $this->belongsTo(Client::class); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }

    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isPending(): bool  { return $this->status === 'pending'; }

    /** Is this holiday payable to staff of the given client? */
    public static function isPayableFor(int $holidayId, ?int $clientId): bool
    {
        return static::where('public_holiday_id', $holidayId)
            ->where(fn ($q) => $q->where('client_id', $clientId)->orWhereNull('client_id'))
            ->where('status', 'approved')
            ->exists();
    }
}
