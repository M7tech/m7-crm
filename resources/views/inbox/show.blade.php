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
                data-message-scroller
                class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain bg-zinc-50/70 p-5 dark:bg-zinc-950/30"
                @if ($messages->currentPage() === 1) x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })" @endif
            >
                <details class="sticky top-0 z-10 mb-2 shrink-0 rounded-xl border border-violet-200 bg-violet-50/95 shadow-sm backdrop-blur dark:border-violet-900 dark:bg-violet-950/95" @if (! $conversation->contact) open @endif>
                    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-violet-900 dark:text-violet-100">
                        {{ $conversation->contact ? __('Customer details').' · '.$conversation->contact->full_name : __('Review detected customer details') }}
                    </summary>
                    <form method="POST" action="{{ route('inbox.contact.save', $conversation) }}" class="grid gap-3 border-t border-violet-200 p-4 sm:grid-cols-2 dark:border-violet-900">
                        @csrf
                        @php($profile = $conversation->contact ?? (object) $suggestedContact)
                        <flux:input name="first_name" :label="__('First name')" :value="old('first_name', $profile->first_name)" required />
                        <flux:input name="last_name" :label="__('Last name')" :value="old('last_name', $profile->last_name)" />
                        <flux:input name="phone" :label="__('Phone')" :value="old('phone', $profile->phone)" />
                        <flux:input name="email" type="email" :label="__('Email')" :value="old('email', $profile->email)" />
                        <flux:input name="city" :label="__('City')" :value="old('city', $profile->city)" />
                        <flux:input name="organization_name" :label="__('Customer company (optional)')" :value="old('organization_name', $profile->organization_name)" />
                        <div class="sm:col-span-2">
                            <label for="category" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Customer type or category') }}</label>
                            <input id="category" name="category" value="{{ old('category', $profile->category) }}" list="conversation-categories" maxlength="100" placeholder="{{ __('client, trader, plumber, engineer, or another category') }}" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            <datalist id="conversation-categories"><option value="client"><option value="trader"><option value="plumber"><option value="engineer"><option value="contractor"><option value="architect"></datalist>
                        </div>
                        <div class="flex items-center justify-between gap-3 sm:col-span-2">
                            <p class="text-xs text-violet-700 dark:text-violet-300">{{ __('Detected values are suggestions. Review them before saving.') }}</p>
                            <flux:button type="submit" variant="primary">{{ $conversation->contact ? __('Update contact') : __('Save contact') }}</flux:button>
                        </div>
                    </form>
                </details>

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

                <div class="flex flex-col gap-3" @if ($messages->currentPage() === 1) data-inbox-updates="{{ route('inbox.updates', $conversation) }}" data-cursor="{{ $messages->getCollection()->max('id') ?? 0 }}" @endif>
                    @include('inbox._messages')
                </div>
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
