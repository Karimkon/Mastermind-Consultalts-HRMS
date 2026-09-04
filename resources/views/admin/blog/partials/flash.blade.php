@if(session('success'))
    <div class="mb-4 rounded-xl bg-green-50 border border-green-200 text-green-800 px-4 py-3 flex items-start gap-3">
        <i class="fas fa-circle-check mt-0.5"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 flex items-start gap-3">
        <i class="fas fa-circle-exclamation mt-0.5"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3">
        <p class="font-semibold mb-1 flex items-center gap-2"><i class="fas fa-circle-exclamation"></i>Please fix the following:</p>
        <ul class="list-disc list-inside text-sm space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
