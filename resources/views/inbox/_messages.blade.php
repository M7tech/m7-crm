@foreach ($messages as $message)
    @php($outbound = $message->direction === 'outbound')
    <div data-message-id="{{ $message->id }}" class="flex {{ $outbound ? 'justify-end' : 'justify-start' }}">
        <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm {{ $outbound ? ($message->status === 'failed' ? 'border border-red-300 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950/50 dark:text-red-100' : 'bg-blue-600 text-white') : 'border border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white' }}">
            <p class="whitespace-pre-wrap break-words" data-message-body>{{ $message->body }}</p>
            <div class="mt-1 flex flex-wrap items-center justify-end gap-x-2 gap-y-1 text-[11px] {{ $outbound && $message->status !== 'failed' ? 'text-blue-100' : 'text-zinc-500 dark:text-zinc-400' }}">
                @if ($outbound && $message->sentBy)<span>{{ $message->sentBy->name }}</span>@endif
                <time datetime="{{ $message->sent_at?->toIso8601String() }}">{{ $message->sent_at?->format('M j, H:i') }}</time>
                @if ($outbound)
                    <span data-delivery-status="{{ $message->id }}" class="font-medium">{{ __(ucfirst($message->status)) }}</span>
                @endif
            </div>
            @if ($outbound)
                <div data-delivery-error="{{ $message->id }}" class="{{ $message->status === 'failed' ? '' : 'hidden' }} mt-2 border-t border-red-200 pt-2 text-xs dark:border-red-800">
                    <p data-delivery-error-text>{{ $message->error }}</p>
                    <button type="button" data-restore-draft class="mt-1 font-semibold underline">{{ __('Copy back to the composer') }}</button>
                </div>
            @endif
        </div>
    </div>
@endforeach
