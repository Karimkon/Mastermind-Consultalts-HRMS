{{--
    Posts this person holds, and has held.

    Separate from Roles & Permissions, which is organised by login. An admin
    replacing a departed HR is thinking about a person, not an account, and
    came here to find them. Both screens write through the same service, so the
    archive is complete whichever route is taken.

    Ending a role never deletes the record of it. That is the whole point: the
    question "how many HRs have we had" is unanswerable from the permission
    tables, because removing a role there deletes the row.
--}}

@props(['employee', 'roleHistory', 'assignableRoles'])

@php($canManage = auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'md']))

<div class="card p-5 mb-4">
    <h3 class="font-semibold text-slate-800 mb-1 flex items-center gap-2">
        <i class="fas fa-user-shield text-blue-600"></i> Roles held
    </h3>

    @if (! $employee->user)
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2">
            <i class="fas fa-circle-info mr-1"></i>
            {{ $employee->full_name }} has no login yet. A role is held by a login, so
            there is nothing to appoint them to until one is created.
        </p>
    @else
        @php($current = $roleHistory->where('ended_at', null))
        @php($former = $roleHistory->whereNotNull('ended_at'))

        <p class="text-xs text-slate-500 mb-3">
            Signed in as <span class="font-mono">{{ $employee->user->email }}</span>
        </p>

        {{-- ─────────────────────────────── now ─────────────────────────────── --}}
        @if ($current->isEmpty())
            <p class="text-sm text-slate-400 mb-3">Holds no role at present.</p>
        @else
            <div class="space-y-2 mb-4">
                @foreach ($current as $a)
                    <div class="flex items-center justify-between gap-3 border border-slate-200 rounded-lg px-3 py-2">
                        <div>
                            <span class="font-medium text-slate-800">
                                {{ ucfirst(str_replace('-', ' ', $a->role_name)) }}
                            </span>
                            <span class="ml-2 text-xs text-slate-500">{{ $a->periodLabel() }}</span>
                            @if ($a->assignedBy)
                                <span class="ml-1 text-xs text-slate-400">· by {{ $a->assignedBy->name }}</span>
                            @endif
                        </div>

                        @if ($canManage)
                            <form method="POST" action="{{ route('employees.roles.revoke', $employee) }}"
                                  onsubmit="return confirm('End this role? The record of it is kept.')">
                                @csrf @method('DELETE')
                                <input type="hidden" name="role" value="{{ $a->role_name }}">
                                <button class="text-xs text-red-600 hover:underline">End</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ────────────────────────────── appoint ────────────────────────────── --}}
        @if ($canManage)
            <form method="POST" action="{{ route('employees.roles.assign', $employee) }}"
                  class="flex flex-wrap gap-2 items-end border-t border-slate-100 pt-3">
                @csrf
                <div class="flex-1 min-w-[11rem]">
                    <label class="form-label">Appoint to</label>
                    <select name="role" class="form-input w-full" required>
                        <option value="">— Select a role —</option>
                        @foreach ($assignableRoles as $r)
                            @continue($current->contains('role_name', $r->name))
                            <option value="{{ $r->name }}">
                                {{ ucfirst(str_replace('-', ' ', $r->name)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <label class="form-label">Why <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" name="reason" class="form-input w-full"
                           placeholder="e.g. taking over from Ian Kirabo" maxlength="500">
                </div>
                <button class="btn-primary">Appoint</button>
            </form>
        @endif

        {{-- ────────────────────────────── the archive ────────────────────────────── --}}
        @if ($former->isNotEmpty())
            <div class="mt-4 pt-3 border-t border-slate-100">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">
                    Previously held
                </p>
                <div class="space-y-1">
                    @foreach ($former as $a)
                        <div class="flex items-center justify-between text-sm text-slate-600">
                            <span>{{ ucfirst(str_replace('-', ' ', $a->role_name)) }}</span>
                            <span class="text-xs text-slate-400">
                                {{ $a->periodLabel() }}
                                @if ($a->endedBy) · ended by {{ $a->endedBy->name }} @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
