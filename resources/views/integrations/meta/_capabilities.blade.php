{{--
    Facebook Page capability matrix. Verified against Meta's Messenger
    Platform, Page webhook, comments, and private-replies documentation
    (2026-11-26). See docs/CRM-SPEC.md "Facebook channel capabilities".
--}}
@php
    $settings = $integration->settings ?? [];
    $active = $integration->status === 'active';
    $rows = [
        [
            'action' => __('Receive direct messages'),
            'state' => ! $active ? 'paused' : (($settings['messenger_enabled'] ?? false) ? 'supported' : 'needs_permission'),
            'detail' => __('messages webhook · pages_messaging, pages_manage_metadata'),
        ],
        [
            'action' => __('Reply to direct messages'),
            'state' => ! $active ? 'paused' : (($settings['messenger_enabled'] ?? false) ? 'supported' : 'needs_permission'),
            'detail' => __('Send API · pages_messaging with App Review · 24-hour standard window'),
        ],
        [
            'action' => __('Receive Page comments'),
            'state' => ! $active ? 'paused' : (($settings['comments_enabled'] ?? false) ? 'supported' : 'needs_permission'),
            'detail' => __('feed webhook · pages_manage_metadata, pages_read_user_content'),
        ],
        [
            'action' => __('Public reply to comments'),
            'state' => ! $active ? 'paused' : (($settings['comments_enabled'] ?? false) ? 'supported' : 'needs_permission'),
            'detail' => __('POST /{comment-id}/comments · pages_manage_engagement'),
        ],
        [
            'action' => __('Private reply to a comment'),
            'state' => 'not_built',
            'detail' => __('Available from Meta (one message within 7 days, pages_messaging) but not built in the CRM yet'),
        ],
        [
            'action' => __('Page mentions'),
            'state' => 'not_built',
            'detail' => __('mention webhook exists; replying to mentions through the API is unverified. Handle on Facebook.'),
        ],
        [
            'action' => __('Replies after 24 hours (HUMAN_AGENT tag)'),
            'state' => 'outside',
            'detail' => __('Requires separate Meta approval. Reply from Meta Business Suite.'),
        ],
    ];
    $labels = [
        'supported' => [__('Supported'), 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'],
        'needs_permission' => [__('Needs permission'), 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'],
        'paused' => [__('Paused'), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'],
        'not_built' => [__('Not in CRM'), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'],
        'outside' => [__('Outside CRM'), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'],
    ];
@endphp
<details class="mt-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Channel capabilities') }}</summary>
    <ul class="divide-y divide-zinc-100 border-t border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
        @foreach ($rows as $row)
            <li class="flex flex-col gap-1 px-4 py-2 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['action'] }}</p>
                    <p class="text-xs text-zinc-500">{{ $row['detail'] }}</p>
                </div>
                <span class="shrink-0 self-start rounded-full px-2 py-0.5 text-xs font-medium sm:self-center {{ $labels[$row['state']][1] }}">{{ $labels[$row['state']][0] }}</span>
            </li>
        @endforeach
    </ul>
</details>
