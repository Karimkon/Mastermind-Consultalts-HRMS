{{--
    Appointing, handing over and standing down an account manager.

    These sit beside the per-client assignment form rather than replacing it:
    that one moves a single client, these move a whole book of business or
    change who holds the role at all.

    Employees are not listed as a separate choice anywhere here, and that is
    deliberate rather than an omission. An account manager reaches staff
    through their clients, so moving a client moves its people with it. A
    second control for "and their employees" could only ever disagree with the
    first.
--}}

@props(['managers', 'promotable'])

<div class="grid gap-4 md:grid-cols-2 mb-4">

    {{-- ─────────────────────────────── appoint ─────────────────────────────── --}}
    <div class="card p-4">
        <h3 class="font-semibold text-slate-800 mb-1 flex items-center gap-2">
            <i class="fas fa-user-plus text-emerald-600"></i> Appoint an account manager
        </h3>
        <p class="text-xs text-slate-500 mb-3">
            Gives the role to somebody who already has a login. Use this rather than
            creating a second account for a person who is already in the system.
        </p>

        <form method="POST" action="{{ route('admin.account-managers.promote') }}"
              class="flex flex-wrap gap-2 items-end">
            @csrf
            <div class="flex-1 min-w-[14rem]">
                <label class="form-label">Who</label>
                <select name="user_id" class="form-input w-full select2" required>
                    <option value="">— Select a user —</option>
                    @foreach ($promotable as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary">Appoint</button>
        </form>
    </div>

    {{-- ────────────────────────────── hand over ────────────────────────────── --}}
    <div class="card p-4">
        <h3 class="font-semibold text-slate-800 mb-1 flex items-center gap-2">
            <i class="fas fa-right-left text-blue-600"></i> Hand over a whole book
        </h3>
        <p class="text-xs text-slate-500 mb-3">
            Moves every client, and every employee behind them, in one step. For
            somebody leaving, or a book being rebalanced.
        </p>

        <form method="POST" action="{{ route('admin.account-managers.transfer') }}"
              class="flex flex-wrap gap-2 items-end"
              onsubmit="return confirm('Move every client and employee from the first account manager to the second?')">
            @csrf
            <div class="flex-1 min-w-[10rem]">
                <label class="form-label">From</label>
                <select name="from_user_id" class="form-input w-full" required>
                    <option value="">— Select —</option>
                    @foreach ($managers as $m)
                        <option value="{{ $m->id }}">
                            {{ $m->name }} ({{ $m->managedClients->count() }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[10rem]">
                <label class="form-label">To</label>
                <select name="to_user_id" class="form-input w-full" required>
                    <option value="">— Select —</option>
                    @foreach ($managers as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary">Transfer</button>
        </form>
    </div>
</div>

{{--
    ─────────────────────────── move one client ───────────────────────────

    The common case, and the one that had no proper home: rebalancing a single
    account rather than somebody's whole book. It was only reachable through a
    panel called "Assign Client" at the very bottom of the page, whose target
    list led with "— Unassign —".

    The employees are not named here because they are not a separate decision.
    An account manager reaches staff through their clients, so repointing the
    client repoints everybody placed there. A second control for "and their
    employees" could only ever disagree with the client assignment.
--}}
<div class="card p-4 mb-4">
    <h3 class="font-semibold text-slate-800 mb-1 flex items-center gap-2">
        <i class="fas fa-arrow-right-arrow-left text-indigo-600"></i> Move one client
    </h3>
    <p class="text-xs text-slate-500 mb-3">
        Hands a single client, and everybody placed there, to a different account
        manager. Use this rather than the whole-book transfer when only one
        account is changing hands.
    </p>

    <form method="POST" action="{{ route('admin.account-managers.assign') }}"
          class="flex flex-wrap gap-2 items-end"
          onsubmit="return confirm('Move this client and all of its employees to the selected account manager?')">
        @csrf @method('PUT')

        <div class="flex-1 min-w-[16rem]">
            <label class="form-label">Client</label>
            <select name="client_id" class="form-input w-full" required>
                <option value="">— Select a client —</option>
                @foreach ($allClients as $c)
                    <option value="{{ $c->id }}">
                        {{ $c->company_name }}
                        — {{ number_format($c->employees_count) }} staff,
                        now {{ $c->accountManager?->name ?? 'unassigned' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 min-w-[12rem]">
            <label class="form-label">Hand it to</label>
            <select name="account_manager_id" class="form-input w-full">
                <option value="">— Select an account manager —</option>
                @foreach ($managers as $m)
                    <option value="{{ $m->id }}">
                        {{ $m->name }} ({{ $m->managedClients->count() }} clients now)
                    </option>
                @endforeach
                <option value="" class="text-slate-400">— or leave it with nobody —</option>
            </select>
        </div>

        <button class="btn-primary">Move client</button>
    </form>
</div>

{{-- ────────────────────────────── stand down ────────────────────────────── --}}
<div class="card p-4 mb-4">
    <h3 class="font-semibold text-slate-800 mb-1 flex items-center gap-2">
        <i class="fas fa-user-minus text-amber-600"></i> Stand somebody down
    </h3>
    <p class="text-xs text-slate-500 mb-3">
        Removes the role. The login stays, and so does their history — payroll runs
        they processed, staff they assigned. If they still hold clients you must say
        who takes them, because a client pointing at somebody who can no longer see
        it leaves its staff with nobody to run their payroll or action their leave.
    </p>

    <form method="POST" action="{{ route('admin.account-managers.remove') }}"
          class="flex flex-wrap gap-2 items-end"
          onsubmit="return confirm('Remove the account manager role from this person?')">
        @csrf
        <div class="flex-1 min-w-[12rem]">
            <label class="form-label">Account manager</label>
            <select name="user_id" class="form-input w-full" required>
                <option value="">— Select —</option>
                @foreach ($managers as $m)
                    <option value="{{ $m->id }}">
                        {{ $m->name }}
                        @if ($m->managedClients->count())
                            — holds {{ $m->managedClients->count() }} client(s)
                        @else
                            — holds none
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[12rem]">
            <label class="form-label">Their clients go to</label>
            <select name="transfer_to" class="form-input w-full">
                <option value="">— nobody, they hold none —</option>
                @foreach ($managers as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-secondary text-amber-700 border-amber-300">Stand down</button>
    </form>
</div>
