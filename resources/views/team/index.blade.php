<x-layouts::app :title="__('Team')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">Workspace</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Team administration</h1>
            <p class="mt-1 text-base text-zinc-600 dark:text-zinc-400">Invite teammates and control their workspace access.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid gap-6">
                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <h2 class="font-semibold text-zinc-950 dark:text-white">Members</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ $members->count() }} workspace members</p>
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($members as $member)
                            <div class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-medium text-zinc-950 dark:text-white">{{ $member->name }}</p>
                                        @if ($member->is(auth()->user()))
                                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">You</span>
                                        @endif
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $member->status === 'active',
                                            'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => $member->status !== 'active',
                                        ])>{{ ucfirst($member->status) }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-sm text-zinc-500">{{ $member->email }} · {{ $member->role->label() }}</p>
                                </div>

                                @can('update', $member)
                                    <form method="POST" action="{{ route('team.members.update', $member) }}" class="flex flex-col gap-2 sm:flex-row">
                                        @csrf
                                        @method('PUT')
                                        <select name="role" aria-label="Role for {{ $member->name }}" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                                            @foreach ([
                                                \App\Enums\UserRole::CompanyAdmin,
                                                \App\Enums\UserRole::SalesManager,
                                                \App\Enums\UserRole::Salesperson,
                                            ] as $role)
                                                <option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <select name="status" aria-label="Status for {{ $member->name }}" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                                            <option value="active" @selected($member->status === 'active')>Active</option>
                                            <option value="inactive" @selected($member->status === 'inactive')>Inactive</option>
                                        </select>
                                        <flux:button type="submit" size="sm">Save</flux:button>
                                    </form>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <h2 class="font-semibold text-zinc-950 dark:text-white">Pending invitations</h2>
                        <p class="mt-1 text-sm text-zinc-500">Invitation links expire after seven days.</p>
                    </div>

                    @forelse ($invitations as $invitation)
                        <div class="flex flex-col gap-3 border-b border-zinc-100 px-5 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                            <div>
                                <p class="font-medium text-zinc-950 dark:text-white">{{ $invitation->email }}</p>
                                <p class="mt-1 text-sm text-zinc-500">{{ $invitation->role->label() }} · expires {{ $invitation->expires_at->diffForHumans() }}</p>
                            </div>
                            <form method="POST" action="{{ route('team.invitations.destroy', $invitation) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" variant="ghost" size="sm">Revoke</flux:button>
                            </form>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-zinc-500">No pending invitations.</p>
                    @endforelse
                </section>
            </div>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Invite teammate</h2>
                <p class="mt-1 text-sm text-zinc-500">An invitation link will be sent by email.</p>

                <form method="POST" action="{{ route('team.invitations.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <flux:input name="email" type="email" :label="__('Email address')" :value="old('email')" required />
                    <div>
                        <label for="role" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Role</label>
                        <select id="role" name="role" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach ([
                                \App\Enums\UserRole::Salesperson,
                                \App\Enums\UserRole::SalesManager,
                                \App\Enums\UserRole::CompanyAdmin,
                            ] as $role)
                                <option value="{{ $role->value }}" @selected(old('role', \App\Enums\UserRole::Salesperson->value) === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <flux:button type="submit" variant="primary" class="w-full">Send invitation</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::app>
