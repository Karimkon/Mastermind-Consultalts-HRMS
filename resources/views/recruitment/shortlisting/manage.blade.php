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
        <p>Build a questionnaire (up to 15 questions). When candidates apply via the careers page they answer these questions, the system marks them, and you can auto-shortlist the <strong>top N</strong> with one click.</p>
        <p class="mt-1"><strong>Every assessment is worth 30 marks in total.</strong> Share those 30 marks out across your questions &mdash; the form will not save until they add up exactly, so that a score on one job means the same as a score on another.</p>
        <p class="mt-1 text-xs text-blue-600"><i class="fas fa-lightbulb mr-1"></i>Question types: <strong>Multiple Choice</strong> &mdash; give each answer its own marks, so &ldquo;5+ years&rdquo; can be worth more than &ldquo;1&ndash;2 years&rdquo;. <strong>Yes/No</strong> &mdash; the preferred answer earns the full marks. <strong>Scale 1&ndash;5</strong> &mdash; scored proportionally. <strong>Free Text</strong> &mdash; read by a person, carries no marks.</p>
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

        @if($errors->any())
        <div class="mb-5 bg-rose-50 border border-rose-200 rounded-xl px-5 py-4 text-sm text-rose-800">
            <p class="font-semibold mb-1"><i class="fas fa-circle-exclamation mr-1"></i>This questionnaire was not saved</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
        @endif

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
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Auto-select Top <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="top_n" x-model.number="topN"
                               min="1" max="1000" step="1" required
                               class="form-input w-28 text-center font-semibold">
                        <span class="text-sm text-slate-500">
                            {{ $job->candidates_count === 1 ? 'candidate' : 'candidates' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        @if($job->candidates_count > 0)
                            {{ $job->candidates_count }} {{ Str::plural('application', $job->candidates_count) }}
                            received so far.
                            <template x-if="topN > {{ $job->candidates_count }}">
                                <span class="text-amber-600">Everybody would be shortlisted.</span>
                            </template>
                        @else
                            Type any number. Nobody has applied yet.
                        @endif
                    </p>
                    {{-- Quick picks, because most shortlists are a round number. --}}
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach([5, 10, 20, 50, 100] as $n)
                        <button type="button" @click="topN = {{ $n }}"
                            class="px-2 py-0.5 rounded-full border text-xs transition"
                            :class="topN === {{ $n }}
                                ? 'bg-blue-600 border-blue-600 text-white'
                                : 'border-slate-200 text-slate-500 hover:border-blue-300'">{{ $n }}</button>
                        @endforeach
                    </div>
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
                        <span x-text="questions.length"></span> / 15 questions
                    </p>
                </div>
                <button type="button" @click="addQuestion()"
                    :disabled="questions.length >= 15"
                    class="btn-primary text-sm"
                    :class="questions.length >= 15 ? 'opacity-50 cursor-not-allowed' : ''">
                    <i class="fas fa-plus mr-1"></i> Add Question
                </button>
            </div>

            {{-- The marks budget. The recruiter is sharing out a fixed 30, so
                 the number that matters is how many are left, not how many
                 have been used. --}}
            <div class="mb-5 rounded-xl border px-4 py-3"
                 :class="balanced ? 'bg-emerald-50 border-emerald-200' : (allocated > totalMarks ? 'bg-rose-50 border-rose-200' : 'bg-amber-50 border-amber-200')">
                <div class="flex items-center justify-between gap-4 mb-2">
                    <div class="text-sm font-semibold"
                         :class="balanced ? 'text-emerald-800' : (allocated > totalMarks ? 'text-rose-800' : 'text-amber-800')">
                        <template x-if="balanced">
                            <span><i class="fas fa-circle-check mr-1"></i>All <span x-text="totalMarks"></span> marks allocated</span>
                        </template>
                        <template x-if="!balanced && allocated < totalMarks">
                            <span><i class="fas fa-circle-half-stroke mr-1"></i><span x-text="totalMarks - allocated"></span> of <span x-text="totalMarks"></span> marks still to give out</span>
                        </template>
                        <template x-if="allocated > totalMarks">
                            <span><i class="fas fa-triangle-exclamation mr-1"></i><span x-text="allocated - totalMarks"></span> marks over the <span x-text="totalMarks"></span> available</span>
                        </template>
                    </div>
                    <div class="text-sm font-bold tabular-nums"
                         :class="balanced ? 'text-emerald-700' : (allocated > totalMarks ? 'text-rose-700' : 'text-amber-700')">
                        <span x-text="allocated"></span> / <span x-text="totalMarks"></span>
                    </div>
                </div>
                <div class="h-2 rounded-full bg-white/70 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-200"
                         :class="balanced ? 'bg-emerald-500' : (allocated > totalMarks ? 'bg-rose-500' : 'bg-amber-500')"
                         :style="`width: ${Math.min(100, (allocated / totalMarks) * 100)}%`"></div>
                </div>
                <p class="text-xs mt-2"
                   :class="balanced ? 'text-emerald-700' : (allocated > totalMarks ? 'text-rose-700' : 'text-amber-700')">
                    <template x-if="textCount > 0">
                        <span><span x-text="textCount"></span> free-text <span x-text="textCount === 1 ? 'question is' : 'questions are'"></span> read by a person and carry no marks.</span>
                    </template>
                    <template x-if="textCount === 0">
                        <span>Every assessment in the recruitment process is marked out of <span x-text="totalMarks"></span>.</span>
                    </template>
                </p>
                <input type="hidden" name="total_marks" :value="totalMarks">
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
                                <div class="flex items-center gap-2" x-show="q.question_type !== 'text'">
                                    <label class="text-xs text-slate-500">Marks</label>
                                    <button type="button" @click="bump(q, -1)"
                                        class="w-6 h-6 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-100 text-xs leading-none">&minus;</button>
                                    <input type="number" min="0" :max="totalMarks" x-model.number="q.weight"
                                        @input="q.weight = clampWeight(q.weight)"
                                        class="w-14 text-center text-sm font-bold text-blue-700 border border-slate-300 rounded py-0.5">
                                    <button type="button" @click="bump(q, 1)"
                                        class="w-6 h-6 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-100 text-xs leading-none">+</button>
                                    <span class="text-xs text-slate-400">of <span x-text="totalMarks"></span></span>
                                </div>
                                <span x-show="q.question_type === 'text'"
                                      class="text-xs text-slate-400 italic">Not marked</span>
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
                        <input type="hidden" :name="`questions[${index}][weight]`" :value="q.question_type === 'text' ? 0 : q.weight">
                        <input type="hidden" :name="`questions[${index}][sort_order]`" :value="index">

                        {{-- Question type --}}
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Question Type</label>
                            <select :name="`questions[${index}][question_type]`" x-model="q.question_type"
                                @change="onTypeChange(q)" class="form-input w-full">
                                <option value="multiple_choice">Multiple Choice (each answer worth its own marks)</option>
                                <option value="yes_no">Yes / No (preferred answer earns full marks)</option>
                                <option value="scale">Scale 1–5 (scored proportionally)</option>
                                <option value="text">Free Text (read by a person, no marks)</option>
                            </select>
                        </div>

                        {{-- Multiple Choice options.
                             Each answer carries its own marks, so a question
                             like "how many years of experience?" can pay more
                             for 5+ years than for 1-2 - which is not a thing
                             a single correct answer can express. --}}
                        <div x-show="q.question_type === 'multiple_choice'" class="space-y-2">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-medium text-slate-600">
                                    Answers <span class="text-slate-400">(set what each one is worth)</span>
                                </label>
                                <button type="button" @click="spreadEvenly(q)"
                                    class="text-xs text-blue-600 hover:underline">
                                    <i class="fas fa-wand-magic-sparkles mr-1"></i>Grade evenly
                                </button>
                            </div>
                            <template x-for="(opt, oi) in q.options" :key="oi">
                                <div class="flex items-center gap-2">
                                    <input type="text"
                                        :name="`questions[${index}][options][${oi}][text]`"
                                        x-model="opt.text"
                                        :placeholder="`e.g. ${oi === 0 ? '1–2 years' : (oi === 1 ? '3–4 years' : 'Answer ' + (oi + 1))}`"
                                        class="form-input flex-1 text-sm">
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <input type="number" min="0" :max="q.weight" step="0.5"
                                            :name="`questions[${index}][options][${oi}][marks]`"
                                            x-model.number="opt.marks"
                                            @input="opt.marks = clampOption(q, opt.marks)"
                                            class="w-16 text-center text-sm border border-slate-300 rounded py-1"
                                            :class="(Number(opt.marks) || 0) > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-400'">
                                        <span class="text-xs text-slate-400">mk</span>
                                    </div>
                                    <button type="button" @click="removeOption(q, oi)"
                                        x-show="q.options.length > 2"
                                        class="text-red-400 hover:text-red-600 text-xs" title="Remove answer">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="addOption(q)"
                                x-show="q.options.length < 6"
                                class="text-xs text-blue-600 hover:underline mt-1">
                                <i class="fas fa-plus mr-1"></i>Add answer
                            </button>
                            <p class="text-xs mt-1"
                               :class="bestOption(q) === q.weight ? 'text-slate-400' : 'text-amber-600'">
                                <template x-if="bestOption(q) === q.weight">
                                    <span>Best answer earns the full <span x-text="q.weight"></span> marks.</span>
                                </template>
                                <template x-if="bestOption(q) !== q.weight">
                                    <span><i class="fas fa-triangle-exclamation mr-1"></i>No answer is worth the full <span x-text="q.weight"></span> marks &mdash; the best on offer is <span x-text="bestOption(q)"></span>.</span>
                                </template>
                            </p>
                            {{-- The highest-paying answer, so anything still
                                 reading the old correct/incorrect shape agrees. --}}
                            <input type="hidden" :name="`questions[${index}][correct_answer]`" :value="bestIndex(q)">
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
            <div class="flex items-center gap-3">
                <span x-show="!balanced" x-cloak class="text-xs text-amber-600">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    <template x-if="allocated < totalMarks">
                        <span><span x-text="totalMarks - allocated"></span> marks still to allocate</span>
                    </template>
                    <template x-if="allocated > totalMarks">
                        <span><span x-text="allocated - totalMarks"></span> marks over</span>
                    </template>
                </span>
                <button type="submit" class="btn-primary text-sm px-6"
                        :disabled="!balanced || questions.length === 0"
                        :class="(!balanced || questions.length === 0) ? 'opacity-50 cursor-not-allowed' : ''">
                    <i class="fas fa-save mr-1"></i>
                    {{ $criteria ? 'Update Criteria' : 'Save Criteria' }}
                </button>
            </div>
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
        totalMarks:  existingCriteria?.total_marks ?? 30,
        questions:   [],
        _keyCounter: 0,

        /* Marks handed out so far. Free text is read by a person and carries
           none, so it never counts towards the budget. */
        get allocated() {
            return this.questions
                .filter(q => q.question_type !== 'text')
                .reduce((sum, q) => sum + (parseInt(q.weight) || 0), 0);
        },

        get balanced() {
            return this.allocated === Number(this.totalMarks);
        },

        get textCount() {
            return this.questions.filter(q => q.question_type === 'text').length;
        },

        clampWeight(value) {
            const n = Math.round(Number(value) || 0);
            return Math.min(Number(this.totalMarks), Math.max(0, n));
        },

        bump(q, by) {
            q.weight = this.clampWeight((Number(q.weight) || 0) + by);
            this.capOptions(q);
        },

        /* An answer can never be worth more than its own question. */
        clampOption(q, value) {
            const n = Number(value) || 0;
            return Math.min(Number(q.weight) || 0, Math.max(0, n));
        },

        capOptions(q) {
            (q.options ?? []).forEach(o => { o.marks = this.clampOption(q, o.marks); });
        },

        bestOption(q) {
            return (q.options ?? []).reduce((best, o) => Math.max(best, Number(o.marks) || 0), 0);
        },

        bestIndex(q) {
            let best = 0, at = 0;
            (q.options ?? []).forEach((o, i) => {
                const m = Number(o.marks) || 0;
                if (m > best) { best = m; at = i; }
            });
            return at;
        },

        /* Share the question's marks across its answers in order, so
           "1-2 / 3-4 / 5+ years" becomes a rising scale in one click. */
        spreadEvenly(q) {
            const n = (q.options ?? []).length;
            if (!n) return;
            const weight = Number(q.weight) || 0;
            q.options.forEach((o, i) => {
                o.marks = Math.round((weight * (i + 1) / n) * 2) / 2;
            });
        },

        init() {
            if (existingCriteria?.questions?.length) {
                this.questions = existingCriteria.questions.map(q => ({
                    _key:          ++this._keyCounter,
                    question:      q.question,
                    question_type: q.question_type,
                    weight:        q.weight,
                    // A questionnaire written before answers carried their own
                    // marks stores only is_correct. Read it as all-or-nothing
                    // so editing an old one does not silently zero it.
                    options: (q.options ?? [{ text: '' }, { text: '' }, { text: '' }, { text: '' }]).map(o => ({
                        text:  o.text ?? '',
                        marks: o.marks ?? (o.is_correct ? q.weight : 0),
                    })),
                    correct_answer: q.correct_answer ?? (q.question_type === 'multiple_choice' ? 0 : 'yes'),
                }));
            }
        },

        addQuestion() {
            if (this.questions.length >= 15) return;

            // Start it on whatever is left of the budget, capped at 5, so
            // adding questions walks towards the total instead of away.
            const left = Math.max(0, Number(this.totalMarks) - this.allocated);

            this.questions.push({
                _key:           ++this._keyCounter,
                question:       '',
                question_type:  'multiple_choice',
                weight:         Math.min(5, left) || left,
                options:        [{ text: '', marks: 0 }, { text: '', marks: 0 },
                                 { text: '', marks: 0 }, { text: '', marks: 0 }],
                correct_answer: 0,
            });
        },

        removeQuestion(index) {
            this.questions.splice(index, 1);
        },

        onTypeChange(q) {
            if (q.question_type === 'multiple_choice') {
                q.options = q.options?.length
                    ? q.options
                    : [{ text: '', marks: 0 }, { text: '', marks: 0 },
                       { text: '', marks: 0 }, { text: '', marks: 0 }];
                this.capOptions(q);
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
            if (q.options.length < 6) q.options.push({ text: '', marks: 0 });
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
