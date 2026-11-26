@foreach ($activities as $activity)
    @php($actor = $activity->actor?->name ?? __('System'))
    @php($meta = $activity->metadata ?? [])
    <li data-activity-id="{{ $activity->id }}" class="{{ $activity->type === 'note' ? 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/40' : 'px-1' }}">
        @if ($activity->type === 'note')
            <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">{{ __('Internal note') }} · {{ $actor }}</p>
            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-amber-950 dark:text-amber-50">{{ $activity->body }}</p>
        @else
            <p class="text-xs text-zinc-600 dark:text-zinc-300">
                @switch($activity->type)
                    @case('assigned')
                        {{ ($meta['to_name'] ?? null) ? __(':actor assigned the conversation to :name.', ['actor' => $actor, 'name' => $meta['to_name']]) : __(':actor unassigned the conversation.', ['actor' => $actor]) }}
                        @break
                    @case('status_changed')
                        {{ __(':actor marked the conversation :status.', ['actor' => $actor, 'status' => __($meta['to'] ?? '')]) }}
                        @break
                    @case('reply_queued')
                        {{ ($meta['visibility'] ?? '') === 'public' ? __(':actor sent a public reply.', ['actor' => $actor]) : __(':actor sent a private reply.', ['actor' => $actor]) }}
                        @break
                    @case('reply_failed')
                        <span class="text-red-700 dark:text-red-300">{{ __('A reply failed to deliver.') }}</span>
                        @break
                    @case('contact_linked')
                        {{ __(':actor linked contact :name.', ['actor' => $actor, 'name' => $meta['name'] ?? '']) }}
                        @break
                    @case('task_created')
                        {{ __(':actor created follow-up task “:title”.', ['actor' => $actor, 'title' => $meta['title'] ?? '']) }}
                        @break
                    @default
                        {{ $activity->type }}
                @endswitch
            </p>
        @endif
        <time class="mt-0.5 block text-[11px] text-zinc-400" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->diffForHumans() }}</time>
    </li>
@endforeach
