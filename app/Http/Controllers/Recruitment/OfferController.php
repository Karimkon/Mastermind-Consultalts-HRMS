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
            'status'       => 'offer',
        ]);

        return back()->with('success', 'Offer extended to ' . $candidate->first_name . '.');
    }

    public function accept(Candidate $candidate)
    {
        $candidate->update(['status' => 'hired']);
        return back()->with('success', $candidate->first_name . ' has accepted the offer.');
    }

    public function reject(Candidate $candidate)
    {
        $candidate->update(['status' => 'rejected', 'offer_amount' => null]);
        return back()->with('success', 'Offer rejected by candidate.');
    }
}
