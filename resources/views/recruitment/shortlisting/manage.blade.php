@extends('layouts.app')
@section('title', 'Shortlisting Criteria — ' . $job->title)
@section('content')

<x-page-header :title="'Shortlisting Criteria'" :subtitle="$job->title">
    <a href="{{ route('recruitment.jobs.show', $job) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Job</a>
    @if($criteria)
    <a href="{{ route('recruitment.shortlisting.results', $job) }}" class="btn-primary"><i class="fas fa-trophy mr-1"></i> View Rankings</a>
    @endif
</x-page-header>

@if(session('success'))
<div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
    <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
    <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
</div>
@endif
@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
    <p class="font-semibold mb-1">Please fix the following:</p>
    <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{-- Info banner --}}
<div class="mb-5 bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 text-sm text-blue-800 flex items-start gap-3">
    <i class="fas fa-info-circle text-blue-500 mt-0.5 text-base"></i>
    <div>
        <p class="font-semibold mb-1">How shortlisting works</p>
        <p>Build a questionnaire (up to 15 questions) with weighted scores. When candidates apply via the careers page they answer these questions. The system calculates a <strong>weighted score</strong> for each candidate and ranks them. You can then auto-shortlist the <strong>top N</strong> with one click.</p>
        <p class="mt-1 text-xs text-blue-600"><i class="fas fa-lightbulb mr-1"></i>Question types: <strong>Multiple Choice</strong> (exact correct answer), <strong>Yes/No</strong> (preferred answer), <strong>Scale 1–5</strong> (scored proportionally), <strong>Free Text</strong> (manual review, not auto-scored).</p>
    </div>
</div>

<div
    x-data="shortlistBuilder({{ $criteria ? json_encode($criteria->load('questions')) : 'null' }})"
    x-init="init()"
>
    <form
        method="POST"
        action="{{ $criteria
            ? route('recruitment.shortlisting.update', [$job, $criteria])
            : route('recruitment.shortlisting.store', $job) }}"
    >
        @csrf
        @if($criteria) @method('PUT') @endif

        {{-- Header card --}}
        <div class="card p-6 mb-5">
            <h3 class="font-semibold text-slate-700 mb-4">Criteria Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Criteria Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" x-model="title" required
                        class="form-input w-full" placeholder="e.g. Software Engineer Screening Questions">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Auto-select Top <span class="text-red-500">*</span></label>
                    <select name="top_n" x-model="topN" class="form-input w-full">
                        @foreach([5,10,15,20,25,30] as $n)
                        <option value="{{ $n }}">{{ $n }} candidates</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                    <textarea name="description" x-model="description" rows="2"
                        class="form-input w-full resize-none" placeholder="Brief description of what this screening evaluates..."></textarea>
                </div>
                <div class="md:col-span-3 flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" x-model="isActive" class="rounded border-slate-300 text-blue-600">
                    <label for="is_active" class="text-sm text-slate-700">Active (candidates will see these questions on the careers page)</label>
                </div>
            </div>
        </div>

        {{-- Questions --}}
        <div class="card p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-slate-700">Screening Questions</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <span x-text="questions.length"></span> / 15 questions &nbsp;|&nbsp;
                        Max total weight: <span x-text="maxWeight" class="font-semibold text-blue-600"></span>
                    </p>
                </div>
                <button type="button" @click="addQuestion()"
                    :disabled="questions.length >= 15"
                    class="btn-primary text-sm"
                    :class="questions.length >= 15 ? 'opacity-50 cursor-not-allowed' : ''">
                    <i class="fas fa-plus mr-1"></i> Add Question
                </button>
            </div>

            <div x-show="questions.length === 0" class="text-center py-10 text-slate-400">
                <i class="fas fa-question-circle text-4xl mb-3 opacity-30"></i>
                <p>No questions yet. Click "Add Question" to start building your screening.</p>
            </div>

            <div class="space-y-4">
                <template x-for="(q, index) in questions" :key="q._key">
                    <div class="border border-slate-200 rounded-xl p-5 bg-slate-50 relative">
                        {{-- Question number + remove --}}
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">
                                Question <span x-text="index + 1"></span>
                            </span>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-slate-500">Weight (1–10)</label>
                                    <input type="range" min="1" max="10" x-model.number="q.weight"
                                        class="w-24 accent-blue-600">
                                    <span class="text-sm font-bold text-blue-700 w-4" x-text="q.weight"></span>
                                </div>
                                <button type="button" @click="removeQuestion(index)"
                                    class="text-red-400 hover:text-red-600 text-sm ml-2" title="Remove question">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Question text --}}
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Question <span class="text-red-500">*</span></label>
                            <input type="text" :name="`questions[${index}][question]`" x-model="q.question"
                                required class="form-input w-full" placeholder="Enter your screening question...">
                        </div>

                        {{-- Hidden weight/order inputs --}}
                        <input type="hidden" :name="`questions[${index}][weight]`" :value="q.weight">
                        <input type="hidden" :name="`questions[${index}][sort_order]`" :value="index">

                        {{-- Question type --}}
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Question Type</label>
                            <select :name="`questions[${index}][question_type]`" x-model="q.question_type"
                                @change="onTypeChange(q)" class="form-input w-full">
                                <option value="multiple_choice">Multiple Choice (one correct answer)</option>
                                <option value="yes_no">Yes / No</option>
                                <option value="scale">Scale 1–5 (rated by value)</option>
                                <option value="text">Free Text (manual review)</option>
                            </select>
                        </div>

                        {{-- Multiple Choice options --}}
                        <div x-show="q.question_type === 'multiple_choice'" class="space-y-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Options <span class="text-slate-400">(mark the correct one)</span></label>
                            <template x-for="(opt, oi) in q.options" :key="oi">
                                <div class="flex items-center gap-2">
                                    <input type="radio"
                                        :name="`questions[${index}][correct_answer]`"
                                        :value="oi"
                                        :checked="q.correct_answer == oi"
                                        @change="q.correct_answer = oi"
                                        class="accent-green-600 flex-shrink-0" title="Mark as correct">
                                    <input type="text"
                                        :name="`questions[${index}][options][${oi}][text]`"
                                        x-model="opt.text"
                                        :placeholder="`Option ${oi + 1}`"
                                        class="form-input flex-1 text-sm">
                                    <button type="button" @click="removeOption(q, oi)"
                                        x-show="q.options.length > 2"
                                        class="text-red-400 hover:text-red-600 text-xs" title="Remove option">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="addOption(q)"
                                x-show="q.options.length < 6"
                                class="text-xs text-blue-600 hover:underline mt-1">
                                <i class="fas fa-plus mr-1"></i>Add option
                            </button>
                            {{-- Hidden correct_answer fallback if nothing selected --}}
                            <input type="hidden" :name="`questions[${index}][correct_answer]`" :value="q.correct_answer ?? 0">
                        </div>

                        {{-- Yes / No --}}
                        <div x-show="q.question_type === 'yes_no'" class="flex items-center gap-6 mt-1">
                            <span class="text-xs text-slate-600 font-medium">Preferred answer:</span>
                            <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" :name="`questions[${index}][correct_answer]`"
                                    value="yes" x-model="q.correct_answer" class="accent-green-600">
                                Yes
                            </label>
                            <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" :name="`questions[${index}][correct_answer]`"
                                    value="no" x-model="q.correct_answer" class="accent-red-500">
                                No
                            </label>
                        </div>

                        {{-- Scale info --}}
                        <div x-show="q.question_type === 'scale'" class="mt-1 text-xs text-slate-500 bg-amber-50 rounded-lg px-3 py-2 border border-amber-100">
                            <i class="fas fa-star text-amber-400 mr-1"></i>
                            Candidates select 1–5. Score = (answer ÷ 5) × weight. A "5" earns the full weight.
                            <input type="hidden" :name="`questions[${index}][correct_answer]`" value="">
                        </div>

                        {{-- Text info --}}
                        <div x-show="q.question_type === 'text'" class="mt-1 text-xs text-slate-500 bg-slate-100 rounded-lg px-3 py-2">
                            <i class="fas fa-pen mr-1"></i>
                            Free-text answers are not auto-scored. Review them manually in the candidate profile.
                            <input type="hidden" :name="`questions[${index}][correct_answer]`" value="">
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Save --}}
        <div class="flex items-center justify-between">
            @if($criteria)
            <form method="POST" action="{{ route('recruitment.shortlisting.destroy', [$job, $criteria]) }}"
                onsubmit="return confirm('Delete this criteria? All candidate responses will also be deleted.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger text-sm"><i class="fas fa-trash mr-1"></i> Delete Criteria</button>
            </form>
            @else
            <div></div>
            @endif
            <button type="submit" class="btn-primary text-sm px-6">
                <i class="fas fa-save mr-1"></i>
                {{ $criteria ? 'Update Criteria' : 'Save Criteria' }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function shortlistBuilder(existingCriteria) {
    return {
        title:       existingCriteria?.title       ?? '',
        description: existingCriteria?.description ?? '',
        topN:        existingCriteria?.top_n       ?? 10,
        isActive:    existingCriteria?.is_active    ?? true,
        questions:   [],
        _keyCounter: 0,

        get maxWeight() {
            return this.questions
                .filter(q => q.question_type !== 'text')
                .reduce((sum, q) => sum + (parseInt(q.weight) || 0), 0);
        },

        init() {
            if (existingCriteria?.questions?.length) {
                this.questions = existingCriteria.questions.map(q => ({
                    _key:          ++this._keyCounter,
                    question:      q.question,
                    question_type: q.question_type,
                    weight:        q.weight,
                    options:       q.options ?? [{ text: '' }, { text: '' }, { text: '' }, { text: '' }],
                    correct_answer: q.correct_answer ?? (q.question_type === 'multiple_choice' ? 0 : 'yes'),
                }));
            }
        },

        addQuestion() {
            if (this.questions.length >= 15) return;
            this.questions.push({
                _key:           ++this._keyCounter,
                question:       '',
                question_type:  'multiple_choice',
                weight:         5,
                options:        [{ text: '' }, { text: '' }, { text: '' }, { text: '' }],
                correct_answer: 0,
            });
        },

        removeQuestion(index) {
            this.questions.splice(index, 1);
        },

        onTypeChange(q) {
            if (q.question_type === 'multiple_choice') {
                q.options        = q.options?.length ? q.options : [{ text: '' }, { text: '' }, { text: '' }, { text: '' }];
                q.correct_answer = 0;
            } else if (q.question_type === 'yes_no') {
                q.correct_answer = 'yes';
                q.options        = [];
            } else {
                q.correct_answer = '';
                q.options        = [];
            }
        },

        addOption(q) {
            if (q.options.length < 6) q.options.push({ text: '' });
        },

        removeOption(q, index) {
            if (q.options.length > 2) {
                q.options.splice(index, 1);
                if (q.correct_answer >= q.options.length) q.correct_answer = 0;
            }
        },
    };
}
</script>
@endpush

@endsection
