@extends('layouts.app')
@section('title', 'Candidate Detail')

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('client.recruitment.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Back</a>
    <div>
        <h1 class="text-2xl font-bold text-slate-800">{{ $candidate->name }}</h1>
        <p class="text-slate-500 text-sm">Applying for: <strong>{{ $candidate->jobPosting->title }}</strong></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left: Candidate info --}}
    <div class="space-y-4">
        <div class="card p-6">
            <div class="flex flex-col items-center text-center mb-4">
                <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-2xl font-bold mb-3">
                    {{ strtoupper(substr($candidate->name, 0, 1)) }}
                </div>
                <h3 class="font-semibold text-slate-800">{{ $candidate->name }}</h3>
                <p class="text-sm text-slate-400">{{ $candidate->email }}</p>
                @if($candidate->phone)<p class="text-sm text-slate-400">{{ $candidate->phone }}</p>@endif
            </div>

            @if($candidate->score !== null)
            <div class="p-3 bg-slate-50 rounded-xl text-center">
                <p class="text-xs text-slate-500 mb-1">AI Match Score</p>
                <div class="text-3xl font-bold {{ $candidate->score >= 70 ? 'text-green-600' : ($candidate->score >= 40 ? 'text-yellow-600' : 'text-red-500') }}">
                    {{ $candidate->score }}%
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 mt-2">
                    <div class="h-2 rounded-full {{ $candidate->score >= 70 ? 'bg-green-500' : ($candidate->score >= 40 ? 'bg-yellow-500' : 'bg-red-400') }}"
                         style="width:{{ $candidate->score }}%"></div>
                </div>
            </div>
            @endif

            @if($candidate->resume_path)
            <a href="{{ Storage::url($candidate->resume_path) }}" target="_blank" class="btn-secondary w-full mt-4 justify-center">
                <i class="fas fa-file-download mr-2"></i>Download Resume
            </a>
            @endif
        </div>

        {{-- Your decision card --}}
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Your Decision</h3>
            @if($candidate->client_shortlist_status === 'approved')
                <span class="badge-green block text-center py-2"><i class="fas fa-check mr-1"></i>Approved for Interview</span>
                @if($candidate->client_actioned_at)
                <p class="text-xs text-slate-400 text-center mt-1">{{ $candidate->client_actioned_at->format('d M Y H:i') }}</p>
                @endif
            @elseif($candidate->client_shortlist_status === 'rejected')
                <span class="badge-red block text-center py-2"><i class="fas fa-times mr-1"></i>Rejected</span>
                @if($candidate->client_shortlist_notes)
                <p class="text-xs text-slate-500 mt-2 bg-red-50 rounded-lg p-2">{{ $candidate->client_shortlist_notes }}</p>
                @endif
            @else
                <p class="text-sm text-slate-500 mb-3">No decision yet. Review the candidate and decide.</p>
                <form method="POST" action="{{ route('client.recruitment.approve', $candidate) }}" onsubmit="return confirm('Approve {{ $candidate->name }} for interview?')" class="mb-2">
                    @csrf
                    <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-check mr-2"></i>Approve for Interview</button>
                </form>
                <form method="POST" action="{{ route('client.recruitment.reject', $candidate) }}" id="reject-inline">
                    @csrf
                    <textarea name="notes" rows="2" class="form-textarea mb-2" placeholder="Reason for rejection (optional)"></textarea>
                    <button type="submit" onclick="return confirm('Reject this candidate?')" class="btn-danger w-full justify-center"><i class="fas fa-times mr-2"></i>Reject</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Right: Details --}}
    <div class="lg:col-span-2 space-y-4">
        {{-- AI Analysis --}}
        @if($candidate->score_breakdown)
        @php $ai = json_decode($candidate->score_breakdown, true); @endphp
        @if($ai && !isset($ai['error']))
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4"><i class="fas fa-robot mr-2 text-blue-500"></i>AI Analysis</h3>
            @if(isset($ai['summary']))
            <p class="text-sm text-slate-600 mb-4 bg-blue-50 rounded-lg p-3">{{ $ai['summary'] }}</p>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if(!empty($ai['strengths']))
                <div>
                    <p class="text-xs font-semibold text-green-700 uppercase mb-2">Strengths</p>
                    <ul class="space-y-1">
                        @foreach($ai['strengths'] as $s)
                        <li class="flex items-start gap-2 text-sm text-slate-700">
                            <i class="fas fa-check-circle text-green-500 mt-0.5 shrink-0"></i>{{ $s }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @if(!empty($ai['gaps']))
                <div>
                    <p class="text-xs font-semibold text-red-600 uppercase mb-2">Gaps</p>
                    <ul class="space-y-1">
                        @foreach($ai['gaps'] as $g)
                        <li class="flex items-start gap-2 text-sm text-slate-700">
                            <i class="fas fa-exclamation-circle text-red-400 mt-0.5 shrink-0"></i>{{ $g }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
            @if(isset($ai['recommendation']))
            <div class="mt-4 pt-4 border-t border-slate-100">
                <p class="text-xs text-slate-500">AI Recommendation: <strong class="{{ in_array($ai['recommendation'], ['Strongly Recommend','Recommend']) ? 'text-green-600' : 'text-red-500' }}">{{ $ai['recommendation'] }}</strong></p>
            </div>
            @endif
        </div>
        @endif
        @endif

        {{-- Notes --}}
        @if($candidate->notes)
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-3">Notes</h3>
            <p class="text-sm text-slate-600">{{ $candidate->notes }}</p>
        </div>
        @endif

        {{-- Interviews --}}
        @if($candidate->interviews->count())
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Interviews</h3>
            <div class="space-y-3">
                @foreach($candidate->interviews as $interview)
                <div class="flex items-start gap-4 p-3 bg-slate-50 rounded-xl">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                        <i class="fas fa-{{ $interview->type === 'phone' ? 'phone' : ($interview->type === 'video' ? 'video' : 'comments') }} text-blue-600 text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-800">{{ ucfirst(str_replace('_', ' ', $interview->type)) }} Interview</p>
                        <p class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($interview->scheduled_at)->format('d M Y, H:i') }}</p>
                        @if($interview->interviewer)
                        <p class="text-xs text-slate-400">with {{ $interview->interviewer->full_name }}</p>
                        @endif
                        @if($interview->rating)
                        <div class="flex items-center gap-1 mt-1">
                            @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star text-xs {{ $i <= $interview->rating ? 'text-yellow-400' : 'text-slate-200' }}"></i>
                            @endfor
                        </div>
                        @endif
                    </div>
                    <span class="badge-{{ $interview->status === 'completed' ? 'green' : ($interview->status === 'scheduled' ? 'blue' : 'red') }}">
                        {{ ucfirst($interview->status) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
