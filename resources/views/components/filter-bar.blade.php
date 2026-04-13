@props(["action" => ""])
<div class="card p-4 mb-4">
    <form method="GET" action="{{ $action }}" id="filter-form" class="flex flex-wrap gap-3 items-end">
        {{ $slot }}
        <button type="submit" class="btn-primary">
            <i class="fas fa-search"></i> Filter
        </button>
        <a href="{{ $action }}" class="btn-secondary">
            <i class="fas fa-undo"></i> Clear
        </a>
    </form>
</div>
