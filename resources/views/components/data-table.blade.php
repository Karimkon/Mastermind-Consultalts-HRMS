@props(["id" => "data-table"])
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full" id="{{ $id }}">
            {{ $slot }}
        </table>
    </div>
</div>
