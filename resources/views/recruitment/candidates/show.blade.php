@extends('layouts.app')
@section('title', $candidate->name)
@section('content')
<x-page-header :title="$candidate->name" subtitle="Candidate Profile">
    <a href="{{ route('recruitment.candidates.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Candidate Information</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-slate-500">Email</span><p class="font-medium">{{ $candidate->email }}</p></div>
                <div><span class="text-slate-500">Phone</span><p class="font-medium">{{ $candidate->phone ?? '—' }}</p></div>
                <div><span class="text-slate-500">Applied For</span><p class="font-medium">{{ $candidate->jobPosting->title ?? '—' }}</p></div>
                <div><span class="text-slate-500">Applied On</span><p class="font-medium">{{ $candidate->created_at->format('M d, Y') }}</p></div>
                <div><span class="text-slate-500">AI Score</span>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="h-2 w-32 bg-slate-100 rounded-full"><div class="h-2 rounded-full {{ $candidate->score >= 70 ? 'bg-green-500' : ($candidate->score >= 40 ? 'bg-yellow-500' : 'bg-red-400') }}" style="width:{{ $candidate->score }}%"></div></div>
                        <span class="font-bold">{{ $candidate->score }}%</span>
                    </div>
                </div>
                <div><span class="text-slate-500">Status</span>
                    <form method="POST" action="{{ route('recruitment.candidates.update', $candidate) }}" class="inline-flex items-center gap-2 mt-1">
                        @csrf @method('PUT')
                        <select name="status" class="form-input text-xs py-1" onchange="this.form.submit()">
                            @foreach(['applied','screening','interview','offered','hired','rejected'] as $s)<option value="{{ $s }}" @selected($candidate->status===$s)>{{ ucfirst($s) }}</option>@endforeach
                        </select>
                    </form>
                </div>
            </div>
            @if($candidate->resume_path)
            <div class="mt-4 pt-4 border-t border-slate-100">
                <a href="{{ Storage::url($candidate->resume_path) }}" target="_blank" class="btn-secondary text-sm"><i class="fas fa-file-pdf mr-1 text-red-500"></i> View Resume</a>
            </div>
            @endif
        </div>
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Interviews ({{ $candidate->interviews->count() }})</h3>
            @forelse($candidate->interviews as $iv)
            <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg mb-2">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-video text-blue-600 text-xs"></i></div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-800">{{ ucfirst($iv->type) }} Interview — <span class="text-slate-500">{{ \Carbon\Carbon::parse($iv->scheduled_at)->format('M d, Y H:i') }}</span></p>
                    <p class="text-xs text-slate-500">Interviewer: {{ $iv->interviewer->full_name ?? '—' }} | Rating: {{ $iv->rating ?? 'Pending' }}</p>
                    @if($iv->feedback)<p class="text-xs text-slate-600 mt-1">{{ $iv->feedback }}</p>@endif
                </div>
                <span class="badge badge-blue text-xs">{{ ucfirst($iv->status) }}</span>
            </div>
            @empty
            <p class="text-sm text-slate-400">No interviews scheduled.</p>
            @endforelse
            <a href="{{ route('recruitment.interviews.create', ['candidate_id' => $candidate->id]) }}" class="btn-secondary mt-3 text-sm"><i class="fas fa-plus mr-1"></i> Schedule Interview</a>
        </div>
    </div>
    <div class="space-y-4">
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Notes</h3>
            <p class="text-sm text-slate-500">{{ $candidate->notes ?? 'No notes added.' }}</p>
        </div>
    </div>
</div>
@endsection