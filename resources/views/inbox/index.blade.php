<x-layouts::app :title="__('Inbox')">
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">{{ __('Communications') }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Team inbox') }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Facebook Messenger conversations and public Page comments shared by your team.') }}</p>
            </div>
            <p class="text-xs text-zinc-500" data-inbox-live-status aria-live="polite">{{ __('Updates automatically') }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('inbox.index') }}" class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6 dark:border-zinc-700 dark:bg-zinc-900" aria-label="{{ __('Filter conversations') }}">
            @php($select = 'block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white')
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ __('Status') }}
                <select name="status" class="mt-1 {{ $select }}">
                    @foreach (['open' => __('Open'), 'pending' => __('Pending'), 'resolved' => __('Resolved'), 'all' => __('All statuses')] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ __('Channel') }}
                <select name="channel" class="mt-1 {{ $select }}">
                    <option value="">{{ __('All channels') }}</option>
                    <option value="facebook_messenger" @selected($filters['channel'] === 'facebook_messenger')>{{ __('Messenger') }}</option>
                    <option value="facebook_comments" @selected($filters['channel'] === 'facebook_comments')>{{ __('Facebook comments') }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ __('Account') }}
                <select name="account" class="mt-1 {{ $select }}">
                    <option value="">{{ __('All accounts') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected($filters['account'] === $account->id)>{{ $account->external_account_name ?: $account->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ __('Assignee') }}
                <select name="assignee" class="mt-1 {{ $select }}">
                    <option value="">{{ __('Everyone') }}</option>
                    <option value="me" @selected($filters['assignee'] === 'me')>{{ __('Assigned to me') }}</option>
                    <option value="unassigned" @selected($filters['assignee'] === 'unassigned')>{{ __('Unassigned') }}</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected($filters['assignee'] === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-zinc-700 dark:text-zinc-200">
                <input type="checkbox" name="unread" value="1" @checked($filters['unread']) class="rounded border-zinc-300">
                {{ __('Unread only') }}
            </label>
            <div class="flex items-end gap-2">
                <flux:button type="submit" variant="primary" class="flex-1">{{ __('Apply') }}</flux:button>
                <flux:button :href="route('inbox.index')" variant="ghost">{{ __('Reset') }}</flux:button>
            </div>
        </form>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                 data-inbox-list
                 data-feed-url="{{ route('inbox.index', request()->only(['status', 'channel', 'account', 'assignee', 'unread', 'page'])) }}"
                 aria-live="polite">
            @include('inbox._list', ['activeId' => null, 'compact' => false])
        </section>

        @if ($accounts->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700">
                <p class="mx-auto max-w-xl text-sm leading-6 text-zinc-500">{{ __('Connect a Facebook Page and subscribe its webhook to messages and feed. New activity will then appear here.') }}</p>
                @can('create', \App\Models\Integration::class)
                    <a href="{{ route('integrations.meta.index') }}" class="mt-4 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Open integrations') }}</a>
                @endcan
            </div>
        @endif

        {{ $conversations->links() }}
    </div>
</x-layouts::app>
