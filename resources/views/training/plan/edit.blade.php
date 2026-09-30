@extends('layouts.app')
@section('title', $session->exists ? 'Edit training' : 'Add training to the plan')

@section('content')
<div class="mb-4">
    <a href="{{ $session->exists ? route('training.plan.show', $session) : route('training.plan.index') }}"
       class="text-sm text-blue-600 hover:underline">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

@include('admin.blog.partials.flash')

<form method="POST"
      action="{{ $session->exists ? route('training.plan.update', $session) : route('training.plan.store') }}"
      x-data="{
          pedagogic: {{ (float) old('cost_pedagogic', $session->cost_pedagogic ?? 0) }},
          logistic:  {{ (float) old('cost_logistic', $session->cost_logistic ?? 0) }},
          remun:     {{ (float) old('cost_remuneration', $session->cost_remuneration ?? 0) }},
          company:   {{ (float) old('cost_company', $session->cost_company ?? 0) }},
          get total() { return (+this.pedagogic) + (+this.logistic) + (+this.remun) + (+this.company); }
      }">
    @csrf
    @if($session->exists) @method('PUT') @endif

    <div class="card p-5 mb-4">
        <h2 class="font-semibold text-slate-800 mb-3">What is being taught</h2>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-6">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-input" required
                       value="{{ old('title', $session->title) }}" placeholder="e.g. Microsoft Excel Advanced">
            </div>

            <div class="sm:col-span-6">
                <label class="form-label">From the course catalogue <span class="text-slate-400 font-normal">(optional)</span></label>
                <select name="training_course_id" class="form-input select2">
                    <option value="">Not from the catalogue</option>
                    @foreach($courses as $c)
                        <option value="{{ $c->id }}" @selected(old('training_course_id', $session->training_course_id) == $c->id)>
                            {{ $c->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-4">
                <label class="form-label">Training area</label>
                <input type="text" name="category" class="form-input"
                       value="{{ old('category', $session->category) }}" placeholder="e.g. IT Skills">
            </div>

            <div class="sm:col-span-4">
                <label class="form-label">Delivery *</label>
                <select name="delivery" class="form-input" required>
                    @foreach(['classroom' => 'Classroom', 'e_learning' => 'E-learning',
                              'on_the_job' => 'On the job', 'external' => 'External course'] as $k => $v)
                        <option value="{{ $k }}" @selected(old('delivery', $session->delivery) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-4">
                <label class="form-label">Plan year *</label>
                <input type="number" name="plan_year" class="form-input" required min="2020" max="2100"
                       value="{{ old('plan_year', $session->plan_year ?: now()->year) }}">
            </div>
        </div>
    </div>

    <div class="card p-5 mb-4">
        <h2 class="font-semibold text-slate-800 mb-3">When, where and by whom</h2>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-3">
                <label class="form-label">Starts</label>
                <input type="date" name="starts_on" class="form-input"
                       value="{{ old('starts_on', $session->starts_on?->format('Y-m-d')) }}">
            </div>
            <div class="sm:col-span-3">
                <label class="form-label">Ends</label>
                <input type="date" name="ends_on" class="form-input"
                       value="{{ old('ends_on', $session->ends_on?->format('Y-m-d')) }}">
            </div>
            <div class="sm:col-span-3">
                <label class="form-label">Duration (days)</label>
                <input type="number" step="0.5" min="0" name="duration_days" class="form-input"
                       value="{{ old('duration_days', $session->duration_days) }}">
            </div>
            <div class="sm:col-span-3">
                <label class="form-label">Duration (hours)</label>
                <input type="number" step="0.5" min="0" name="duration_hours" class="form-input"
                       value="{{ old('duration_hours', $session->duration_hours) }}">
            </div>

            <div class="sm:col-span-4">
                <label class="form-label">Trainer</label>
                <input type="text" name="trainer" class="form-input"
                       value="{{ old('trainer', $session->trainer) }}" placeholder="Who delivers it">
            </div>
            <div class="sm:col-span-4">
                <label class="form-label">Training company / provider</label>
                <input type="text" name="provider" class="form-input"
                       value="{{ old('provider', $session->provider) }}">
            </div>
            <div class="sm:col-span-4">
                <label class="form-label">Venue</label>
                <input type="text" name="venue" class="form-input"
                       value="{{ old('venue', $session->venue) }}" placeholder="e.g. Head Office boardroom">
            </div>

            <div class="sm:col-span-4">
                <label class="form-label">Seat limit <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="number" min="1" name="max_participants" class="form-input"
                       value="{{ old('max_participants', $session->max_participants) }}">
            </div>
        </div>
    </div>

    <div class="card p-5 mb-4">
        <h2 class="font-semibold text-slate-800 mb-1">Cost</h2>
        <p class="text-xs text-slate-500 mb-3">
            Split the way finance codes it. The total is what goes to the CEO for sign-off.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            @foreach([
                ['cost_pedagogic',    'Pedagogic',        "The trainer's fee"],
                ['cost_logistic',     'Logistic',         'Travel, venue, materials'],
                ['cost_remuneration', 'Remuneration',     'Paid time off the job'],
                ['cost_company',      'Company logistic', 'Our own costs'],
            ] as [$field, $label, $hint])
            <div class="sm:col-span-3">
                <label class="form-label">{{ $label }}</label>
                <input type="number" step="0.01" min="0" name="{{ $field }}" class="form-input"
                       x-model.number="{{ ['cost_pedagogic'=>'pedagogic','cost_logistic'=>'logistic','cost_remuneration'=>'remun','cost_company'=>'company'][$field] }}"
                       value="{{ old($field, $session->$field ?? 0) }}">
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $hint }}</p>
            </div>
            @endforeach
        </div>

        <p class="mt-3 text-sm font-semibold text-slate-700">
            Total: <span x-text="total.toLocaleString()"></span>
        </p>
    </div>

    <div class="card p-5 mb-4">
        <h2 class="font-semibold text-slate-800 mb-3">Why it is needed</h2>
        <textarea name="justification" rows="3" class="form-input"
                  placeholder="The gap this closes — what people cannot do today that they will be able to do after.">{{ old('justification', $session->justification) }}</textarea>
        <p class="text-xs text-slate-500 mt-1">
            This is what HR and the CEO read when deciding.
        </p>

        <label class="form-label mt-3">Notes</label>
        <textarea name="notes" rows="2" class="form-input">{{ old('notes', $session->notes) }}</textarea>
    </div>

    @unless($session->exists)
    <div class="card p-5 mb-4">
        <h2 class="font-semibold text-slate-800 mb-1">Who attends</h2>
        <p class="text-xs text-slate-500 mb-3">
            You can add more people after saving.
        </p>
        <select name="employee_ids[]" class="form-input select2" multiple>
            @foreach($employees as $e)
                <option value="{{ $e->id }}">
                    {{ $e->full_name }} — {{ $e->department?->name ?? 'No department' }}
                </option>
            @endforeach
        </select>
    </div>
    @endunless

    <button class="btn-primary" data-loading-label="Saving…">
        <i class="fas fa-save mr-1"></i>
        {{ $session->exists ? 'Save changes' : 'Add to the plan' }}
    </button>
</form>
@endsection
