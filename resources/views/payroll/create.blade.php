@extends("layouts.app")
@section("title","New Payroll Run")
@section("content")
<x-page-header title="Create Payroll Run"><a href="{{ route('payroll.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></x-page-header>
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('payroll.store') }}" class="space-y-4">@csrf
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Month *</label>
                <select name="month" class="form-select" required>
                    @for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ date("n")==$m?"selected":"" }}>{{ date("F",mktime(0,0,0,$m,1)) }}</option>@endfor
                </select>
            </div>
            <div><label class="form-label">Year *</label>
                <select name="year" class="form-select" required>
                    @for($y=date("Y");$y>=date("Y")-3;$y--)<option value="{{ $y }}" {{ date("Y")==$y?"selected":"" }}>{{ $y }}</option>@endfor
                </select>
            </div>
        </div>
        <div><label class="form-label">Notes</label><textarea name="notes" class="form-input" rows="3"></textarea></div>
        <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Create Payroll Run</button>
    </form>
</div>
@endsection
