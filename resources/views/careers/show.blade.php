<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $job->title }} — Mastermind Careers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>body{font-family:"Inter",sans-serif;}</style>
</head>
<body class="bg-slate-50">

<header class="bg-white border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="{{ route('careers.index') }}" class="flex items-center gap-2 text-slate-600 hover:text-slate-800 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Jobs
        </a>
        <a href="{{ route('login') }}" class="text-sm text-blue-600 font-medium hover:underline">Employee Login →</a>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-10">

    {{-- Success alert --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-5 py-4 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle text-green-500 text-xl"></i>
        <div>
            <p class="font-semibold">Application Submitted!</p>
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    </div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-4 mb-6">
        <p class="font-semibold mb-1">Please fix the following errors:</p>
        <ul class="list-disc list-inside text-sm space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Job Detail --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 p-8 mb-6">
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="text-sm font-medium bg-blue-100 text-blue-700 px-3 py-1 rounded-full">{{ $job->department?->name ?? 'General' }}</span>
                    <span class="text-sm font-medium bg-slate-100 text-slate-600 px-3 py-1 rounded-full">{{ ucfirst(str_replace('_',' ',$job->employment_type)) }}</span>
                    @if($job->location)<span class="text-sm text-slate-500 flex items-center gap-1"><i class="fas fa-map-marker-alt"></i>{{ $job->location }}</span>@endif
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 mb-2">{{ $job->title }}</h1>
                @if($job->reference_number)<p class="text-sm text-slate-400 mb-4">Ref: {{ $job->reference_number }}</p>@endif

                @if($job->salary_min && $job->salary_max)
                <div class="inline-flex items-center gap-2 bg-green-50 text-green-700 px-4 py-2 rounded-xl text-sm font-semibold mb-6">
                    <i class="fas fa-money-bill-wave"></i>
                    R{{ number_format($job->salary_min) }} – R{{ number_format($job->salary_max) }} per month
                </div>
                @endif

                <div class="prose prose-slate text-slate-700 text-sm leading-relaxed">
                    <h3 class="font-bold text-base text-slate-800 mb-2">About the Role</h3>
                    <div>{!! nl2br(e($job->description)) !!}</div>

                    @if($job->requirements)
                    <h3 class="font-bold text-base text-slate-800 mt-6 mb-2">Requirements</h3>
                    <div>{!! nl2br(e($job->requirements)) !!}</div>
                    @endif

                    @if($job->benefits)
                    <h3 class="font-bold text-base text-slate-800 mt-6 mb-2">What We Offer</h3>
                    <div>{!! nl2br(e($job->benefits)) !!}</div>
                    @endif
                </div>
            </div>

            {{-- Related Jobs --}}
            @if($relatedJobs->count())
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4">Similar Positions</h3>
                @foreach($relatedJobs as $related)
                <a href="{{ route('careers.show', $related) }}" class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0 hover:text-blue-600 transition">
                    <div>
                        <p class="text-sm font-medium">{{ $related->title }}</p>
                        <p class="text-xs text-slate-400">{{ ucfirst(str_replace('_',' ',$related->employment_type)) }}</p>
                    </div>
                    <i class="fas fa-chevron-right text-slate-300"></i>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Application Form --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sticky top-20">
                <h2 class="text-xl font-bold text-slate-800 mb-1">Apply Now</h2>
                @if($job->deadline && !$job->deadline->isPast())
                <p class="text-xs text-orange-500 mb-4"><i class="fas fa-clock mr-1"></i>Closes {{ $job->deadline->format('d M Y') }}</p>
                @endif
                <p class="text-xs text-slate-500 mb-5">{{ $job->vacancies }} position{{ $job->vacancies > 1 ? 's' : '' }} available</p>

                <form method="POST" action="{{ route('careers.apply', $job) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" placeholder="John Smith">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" placeholder="john@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                        <input type="tel" name="phone" value="{{ old('phone') }}"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" placeholder="+27 xx xxx xxxx">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Cover Letter</label>
                        <textarea name="cover_letter" rows="4"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 resize-none"
                            placeholder="Tell us why you're a great fit...">{{ old('cover_letter') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">CV / Resume <span class="text-red-500">*</span></label>
                        <input type="file" name="cv" accept=".pdf,.doc,.docx" required
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-600 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700">
                        <p class="text-xs text-slate-400 mt-1">PDF, DOC or DOCX — max 5MB</p>
                    </div>
                    {{-- Screening Questions (if criteria active) --}}
                    @if(!empty($screeningCriteria) && $screeningCriteria->questions->isNotEmpty())
                    <div class="border-t border-slate-200 pt-4">
                        <h3 class="font-semibold text-slate-800 text-sm mb-1">Screening Questions</h3>
                        <p class="text-xs text-slate-400 mb-4">Please answer all questions below. Your responses help us identify the best fit.</p>
                        <div class="space-y-5">
                            @foreach($screeningCriteria->questions as $q)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    {{ $loop->iteration }}. {{ $q->question }}
                                    <span class="text-xs text-slate-400 font-normal ml-1">(weight: {{ $q->weight }})</span>
                                </label>

                                @if($q->question_type === 'multiple_choice' && !empty($q->options))
                                <div class="space-y-1.5">
                                    @foreach($q->options as $oi => $opt)
                                    <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition text-sm">
                                        <input type="radio" name="screening[{{ $q->id }}]" value="{{ $oi }}"
                                            class="accent-blue-600" required>
                                        {{ $opt['text'] }}
                                    </label>
                                    @endforeach
                                </div>

                                @elseif($q->question_type === 'yes_no')
                                <div class="flex gap-3">
                                    <label class="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-green-50 hover:border-green-300 transition text-sm flex-1 justify-center">
                                        <input type="radio" name="screening[{{ $q->id }}]" value="yes"
                                            class="accent-green-600" required>
                                        Yes
                                    </label>
                                    <label class="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-red-50 hover:border-red-300 transition text-sm flex-1 justify-center">
                                        <input type="radio" name="screening[{{ $q->id }}]" value="no"
                                            class="accent-red-500" required>
                                        No
                                    </label>
                                </div>

                                @elseif($q->question_type === 'scale')
                                <div class="flex gap-2">
                                    @foreach([1,2,3,4,5] as $val)
                                    <label class="flex-1 text-center p-2 rounded-lg border border-slate-200 cursor-pointer hover:bg-amber-50 hover:border-amber-300 transition text-sm font-medium">
                                        <input type="radio" name="screening[{{ $q->id }}]" value="{{ $val }}"
                                            class="sr-only" required>
                                        <span class="block">{{ $val }}</span>
                                        <span class="text-xs text-slate-400">{{ $val === 1 ? 'Low' : ($val === 5 ? 'High' : '') }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                <script>
                                document.querySelectorAll('input[name="screening[{{ $q->id }}]"]').forEach(function(radio) {
                                    radio.addEventListener('change', function() {
                                        document.querySelectorAll('input[name="screening[{{ $q->id }}]"]').forEach(function(r) {
                                            r.closest('label').classList.remove('bg-amber-100','border-amber-400');
                                        });
                                        if (this.checked) {
                                            this.closest('label').classList.add('bg-amber-100','border-amber-400');
                                        }
                                    });
                                });
                                </script>

                                @elseif($q->question_type === 'text')
                                <textarea name="screening[{{ $q->id }}]" rows="3"
                                    class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 resize-none"
                                    placeholder="Your answer..."></textarea>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm transition">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Application
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<footer class="bg-white border-t border-slate-200 py-6 text-center text-sm text-slate-400 mt-10">
    &copy; {{ date('Y') }} Mastermind Consultants. All rights reserved.
</footer>
</body>
</html>