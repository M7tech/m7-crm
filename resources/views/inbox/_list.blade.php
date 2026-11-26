@php($statusStyles = [
    'open' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    'resolved' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
])
@forelse ($conversations as $item)
    @php($unread = $item->isUnreadFor(auth()->user()))
    <a href="{{ route('inbox.show', ['conversation' => $item, ...request()->only(['status', 'channel', 'account', 'assignee', 'unread'])]) }}"
       data-conversation-row="{{ $item->id }}"
       @if ($activeId === $item->id) aria-current="page" @endif
       class="flex items-start gap-3 border-b border-zinc-100 px-4 py-3 transition last:border-0 dark:border-zinc-800 {{ $activeId === $item->id ? 'bg-blue-50 dark:bg-blue-950/40' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/70' }}">
        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $unread ? 'bg-blue-600' : 'bg-transparent' }}" aria-hidden="true"></span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="truncate text-sm {{ $unread ? 'font-semibold' : 'font-medium' }} text-zinc-950 dark:text-white">{{ $item->participant_name ?: __('Facebook contact') }}</p>
                @if ($unread)<span class="sr-only">{{ __('Unread') }}</span>@endif
                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ $item->channelLabel() }}</span>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusStyles[$item->status] ?? $statusStyles['resolved'] }}">{{ __(ucfirst($item->status)) }}</span>
            </div>
            <p class="mt-1 truncate text-sm text-zinc-500">
                @if ($item->latestMessage?->direction === 'outbound')<span class="text-zinc-400">{{ __('You:') }}</span> @endif{{ $item->latestMessage?->body ?: __('No text preview') }}
            </p>
            @unless ($compact)
                <p class="mt-1 truncate text-xs text-zinc-400">{{ $item->integration?->external_account_name ?? __('Disconnected Page') }} → {{ $item->company?->name ?? __('Unknown company') }}</p>
            @endunless
            <p class="mt-1 text-xs {{ $item->assignedTo ? 'text-zinc-600 dark:text-zinc-300' : 'text-amber-700 dark:text-amber-300' }}">{{ $item->assignedTo?->name ?? __('Unassigned') }}</p>
        </div>
        <time datetime="{{ $item->last_message_at?->toIso8601String() }}" class="shrink-0 text-xs text-zinc-400">{{ $item->last_message_at?->diffForHumans(short: true) }}</time>
    </a>
@empty
    <div class="px-6 py-12 text-center">
        <h2 class="font-semibold text-zinc-950 dark:text-white">{{ __('No conversations match these filters') }}</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">{{ __('New Messenger messages and Page comments appear here automatically.') }}</p>
    </div>
@endforelse
