<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table    = 'notifications';
    protected $fillable = ['user_id', 'type', 'title', 'body', 'data', 'read_at'];
    protected $casts    = ['data' => 'array', 'read_at' => 'datetime'];

    /** Serialised with every notice, so the bell has somewhere to send you. */
    protected $appends  = ['link'];

    public function user() { return $this->belongsTo(User::class); }

    /**
     * Where each kind of notice leads: [route name, the data key holding its id].
     *
     * A null key means the route takes no parameter — "your payslip is ready"
     * goes to the list of your payslips, because the notice is addressed to
     * the employee and the per-slip route is the payroll officer's.
     *
     * Only notices whose data key is known are listed. A type left out keeps
     * whatever absolute url it was written with, and a type with neither is
     * simply not a link — which is honest, and better than a route() that
     * throws inside the bell.
     */
    private const LINKS = [
        'payroll_stage'         => ['payroll.show',       'payroll_run_id'],
        'payroll_processed'     => ['employee.payslips',  null],
        'leave_submitted'       => ['leaves.show',        'leave_id'],
        'leave_status'          => ['leaves.show',        'leave_id'],
        'leave_adjusted'        => ['leaves.show',        'leave_id'],
        'leave_cover_nominated' => ['leaves.show',        'leave_id'],
        'meeting_invite'        => ['meetings.show',      'meeting_id'],
        'new_application'       => ['recruitment.candidates.show', 'candidate_id'],
        'chat'                  => ['chat.index',         null],
        'appraisal'             => ['appraisals.show',    'appraisal_id'],
        'review_submitted'      => ['appraisals.show',    'appraisal_id'],
        'pip'                   => ['pips.show',          'pip_id'],
    ];

    /**
     * The page this notice is about, or null when there is not one.
     *
     * The bell row has carried an href since the appraisal fix, but it read
     * `data.url` and almost nothing writes one: of 546 notices on the live
     * system, 34 had a url and 512 were dead ends. Every type does carry the
     * id of the thing it is about, so the link is worked out from that instead
     * of backfilling the rows — which also covers everything written from here
     * on, and rows nobody would have thought to backfill.
     *
     * The derived route is preferred over a stored url deliberately. Those
     * were written absolute, and the pip notices still point at
     * mastermind.autos, a host this system no longer answers on.
     */
    public function getLinkAttribute(): ?string
    {
        $map = self::LINKS[$this->type] ?? null;

        if ($map) {
            [$route, $key] = $map;

            if (\Illuminate\Support\Facades\Route::has($route)) {
                if ($key === null) {
                    return route($route);
                }

                $id = is_array($this->data) ? ($this->data[$key] ?? null) : null;
                if ($id) {
                    return route($route, $id);
                }
            }
        }

        return is_array($this->data) ? ($this->data['url'] ?? null) : null;
    }

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
