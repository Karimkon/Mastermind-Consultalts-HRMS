<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table    = 'notifications';
    protected $fillable = ['user_id', 'type', 'title', 'body', 'data', 'read_at'];
    protected $casts    = ['data' => 'array', 'read_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }

    /**
     * Which part of the menu each kind of notice belongs to.
     *
     * The bell said "1" and nothing said where. Somebody with an improvement
     * plan opened, a payslip released and a leave request answered saw a single
     * number and had to go looking through every screen to find out which was
     * which — and 439 payslip notices had gone unread, because a payslip
     * arriving looked exactly like nothing arriving if no menu item changed.
     *
     * A type not listed here still reaches the bell; it just has no menu item
     * of its own to sit on.
     */
    public const AREAS = [
        'pip'                   => 'pips',
        'goal'                  => 'goals',
        'payroll_processed'     => 'payslips',
        'payroll_stage'         => 'payroll',
        'leave_submitted'       => 'leave',
        'leave_status'          => 'leave',
        'leave_adjusted'        => 'leave',
        'leave_cover_nominated' => 'leave',
        'meeting_invite'        => 'calendar',
        'training_enrolled'     => 'training',
        'training'              => 'training_plan',
        'appraisal'             => 'appraisals',
        'review_submitted'      => 'appraisals',
        'document_expiry'       => 'documents',
        'new_application'       => 'recruitment',
        'probation_due'         => 'probation',
        'change_requested'      => 'change_approvals',
        'quality_alert'         => 'quality',
    ];

    /**
     * Unread counts per menu area for one user, as ['leave' => 2, 'pips' => 1].
     *
     * One grouped query: the sidebar renders on every page, so this must not
     * turn into a query per menu item.
     *
     * @return array<string,int>
     */
    public static function unreadCountsFor(?User $user): array
    {
        if (! $user) return [];

        $byType = static::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $byArea = [];
        foreach ($byType as $type => $total) {
            $area = self::AREAS[$type] ?? null;
            if (! $area) continue;
            $byArea[$area] = ($byArea[$area] ?? 0) + (int) $total;
        }

        return $byArea;
    }

    /**
     * Clear the notices for one area, because the person has just opened it.
     *
     * Opening the bell deliberately does NOT mark anything read — a notice
     * could be cleared a second after it arrived and look as though it had
     * never come. Opening the screen the notice is about is a different act:
     * that is somebody actually reading it, and a badge that never clears is
     * one people stop seeing.
     */
    public static function markAreaRead(?User $user, string $area): int
    {
        if (! $user) return 0;

        $types = array_keys(self::AREAS, $area, true);
        if (! $types) return 0;

        return static::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereIn('type', $types)
            ->update(['read_at' => now()]);
    }
}
