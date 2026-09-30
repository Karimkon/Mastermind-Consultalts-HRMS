<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Appraisal, RatingScale, RatingScaleBand};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingScaleController extends Controller
{
    public function index()
    {
        $scales = RatingScale::with('bands')->withCount('appraisals')->orderBy('name')->get();
        return view('admin.rating-scales.index', compact('scales'));
    }

    public function create()
    {
        return view('admin.rating-scales.edit', ['scale' => new RatingScale(['max_points' => 5])]);
    }

    public function edit(RatingScale $ratingScale)
    {
        $ratingScale->load('bands');
        return view('admin.rating-scales.edit', ['scale' => $ratingScale]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $scale = DB::transaction(fn() => $this->save(new RatingScale, $data));

        return redirect()->route('admin.rating-scales.edit', $scale)
            ->with('success', "\"{$scale->name}\" created.");
    }

    public function update(Request $request, RatingScale $ratingScale)
    {
        $data = $this->validated($request);
        DB::transaction(fn() => $this->save($ratingScale, $data));

        // Cards already scored on this scale keep a percentage and band worked
        // out under the old settings, which would quietly disagree with the new
        // ones. They are recomputed rather than left to drift.
        $restated = 0;
        foreach (Appraisal::where('rating_scale_id', $ratingScale->id)->get() as $appraisal) {
            $appraisal->recalculate();
            $restated++;
        }

        return back()->with('success', "\"{$ratingScale->name}\" saved."
            . ($restated ? " {$restated} appraisal(s) restated." : ''));
    }

    public function makeDefault(RatingScale $ratingScale)
    {
        $ratingScale->makeDefault();
        return back()->with('success', "\"{$ratingScale->name}\" is now the default scale.");
    }

    public function destroy(RatingScale $ratingScale)
    {
        // Two guards, because both failures are silent and expensive: deleting
        // a scale in use would null the link on live cards, and deleting the
        // default would leave new cards with nothing to inherit.
        if ($ratingScale->appraisals()->exists()) {
            return back()->with('error',
                'That scale is in use on ' . $ratingScale->appraisals()->count()
                . ' appraisal(s), so it cannot be deleted. Deactivate it instead.');
        }

        if ($ratingScale->is_default) {
            return back()->with('error',
                'That is the default scale. Make another scale the default first.');
        }

        $name = $ratingScale->name;
        $ratingScale->delete();

        return redirect()->route('admin.rating-scales.index')
            ->with('success', "\"{$name}\" deleted.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                => 'required|string|max:100',
            'description'         => 'nullable|string|max:255',
            'max_points'          => 'required|integer|min:2|max:10',
            'is_active'           => 'nullable|boolean',
            'bands'               => 'required|array|min:2',
            'bands.*.label'       => 'required|string|max:60',
            'bands.*.min_percent' => 'required|numeric|min:0|max:100',
            'bands.*.range_label' => 'nullable|string|max:40',
        ], [
            'bands.required'   => 'A scale needs at least two bands.',
            'max_points.min'   => 'A scale needs at least two points.',
            'max_points.max'   => 'Ten points is the most the score card can show.',
        ]);
    }

    private function save(RatingScale $scale, array $data): RatingScale
    {
        $scale->fill([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'max_points'  => $data['max_points'],
            'is_active'   => (bool) ($data['is_active'] ?? false),
        ])->save();

        $scale->bands()->delete();

        // One band per point, in order. Anything typed beyond max_points is
        // dropped rather than stored, so the scale and its bands cannot
        // disagree about how many steps it has.
        $points = 1;
        foreach ($data['bands'] as $band) {
            if ($points > $scale->max_points) break;

            RatingScaleBand::create([
                'rating_scale_id' => $scale->id,
                'points'          => $points++,
                'label'           => $band['label'],
                'min_percent'     => $band['min_percent'],
                'range_label'     => $band['range_label'] ?? null,
            ]);
        }

        return $scale->refresh();
    }
}
