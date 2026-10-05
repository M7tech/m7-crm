@foreach ($messages as $message)
    <div data-message-id="{{ $message->id }}" class="flex {{ $message->direction === 'outbound' ? 'justify-end' : 'justify-start' }}">
        <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm {{ $message->direction === 'outbound' ? 'bg-blue-600 text-white' : 'border border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white' }}">
            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
            <div class="mt-1 flex items-center justify-end gap-2 text-[11px] {{ $message->direction === 'outbound' ? 'text-blue-100' : 'text-zinc-400' }}">
                <time>{{ $message->sent_at?->format('M j, H:i') }}</time>
                @if ($message->direction === 'outbound')<span>{{ ucfirst($message->status) }}</span>@endif
            </div>
        </div>
    </div>
@endforeach
