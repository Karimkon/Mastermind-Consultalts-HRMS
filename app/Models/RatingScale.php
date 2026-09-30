<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A rating scale: how many points a KPI can score, and what each score means.
 *
 * Replaces the constants that used to live on Appraisal. Nothing here assumes
 * five points - the scale's own `max_points` is the divisor behind the overall
 * percentage, so a card rated out of 10 works out correctly without a second
 * code path.
 */
class RatingScale extends Model
{
    protected $fillable = ['name', 'description', 'max_points', 'is_default', 'is_active'];

    protected $casts = [
        'max_points' => 'integer',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function bands()
    {
        return $this->hasMany(RatingScaleBand::class)->orderBy('points');
    }

    public function appraisals() { return $this->hasMany(Appraisal::class); }

    /**
     * The scale used when a card names none.
     *
     * Falls back to any active scale, then to any scale at all, so a card can
     * still be scored if somebody clears the default flag by accident - an
     * appraisal in progress should not break because of a settings mistake.
     */
    public static function default(): ?self
    {
        return static::with('bands')->where('is_default', true)->first()
            ?? static::with('bands')->where('is_active', true)->orderBy('id')->first()
            ?? static::with('bands')->orderBy('id')->first();
    }

    /** Make this the default, and the only one. */
    public function makeDefault(): void
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true, 'is_active' => true]);
    }

    /** Which band a percentage falls into: the highest whose floor it clears. */
    public function bandFor(?float $percent): ?int
    {
        if ($percent === null) return null;

        $match = null;
        foreach ($this->bands as $band) {
            if ($percent >= $band->min_percent) $match = $band->points;
        }

        // Below every floor still means the bottom band, not "no band" - a score
        // of zero is Poor, not unrated.
        return $match ?? $this->bands->first()?->points;
    }

    public function labelFor(?int $points): ?string
    {
        return $this->bands->firstWhere('points', $points)?->label;
    }

    /** True when the bands cover every point on the scale. */
    public function isComplete(): bool
    {
        return $this->bands->pluck('points')->sort()->values()->all()
            === range(1, $this->max_points);
    }
}
