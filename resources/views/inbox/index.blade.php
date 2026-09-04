<x-layouts::app :title="__('Inbox')">
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Communications</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Unified inbox</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Facebook Messenger conversations across your connected Pages.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @forelse ($conversations as $conversation)
                <a href="{{ route('inbox.show', $conversation) }}" class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4 transition last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/70">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate font-medium text-zinc-950 dark:text-white">{{ $conversation->participant_name ?: 'Facebook contact' }}</p>
                            <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">Messenger</span>
                        </div>
                        <p class="mt-1 truncate text-sm text-zinc-500">{{ $conversation->latestMessage?->body ?: 'No text preview' }}</p>
                        <p class="mt-1 text-xs text-zinc-400">{{ $conversation->integration->external_account_name }} → {{ $conversation->company->name }}</p>
                    </div>
                    <time class="shrink-0 text-xs text-zinc-400">{{ $conversation->last_message_at?->diffForHumans() }}</time>
                </a>
            @empty
                <div class="px-6 py-16 text-center">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">No Messenger conversations yet</h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-zinc-500">In Meta, add <code>pages_messaging</code> to the Business Login configuration, reconnect the Page, and subscribe the Page webhook to <code>messages</code>. New messages will then appear here.</p>
                    <a href="{{ route('integrations.meta.index') }}" class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Open integrations</a>
                </div>
            @endforelse
        </section>

        {{ $conversations->links() }}
    </div>
</x-layouts::app>
