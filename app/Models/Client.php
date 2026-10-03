<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PayrollRun;

class Client extends Model
{

    /**
     * The premises this client operates from.
     *
     * Many, because several clients are more than one place: Roofings runs
     * Lubowa and Industrial Area about ten kilometres apart, and a single
     * coordinate would put one of those two workforces permanently outside the
     * fence.
     */
    public function sites(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientSite::class);
    }

    public function activeSites(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->sites()->where('is_active', true);
    }

    /**
     * Every place a clock-in may legitimately happen, newest model first.
     *
     * Falls back to the single `work_site_lat`/`work_site_lng` pair a client
     * carried before sites existed, so a client that has not been migrated keeps
     * behaving exactly as it did. The fallback is synthesised rather than saved:
     * writing a row here as a side effect of reading would migrate data at a
     * moment nobody asked for it.
     *
     * @return \Illuminate\Support\Collection<int, ClientSite>
     */
    public function fenceSites(): \Illuminate\Support\Collection
    {
        $sites = $this->activeSites()->get();

        if ($sites->isNotEmpty()) {
            return $sites;
        }

        if (! $this->work_site_lat || ! $this->work_site_lng) {
            return collect();
        }

        return collect([
            new ClientSite([
                'client_id' => $this->id,
                'name' => 'Main site',
                'address' => $this->work_site_address,
                'lat' => $this->work_site_lat,
                'lng' => $this->work_site_lng,
                'geo_fence_radius' => $this->geo_fence_radius ?? 100,
                'is_active' => true,
            ]),
        ]);
    }

    /** Whether anything at all can be checked for this client. */
    public function hasGeoFence(): bool
    {
        return $this->fenceSites()->isNotEmpty();
    }

    /**
     * The site a point is closest to, with the distance to it.
     *
     * Nearest rather than first: somebody at Industrial Area should be measured
     * against Industrial Area, not against whichever site happens to have the
     * lower id.
     *
     * @return array{ClientSite, float}|null
     */
    /**
     * The nearest mapped premises belonging to ANY client.
     *
     * For head office staff, who spend the week visiting clients: measuring
     * them against head office marked every visit as off-site and sent it to
     * HR to sort out. Here the whole estate is the fence.
     *
     * Twenty clients and ten sites, so this is a single query and a loop rather
     * than trigonometry in SQL - and it picks up the clients still carrying the
     * legacy work_site_lat through fenceSites().
     *
     * @return array{0: \App\Models\ClientSite, 1: float, 2: \App\Models\Client}|null
     */
    public static function nearestSiteAnywhere(float $lat, float $lng): ?array
    {
        $best = null;

        foreach (static::with('activeSites')->get() as $client) {
            $nearest = $client->nearestSite($lat, $lng);
            if ($nearest === null) {
                continue;
            }

            [$site, $distance] = $nearest;

            if ($best === null || $distance < $best[1]) {
                $best = [$site, $distance, $client];
            }
        }

        return $best;
    }
    public function nearestSite(float $lat, float $lng): ?array
    {
        $nearest = null;
        $best = null;

        foreach ($this->fenceSites() as $site) {
            $distance = $site->distanceTo($lat, $lng);

            if ($best === null || $distance < $best) {
                $best = $distance;
                $nearest = $site;
            }
        }

        return $nearest === null ? null : [$nearest, $best];
    }
    protected $fillable = [
        'user_id', 'account_manager_id', 'supervisor_employee_id', 'company_name', 'contact_person',
        'phone', 'email',
        'industry', 'address', 'deployment_area', 'work_area', 'status', 'notes',
        'payment_day', 'work_site_address', 'work_site_lat', 'work_site_lng', 'geo_fence_radius',
        'attendance_enabled', 'is_head_office',
        // Payroll formula settings
        'gross_up_paye', 'gpa_wmc_rate', 'billing_rate_multiplier', 'payroll_type',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'work_site_lat'          => 'float',
        'work_site_lng'          => 'float',
        'payment_day'            => 'integer',
        'geo_fence_radius'       => 'integer',
        'attendance_enabled'     => 'boolean',
        'gross_up_paye'          => 'boolean',
        'gpa_wmc_rate'           => 'float',
        'billing_rate_multiplier'=> 'float',
    ];

    /** Human-readable payroll type label */
    public function getPayrollTypeLabelAttribute(): string
    {
        return match($this->payroll_type ?? 'daily') {
            'hourly'  => 'Hourly (Casual)',
            'daily'   => 'Daily (Casual)',
            'monthly' => 'Monthly (Contract)',
            'mixed'   => 'Mixed (Casual + Contract)',
            default   => ucfirst($this->payroll_type ?? 'daily'),
        };
    }

    public function user()           { return $this->belongsTo(User::class); }
    public function accountManager() { return $this->belongsTo(User::class, 'account_manager_id'); }
    public function supervisor()     { return $this->belongsTo(Employee::class, 'supervisor_employee_id'); }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'client_employee_assignments')
                    ->withPivot('notes', 'assigned_by')
                    ->withTimestamps();
    }

    public function payrollRuns()
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function transferHistory()
    {
        return $this->hasMany(EmployeeClientTransfer::class)->orderByDesc('effective_date');
    }

    public function jobPostings()
    {
        return $this->belongsToMany(JobPosting::class, 'client_job_assignments')
                    ->withPivot('notes', 'assigned_by')
                    ->withTimestamps();
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->status === 'active'
            ? '<span class="badge-green">Active</span>'
            : '<span class="badge-gray">Inactive</span>';
    }
}
