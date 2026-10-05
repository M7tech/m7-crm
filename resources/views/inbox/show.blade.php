<x-layouts::app :title="__('Conversation')">
    <div class="mx-auto grid h-[calc(100dvh-4rem)] min-h-0 w-full max-w-7xl flex-1 gap-5 p-4 sm:p-6 lg:h-dvh lg:grid-cols-[20rem_minmax(0,1fr)] lg:p-8">
        <aside class="hidden min-h-0 overflow-y-auto rounded-2xl border border-zinc-200 bg-white shadow-sm lg:block dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <a href="{{ route('inbox.index') }}" class="font-semibold text-zinc-950 dark:text-white">Inbox</a>
            </div>
            @foreach ($conversations as $item)
                <a href="{{ route('inbox.show', $item) }}" class="block border-b border-zinc-100 px-4 py-3 last:border-0 {{ $item->id === $conversation->id ? 'bg-blue-50 dark:bg-blue-950/40' : 'hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/70' }}">
                    <p class="truncate text-sm font-medium text-zinc-950 dark:text-white">{{ $item->participant_name ?: 'Facebook contact' }}</p>
                    <p class="mt-1 truncate text-xs text-zinc-500">{{ $item->channel === 'facebook_comments' ? 'Comment · ' : '' }}{{ $item->latestMessage?->body ?: 'No text preview' }}</p>
                </a>
            @endforeach
        </aside>

        <section class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <header class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <a href="{{ route('inbox.index') }}" class="mb-2 inline-flex text-sm font-medium text-blue-600 lg:hidden">← Inbox</a>
                <h1 class="font-semibold text-zinc-950 dark:text-white">{{ $conversation->participant_name ?: 'Facebook contact' }}</h1>
                <p class="mt-1 text-sm text-zinc-500">{{ $conversation->channel === 'facebook_comments' ? 'Facebook comments' : 'Messenger' }} · {{ $conversation->integration->external_account_name }} → {{ $conversation->company->name }}</p>
            </header>

            @if (session('status'))
                <div class="mx-5 mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mx-5 mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
            @endif

            <div
                class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain bg-zinc-50/70 p-5 dark:bg-zinc-950/30"
                @if ($messages->currentPage() === 1) x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })" @endif
            >
                @if ($messages->hasMorePages() || $messages->previousPageUrl())
                    <nav aria-label="Conversation history" class="mb-2 flex items-center justify-center gap-3 text-sm">
                        @if ($messages->previousPageUrl())
                            <a href="{{ $messages->previousPageUrl() }}" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 font-medium text-blue-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">Newer messages</a>
                        @endif
                        @if ($messages->hasMorePages())
                            <a href="{{ $messages->nextPageUrl() }}" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 font-medium text-blue-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">Load older messages</a>
                        @endif
                    </nav>
                @endif

                @foreach ($messages as $message)
                    <div class="flex {{ $message->direction === 'outbound' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm {{ $message->direction === 'outbound' ? 'bg-blue-600 text-white' : 'border border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white' }}">
                            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                            <div class="mt-1 flex items-center justify-end gap-2 text-[11px] {{ $message->direction === 'outbound' ? 'text-blue-100' : 'text-zinc-400' }}">
                                <time>{{ $message->sent_at?->format('M j, H:i') }}</time>
                                @if ($message->direction === 'outbound')<span>{{ ucfirst($message->status) }}</span>@endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <form data-reply-composer method="POST" action="{{ route('inbox.reply', $conversation) }}" class="shrink-0 border-t border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                @csrf
                <label for="body" class="sr-only">Reply</label>
                <div class="flex items-end gap-3">
                    <textarea id="body" name="body" rows="2" maxlength="2000" required placeholder="{{ $conversation->channel === 'facebook_comments' ? 'Write a public comment reply…' : 'Write a reply…' }}" class="min-h-12 flex-1 resize-y rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-950 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">{{ old('body') }}</textarea>
                    <flux:button type="submit" variant="primary">Send</flux:button>
                </div>
                <p class="mt-2 text-xs text-zinc-400">{{ $conversation->channel === 'facebook_comments' ? 'This reply will be posted publicly on Facebook as the connected Page.' : "Replies follow Meta's Messenger messaging-window and permission rules." }}</p>
            </form>
        </section>
    </div>
</x-layouts::app>
