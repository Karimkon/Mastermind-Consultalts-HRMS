<?php
namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function store(Request $request, Candidate $candidate)
    {
        $request->validate([
            'offer_amount' => 'required|numeric|min:0',
            'offer_date'   => 'required|date',
            'offer_expiry' => 'required|date|after:offer_date',
        ]);

        $candidate->update([
            'offer_amount' => $request->offer_amount,
            'offer_date'   => $request->offer_date,
            'offer_expiry' => $request->offer_expiry,
        ]);

        app(\App\Services\RecruitmentPipeline::class)->moveTo($candidate, 'offer');

        return back()->with('success', 'Offer extended to ' . $candidate->first_name . '.');
    }

    public function accept(Candidate $candidate)
    {
        app(\App\Services\RecruitmentPipeline::class)->moveTo($candidate, 'hired');
        return back()->with('success', $candidate->first_name . ' has accepted the offer.');
    }

    public function reject(Candidate $candidate)
    {
        // The candidate turned the offer down. Recorded, but no notice sent -
        // the preset rejection wording tells somebody they were not
        // shortlisted, which is not what happened here.
        $candidate->forceFill(['offer_amount' => null])->save();
        app(\App\Services\RecruitmentPipeline::class)->moveTo(
            $candidate, 'rejected', 'Offer declined by the candidate.', false
        );
        return back()->with('success', 'Offer rejected by candidate.');
    }
}
