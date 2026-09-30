<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opening a screen clears that screen's badge.
 *
 * Without this the counts would only ever climb: the bell's "mark all read"
 * clears everything at once, which is the opposite problem — it wipes notices
 * for screens you have not looked at. Tying the clear to the route means the
 * number goes away exactly when the person has in fact read the thing.
 *
 * Deliberately narrow: only the landing route of each area, only on a GET, and
 * never on the JSON polls the bell makes in the background.
 */
class ClearAreaNotifications
{
    /** Route name → the notification area it settles. */
    private const ROUTE_AREAS = [
        'pips.index'                     => 'pips',
        'goals.index'                    => 'goals',
        'employee.payslips'              => 'payslips',
        'payroll.index'                  => 'payroll',
        'leaves.index'                   => 'leave',
        'meetings.calendar'              => 'calendar',
        'meetings.index'                 => 'calendar',
        'training.index'                 => 'training',
        'training.plan.index'            => 'training_plan',
        'appraisals.index'               => 'appraisals',
        'employee.documents'             => 'documents',
        'recruitment.candidates.index'   => 'recruitment',
        'probation.index'                => 'probation',
        'admin.change-approvals.index'   => 'change_approvals',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Runs before the response so the sidebar on this very page load
        // already shows the cleared count, rather than staying lit until the
        // next click.
        if ($request->isMethod('GET')
            && ! $request->expectsJson()
            && ($user = $request->user())) {

            $area = self::ROUTE_AREAS[$request->route()?->getName() ?? ''] ?? null;

            if ($area) {
                Notification::markAreaRead($user, $area);
            }
        }

        return $next($request);
    }
}
