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
                            <option value="new" @selected($candidate->status==='new')>New</option>
                            <option value="screening" @selected($candidate->status==='screening')>Screening</option>
                            <option value="shortlisted" @selected($candidate->status==='shortlisted')>Shortlisted</option>
                            <option value="interview" @selected($candidate->status==='interview')>Interview</option>
                            <option value="offer" @selected($candidate->status==='offer')>Offer</option>
                            <option value="hired" @selected($candidate->status==='hired')>Hired</option>
                            <option value="rejected" @selected($candidate->status==='rejected')>Rejected</option>
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

        {{-- Client Shortlist Status --}}
        @if($candidate->client_shortlist_status)
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Client Decision</h3>
            @if($candidate->client_shortlist_status === 'approved')
                <span class="badge-green block text-center py-2"><i class="fas fa-check mr-1"></i>Approved by Client</span>
            @elseif($candidate->client_shortlist_status === 'rejected')
                <span class="badge-red block text-center py-2"><i class="fas fa-times mr-1"></i>Rejected by Client</span>
                @if($candidate->client_shortlist_notes)
                <p class="text-xs text-slate-500 mt-2 bg-red-50 rounded-lg p-2">{{ $candidate->client_shortlist_notes }}</p>
                @endif
            @endif
            @if($candidate->client_actioned_at)
            <p class="text-xs text-slate-400 text-center mt-1">{{ $candidate->client_actioned_at->format('d M Y H:i') }}</p>
            @endif
        </div>
        @endif

        {{-- AI Analysis Panel --}}
        <div class="card p-5" x-data="{ loading: false, result: null, error: null, questions: null, qLoading: false }">
            <h3 class="font-semibold text-slate-700 mb-3"><i class="fas fa-robot text-blue-500 mr-2"></i>AI Analysis</h3>

            @if($candidate->score_breakdown)
            @php $ai = json_decode($candidate->score_breakdown, true); @endphp
            @if($ai && !isset($ai['error']))
            <div class="space-y-2 mb-3">
                @if(isset($ai['summary']))<p class="text-xs text-slate-600 bg-blue-50 rounded-lg p-2">{{ $ai['summary'] }}</p>@endif
                @if(!empty($ai['strengths']))<div>
                    <p class="text-xs font-semibold text-green-700 mb-1">Strengths</p>
                    <ul class="space-y-0.5">
                        @foreach($ai['strengths'] as $s)<li class="text-xs text-slate-600 flex items-start gap-1"><i class="fas fa-check-circle text-green-500 mt-0.5 shrink-0"></i>{{ $s }}</li>@endforeach
                    </ul>
                </div>@endif
                @if(!empty($ai['gaps']))<div>
                    <p class="text-xs font-semibold text-red-600 mb-1">Gaps</p>
                    <ul class="space-y-0.5">
                        @foreach($ai['gaps'] as $g)<li class="text-xs text-slate-600 flex items-start gap-1"><i class="fas fa-exclamation-circle text-red-400 mt-0.5 shrink-0"></i>{{ $g }}</li>@endforeach
                    </ul>
                </div>@endif
                @if(isset($ai['recommendation']))<p class="text-xs text-slate-500 pt-1">Recommendation: <strong class="{{ in_array($ai['recommendation'],['Strongly Recommend','Recommend']) ? 'text-green-600' : 'text-red-500' }}">{{ $ai['recommendation'] }}</strong></p>@endif
            </div>
            @endif
            @endif

            @if($candidate->resume_path)
            <button @click="loading=true; error=null; fetch('{{ route('recruitment.ai.score', $candidate) }}',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('[name=csrf-token]').content,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{loading=false; if(d.error)error=d.error; else{result=d; location.reload();}}).catch(e=>{loading=false;error='Request failed';})"
                :disabled="loading"
                class="btn-secondary w-full justify-center text-sm mt-2">
                <i class="fas fa-sync-alt mr-2" :class="loading ? 'fa-spin' : ''"></i>
                <span x-text="loading ? 'Analysing...' : 'Run AI Resume Analysis'"></span>
            </button>
            <p x-show="error" x-text="error" class="text-xs text-red-500 mt-2"></p>
            @else
            <p class="text-xs text-slate-400">Upload a resume to enable AI analysis.</p>
            @endif

            {{-- Interview Questions --}}
            <div class="mt-3 pt-3 border-t border-slate-100">
                <button @click="qLoading=true; fetch('{{ route('recruitment.ai.questions', $candidate) }}',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('[name=csrf-token]').content,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{qLoading=false; if(d.error)error=d.error; else questions=d;})"
                    :disabled="qLoading"
                    class="btn-secondary w-full justify-center text-sm">
                    <i class="fas fa-question-circle mr-2" :class="qLoading ? 'fa-spin' : ''"></i>
                    <span x-text="qLoading ? 'Generating...' : 'Generate Interview Questions'"></span>
                </button>
                <div x-show="questions" class="mt-3 text-xs space-y-3">
                    <template x-if="questions">
                        <div>
                            <template x-for="[cat,qs] in Object.entries(questions || {})" :key="cat">
                                <div class="mb-2">
                                    <p class="font-semibold text-slate-600 uppercase mb-1" x-text="cat.replace('_',' ')"></p>
                                    <ul class="space-y-1">
                                        <template x-for="q in qs" :key="q">
                                            <li class="text-slate-600 flex items-start gap-1"><i class="fas fa-angle-right text-blue-400 mt-0.5 shrink-0"></i><span x-text="q"></span></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection