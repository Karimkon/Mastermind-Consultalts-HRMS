{{--
    Roles and permissions for one person.

    Shared by create and edit so the two cannot drift apart, which is how a
    field ends up on one form and not the other.

    Roles are checkboxes rather than a dropdown because somebody here genuinely
    holds several jobs: the MD is super-admin, md and auditor at once, and
    Mbogga Julius holds four. A single-choice control could not express that,
    and worse, saving through it stripped whatever it could not show.

    Permissions are the exception layer. Almost nobody needs them — the roles
    carry the access — so they are collapsed by default and the panel says
    plainly that a role is the usual answer.
--}}

@props(['roles', 'permissions', 'heldRoles' => [], 'heldPermissions' => []])

<div class="md:col-span-2">
    <label class="form-label">Roles <span class="text-red-500">*</span></label>
    <p class="text-xs text-slate-500 mb-2">
        Somebody can hold more than one. Tick every job this person actually does.
    </p>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-3 border border-slate-200 rounded-lg bg-slate-50">
        @foreach ($roles as $r)
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="roles[]" value="{{ $r->name }}" class="rounded"
                    {{ in_array($r->name, old('roles', $heldRoles), true) ? 'checked' : '' }}>
                <span class="text-slate-700">{{ ucfirst(str_replace('-', ' ', $r->name)) }}</span>
            </label>
        @endforeach
    </div>

    @error('roles')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div class="md:col-span-2">
    <details class="border border-slate-200 rounded-lg" {{ $heldPermissions ? 'open' : '' }}>
        <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-700 select-none">
            Extra permissions
            @if ($heldPermissions)
                <span class="ml-1 text-xs text-blue-600">({{ count($heldPermissions) }} set)</span>
            @else
                <span class="ml-1 text-xs text-slate-400">— usually none needed</span>
            @endif
        </summary>

        <div class="px-3 pb-3">
            <p class="text-xs text-slate-500 mb-3">
                Granted to this person on top of their roles. Reach for a role first:
                a permission set here applies to one account only, so it is invisible
                to anybody wondering why two people with the same job can do different
                things.
            </p>

            @forelse ($permissions as $module => $group)
                <div class="mb-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">
                        {{ str_replace('-', ' ', $module) }}
                    </p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1">
                        @foreach ($group as $perm)
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="rounded"
                                    {{ in_array($perm->id, old('permissions', $heldPermissions)) ? 'checked' : '' }}>
                                <span class="text-slate-600 text-xs">{{ $perm->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No permissions are defined yet.</p>
            @endforelse
        </div>
    </details>
</div>
