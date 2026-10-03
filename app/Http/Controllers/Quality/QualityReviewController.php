<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityCheckRun;
use App\Models\QualityNonconformity;
use App\Models\QualityReview;
use Illuminate\Http\Request;

class QualityReviewController extends Controller
{
    public function index()
    {
        $reviews = QualityReview::with('chair')->latest()->paginate(20);

        return view('quality.reviews.index', compact('reviews'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'held_on' => 'nullable|date',
        ]);

        // Seed the review with the current headline score, so the management
        // record reflects where quality stood when it was opened.
        $latest = QualityCheckRun::where('status', 'completed')->latest('completed_at')->first();

        $review = QualityReview::create($data + [
            'reference' => QualityReview::nextReference(),
            'status' => 'draft',
            'chaired_by' => $request->user()?->id,
            'overall_score' => $latest?->score,
        ]);

        return redirect()->route('quality.reviews.show', $review)
            ->with('success', "Review {$review->reference} opened.");
    }

    public function show(QualityReview $review)
    {
        $review->load('chair');
        $openNc = QualityNonconformity::whereIn('status', QualityNonconformity::OPEN_STATES)->count();
        $latest = QualityCheckRun::where('status', 'completed')->latest('completed_at')->first();

        return view('quality.reviews.show', compact('review', 'openNc', 'latest'));
    }

    public function update(Request $request, QualityReview $review)
    {
        $data = $request->validate([
            'summary' => 'nullable|string',
            'decisions' => 'nullable|string',
            'status' => 'required|in:draft,completed',
            'held_on' => 'nullable|date',
        ]);

        $review->update($data);

        return back()->with('success', 'Review updated.');
    }
}
