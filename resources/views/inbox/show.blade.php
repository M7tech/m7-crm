@php
    $user = auth()->user();
    $canReply = $user->can('reply', $conversation);
    $canManage = $user->can('manage', $conversation);
    $canAssignOthers = $user->can('assignOthers', $conversation);
    $isPublic = $conversation->replyVisibility() === 'public';
    $postId = $conversation->channel === 'facebook_comments'
        ? $conversation->messages()->where('direction', 'inbound')->orderBy('id')->value('payload->post_id')
        : null;
    $field = 'block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white';
    $card = 'rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900';
@endphp
<x-layouts::app :title="__('Conversation')">
    <div class="mx-auto grid w-full max-w-[110rem] flex-1 gap-5 p-4 sm:p-6 lg:h-dvh lg:min-h-0 lg:grid-cols-[minmax(0,1fr)_22rem] lg:p-8 2xl:grid-cols-[19rem_minmax(0,1fr)_23rem]">
        {{-- Shared queue --}}
        <aside class="hidden min-h-0 flex-col overflow-hidden {{ $card }} 2xl:flex" aria-label="{{ __('Conversations') }}">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <a href="{{ route('inbox.index', request()->only(['status', 'channel', 'account', 'assignee', 'unread'])) }}" class="font-semibold text-zinc-950 dark:text-white">{{ __('Team inbox') }}</a>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto" data-inbox-list data-active-id="{{ $conversation->id }}"
                 data-feed-url="{{ route('inbox.index', request()->only(['status', 'channel', 'account', 'assignee', 'unread'])) }}">
                @include('inbox._list', ['conversations' => $conversations, 'activeId' => $conversation->id, 'compact' => true])
            </div>
        </aside>

        {{-- Conversation and composer --}}
        <section class="flex h-[calc(100dvh-6rem)] min-h-[32rem] flex-col overflow-hidden lg:h-auto lg:min-h-0 {{ $card }}"
                 data-conversation
                 data-updates-url="{{ route('inbox.updates', $conversation) }}"
                 data-activity-cursor="{{ $activities->max('id') ?? 0 }}"
                 data-draft-key="inbox-draft:{{ $conversation->tenant_id }}:{{ $conversation->id }}:{{ $user->id }}"
                 @if (session('reply_queued')) data-clear-draft @endif>
            <header class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('inbox.index') }}" class="mb-1 inline-flex text-sm font-medium text-blue-600 2xl:hidden">← {{ __('Team inbox') }}</a>
                        <h1 class="truncate font-semibold text-zinc-950 dark:text-white">{{ $conversation->participant_name ?: __('Facebook contact') }}</h1>
                        <p class="mt-1 text-sm text-zinc-500">{{ $conversation->channelLabel() }} · {{ $conversation->integration->external_account_name }} → {{ $conversation->company->name }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ __('Status') }}: <span data-conversation-status>{{ __(ucfirst($conversation->status)) }}</span></span>
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ __('Assignee') }}: <span data-conversation-assignee data-assignee-id="{{ $conversation->assigned_to_id }}">{{ $conversation->assignedTo?->name ?? __('Unassigned') }}</span></span>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    @if ($canManage)
                        <form method="POST" action="{{ route('inbox.assign', $conversation) }}" class="flex items-end gap-2">
                            @csrf @method('PUT')
                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ __('Assign to') }}
                                <select name="assigned_to_id" class="mt-1 {{ $field }} py-1.5">
                                    <option value="">{{ __('Unassigned') }}</option>
                                    @foreach ($members as $member)
                                        @if ($canAssignOthers || $member->id === $user->id || $member->id === $conversation->assigned_to_id)
                                            <option value="{{ $member->id }}" @selected($conversation->assigned_to_id === $member->id)>{{ $member->id === $user->id ? __(':name (me)', ['name' => $member->name]) : $member->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <flux:button type="submit" size="sm">{{ __('Save') }}</flux:button>
                        </form>
                        <form method="POST" action="{{ route('inbox.status', $conversation) }}" class="flex flex-wrap gap-1" aria-label="{{ __('Conversation status') }}">
                            @csrf @method('PUT')
                            @foreach (['open' => __('Reopen'), 'pending' => __('Mark pending'), 'resolved' => __('Resolve')] as $value => $label)
                                @if ($conversation->status !== $value)
                                    <flux:button type="submit" name="status" value="{{ $value }}" size="sm" :variant="$value === 'resolved' ? 'primary' : 'outline'">{{ $label }}</flux:button>
                                @endif
                            @endforeach
                        </form>
                    @else
                        <p class="text-xs text-zinc-500">{{ __('Assigned to :name. Ask them or a manager to reassign it.', ['name' => $conversation->assignedTo?->name ?? __('a teammate')]) }}</p>
                    @endif
                </div>

                <p data-presence class="{{ $viewers === [] ? 'hidden' : '' }} rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 dark:bg-amber-950/50 dark:text-amber-200" role="status">
                    {{ __('Also viewing:') }} <span data-presence-names>{{ implode(', ', $viewers) }}</span>
                </p>
            </header>

            @if (session('status'))
                <div class="mx-5 mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mx-5 mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200" role="alert">{{ $errors->first() }}</div>
            @endif

            <div data-message-scroller
                 class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain bg-zinc-50/70 p-5 dark:bg-zinc-950/30"
                 @if ($messages->currentPage() === 1) x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })" @endif>
                @if ($messages->hasMorePages() || $messages->previousPageUrl())
                    <nav aria-label="{{ __('Conversation history') }}" class="mb-2 flex items-center justify-center gap-3 text-sm">
                        @if ($messages->previousPageUrl())
                            <a href="{{ $messages->previousPageUrl() }}" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 font-medium text-blue-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">{{ __('Newer messages') }}</a>
                        @endif
                        @if ($messages->hasMorePages())
                            <a href="{{ $messages->nextPageUrl() }}" class="rounded-lg border border-zinc-200 bg-white px-3 py-2 font-medium text-blue-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">{{ __('Load older messages') }}</a>
                        @endif
                    </nav>
                @endif

                <div class="flex flex-col gap-3" @if ($messages->currentPage() === 1) data-inbox-updates="{{ route('inbox.updates', $conversation) }}" data-cursor="{{ $latestMessageId }}" @endif>
                    @include('inbox._messages')
                </div>
            </div>

            {{-- Composer: customer reply or internal note --}}
            <div class="shrink-0 border-t border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" x-data="{ mode: '{{ old('note') ? 'note' : 'reply' }}' }">
                <div class="mb-3 inline-flex rounded-lg bg-zinc-100 p-1 text-sm dark:bg-zinc-800" role="tablist" aria-label="{{ __('Composer mode') }}">
                    <button type="button" role="tab" :aria-selected="mode === 'reply'" @click="mode = 'reply'" class="rounded-md px-3 py-1.5 font-medium" :class="mode === 'reply' ? 'bg-white shadow-sm dark:bg-zinc-700' : 'text-zinc-500'">
                        {{ $isPublic ? __('Public reply') : __('Private reply') }}
                    </button>
                    <button type="button" role="tab" :aria-selected="mode === 'note'" @click="mode = 'note'" class="rounded-md px-3 py-1.5 font-medium" :class="mode === 'note' ? 'bg-amber-100 text-amber-900 shadow-sm dark:bg-amber-900 dark:text-amber-100' : 'text-zinc-500'">
                        {{ __('Internal note') }}
                    </button>
                </div>

                <div x-show="mode === 'reply'">
                    @if ($canReply)
                        <form data-reply-composer method="POST" action="{{ route('inbox.reply', $conversation) }}">
                            @csrf
                            <input type="hidden" name="seen_message_id" value="{{ $latestMessageId }}" data-seen-message-id>
                            <p class="mb-2 flex items-center gap-2 text-xs font-semibold {{ $isPublic ? 'text-orange-700 dark:text-orange-300' : 'text-blue-700 dark:text-blue-300' }}">
                                <span class="rounded px-1.5 py-0.5 {{ $isPublic ? 'bg-orange-100 dark:bg-orange-950' : 'bg-blue-100 dark:bg-blue-950' }}">{{ $isPublic ? __('PUBLIC') : __('PRIVATE') }}</span>
                                {{ $isPublic ? __('Posted publicly under the comment as :page.', ['page' => $conversation->integration->external_account_name]) : __('Sent privately in Messenger as :page.', ['page' => $conversation->integration->external_account_name]) }}
                            </p>
                            <label for="body" class="sr-only">{{ __('Reply') }}</label>
                            <div class="flex items-end gap-3">
                                <textarea id="body" name="body" rows="2" maxlength="2000" required data-draft-input placeholder="{{ $isPublic ? __('Write a public comment reply…') : __('Write a reply…') }}" class="min-h-12 flex-1 resize-y rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-950 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">{{ old('body') }}</textarea>
                                <flux:button type="submit" variant="primary">{{ __('Send') }}</flux:button>
                            </div>
                            @if (session('reply_conflict'))
                                <label class="mt-2 flex items-center gap-2 text-sm font-medium text-red-800 dark:text-red-200">
                                    <input type="checkbox" name="confirm_send" value="1" class="rounded border-zinc-300">
                                    {{ __('I reviewed the newer reply and still want to send mine.') }}
                                </label>
                            @endif
                            <p class="mt-2 text-xs text-zinc-400">
                                {{ $isPublic ? __('Anyone who can see the post can read this reply.') : __('Messenger allows standard replies within 24 hours of the customer\'s last message.') }}
                                {{ __('Your draft is kept on this device until it is sent.') }}
                            </p>
                        </form>
                    @else
                        <p class="rounded-lg bg-zinc-100 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            @if ($conversation->integration->status !== 'active')
                                {{ __('Replies are paused because the Facebook Page is disconnected or needs reconnection.') }}
                            @elseif ($conversation->status === 'resolved')
                                {{ __('This conversation is resolved. Reopen it to reply.') }}
                            @else
                                {{ __('Only the assignee or a manager can reply to this conversation.') }}
                            @endif
                        </p>
                    @endif
                </div>

                <form x-show="mode === 'note'" x-cloak method="POST" action="{{ route('inbox.notes.store', $conversation) }}">
                    @csrf
                    <p class="mb-2 text-xs font-semibold text-amber-800 dark:text-amber-300">{{ __('Internal note — visible only to your team, never sent to the customer.') }}</p>
                    <label for="note" class="sr-only">{{ __('Internal note') }}</label>
                    <div class="flex items-end gap-3">
                        <textarea id="note" name="note" rows="2" maxlength="3000" required class="min-h-12 flex-1 resize-y rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-zinc-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-white">{{ old('note') }}</textarea>
                        <flux:button type="submit">{{ __('Add note') }}</flux:button>
                    </div>
                </form>
            </div>
        </section>

        {{-- Customer, tasks, activity --}}
        <aside class="flex min-h-0 flex-col gap-4 lg:overflow-y-auto" aria-label="{{ __('Customer and follow-up') }}">
            <section class="{{ $card }} p-4">
                <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Customer') }}</h2>
                @if ($conversation->contact)
                    <dl class="mt-3 space-y-1 text-sm">
                        <div><dt class="sr-only">{{ __('Name') }}</dt><dd class="font-medium text-zinc-900 dark:text-white"><a href="{{ route('contacts.show', $conversation->contact) }}" class="hover:underline">{{ $conversation->contact->full_name }}</a></dd></div>
                        @foreach ([__('Phone') => $conversation->contact->phone, __('Email') => $conversation->contact->email, __('City') => $conversation->contact->city, __('Category') => $conversation->contact->category] as $label => $value)
                            @if ($value)<div class="flex gap-2"><dt class="text-zinc-500">{{ $label }}</dt><dd class="text-zinc-800 dark:text-zinc-200">{{ $value }}</dd></div>@endif
                        @endforeach
                    </dl>
                @else
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Not linked to a CRM contact yet.') }}</p>
                @endif

                <details class="mt-3 rounded-lg border border-violet-200 bg-violet-50/60 dark:border-violet-900 dark:bg-violet-950/30" @if (! $conversation->contact && ! $contactSearch) open @endif>
                    <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-violet-900 dark:text-violet-100">
                        {{ $conversation->contact ? __('Update contact details') : __('Review detected customer details') }}
                    </summary>
                    <form method="POST" action="{{ route('inbox.contact.save', $conversation) }}" class="grid gap-3 border-t border-violet-200 p-3 dark:border-violet-900">
                        @csrf
                        @php($profile = $conversation->contact ?? (object) $suggestedContact)
                        <flux:input name="first_name" :label="__('First name')" :value="old('first_name', $profile->first_name)" required />
                        <flux:input name="last_name" :label="__('Last name')" :value="old('last_name', $profile->last_name)" />
                        <flux:input name="phone" :label="__('Phone')" :value="old('phone', $profile->phone)" />
                        <flux:input name="email" type="email" :label="__('Email')" :value="old('email', $profile->email)" />
                        <flux:input name="city" :label="__('City')" :value="old('city', $profile->city)" />
                        <flux:input name="organization_name" :label="__('Customer company (optional)')" :value="old('organization_name', $profile->organization_name)" />
                        <div>
                            <label for="category" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Customer type or category') }}</label>
                            <input id="category" name="category" value="{{ old('category', $profile->category) }}" list="conversation-categories" maxlength="100" placeholder="{{ __('client, trader, plumber, engineer, or another category') }}" class="{{ $field }}">
                            <datalist id="conversation-categories"><option value="client"><option value="trader"><option value="plumber"><option value="engineer"><option value="contractor"><option value="architect"></datalist>
                        </div>
                        <p class="text-xs text-violet-700 dark:text-violet-300">{{ __('Detected values are suggestions. Review them before saving.') }}</p>
                        <flux:button type="submit" variant="primary">{{ $conversation->contact ? __('Update contact') : __('Confirm and create contact') }}</flux:button>
                    </form>
                </details>

                <details class="mt-2 rounded-lg border border-zinc-200 dark:border-zinc-700" @if ($contactSearch) open @endif>
                    <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Link an existing contact') }}</summary>
                    <div class="border-t border-zinc-200 p-3 dark:border-zinc-700">
                        <form method="GET" action="{{ route('inbox.show', $conversation) }}" class="flex gap-2">
                            <label for="contact_q" class="sr-only">{{ __('Search contacts') }}</label>
                            <input id="contact_q" name="contact_q" value="{{ $contactSearch }}" minlength="2" placeholder="{{ __('Name, phone, or email') }}" class="{{ $field }}">
                            <flux:button type="submit" size="sm">{{ __('Search') }}</flux:button>
                        </form>
                        <ul class="mt-2 space-y-1">
                            @foreach ($contactMatches as $match)
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <span class="min-w-0 truncate">{{ $match->full_name }} <span class="text-xs text-zinc-500">· {{ $match->company->name }}</span></span>
                                    <form method="POST" action="{{ route('inbox.contact.link', $conversation) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="contact_id" value="{{ $match->id }}">
                                        <flux:button type="submit" size="xs">{{ __('Link') }}</flux:button>
                                    </form>
                                </li>
                            @endforeach
                            @if ($contactSearch && $contactMatches->isEmpty())
                                <li class="text-xs text-zinc-500">{{ __('No matching contacts.') }}</li>
                            @endif
                        </ul>
                    </div>
                </details>
            </section>

            <section class="{{ $card }} p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Follow-up tasks') }}</h2>
                    <a href="{{ route('tasks.board') }}" class="text-xs font-medium text-blue-600">{{ __('Open board') }}</a>
                </div>
                <ul class="mt-2 space-y-2">
                    @forelse ($tasks as $task)
                        <li class="flex items-start justify-between gap-2 text-sm">
                            <a href="{{ route('tasks.show', $task) }}" class="min-w-0 truncate font-medium text-zinc-900 hover:underline dark:text-white">{{ $task->title }}</a>
                            <span class="shrink-0 text-xs text-zinc-500">{{ \App\Models\Task::statusLabel($task->status) }} · {{ $task->due_at->timezone($timezone)->format('M j') }}</span>
                        </li>
                    @empty
                        <li class="text-xs text-zinc-500">{{ __('No follow-up tasks for this conversation.') }}</li>
                    @endforelse
                </ul>
                @can('create', \App\Models\Task::class)
                    <details class="mt-3 rounded-lg border border-zinc-200 dark:border-zinc-700" @if ($errors->hasAny(['title', 'due_at', 'assigned_to_id', 'priority'])) open @endif>
                        <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Create follow-up task') }}</summary>
                        <form method="POST" action="{{ route('inbox.tasks.store', $conversation) }}" class="grid gap-3 border-t border-zinc-200 p-3 dark:border-zinc-700">
                            @csrf
                            <flux:input name="title" :label="__('Task title')" :value="old('title', __('Follow up with :name', ['name' => $conversation->participant_name ?: __('customer')]))" required maxlength="180" />
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Assigned to') }}
                                <select name="assigned_to_id" required class="mt-1 {{ $field }}">
                                    @foreach ($members as $member)
                                        @if ($user->role !== \App\Enums\UserRole::Salesperson || $member->id === $user->id)
                                            <option value="{{ $member->id }}" @selected((int) old('assigned_to_id', $conversation->assigned_to_id ?? $user->id) === $member->id)>{{ $member->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <flux:input name="due_at" type="datetime-local" :label="__('Due date and time')" :value="old('due_at', now($timezone)->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'))" required />
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Priority') }}
                                <select name="priority" required class="mt-1 {{ $field }}">
                                    @foreach (\App\Models\Task::PRIORITIES as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ __(ucfirst($priority)) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <flux:textarea name="description" :label="__('Notes')" rows="2">{{ old('description') }}</flux:textarea>
                            <p class="text-xs text-zinc-500">{{ __('The task is linked to this conversation and its customer and appears in My Tasks and on the board.') }}</p>
                            <flux:button type="submit" variant="primary">{{ __('Create task') }}</flux:button>
                        </form>
                    </details>
                @endcan
            </section>

            <section class="{{ $card }} p-4">
                <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Notes and activity') }}</h2>
                <ol class="mt-3 space-y-3" data-activity-list>
                    @include('inbox._activities', ['activities' => $activities])
                </ol>
            </section>

            <section class="{{ $card }} p-4 text-xs leading-5 text-zinc-600 dark:text-zinc-300">
                <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Handled outside the CRM') }}</h2>
                <ul class="mt-2 list-disc space-y-1 ps-4">
                    @if ($conversation->channel === 'facebook_comments')
                        <li>{{ __('Private replies to comments, reactions, hiding, and deleting comments are not available here yet.') }}</li>
                    @else
                        <li>{{ __('Attachments, reactions, and replies after the 24-hour Messenger window are not available here yet.') }}</li>
                    @endif
                    <li>{{ __('Page mentions are not imported into the inbox.') }}</li>
                </ul>
                @if ($postId)
                    <a href="https://www.facebook.com/{{ rawurlencode((string) $postId) }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex font-semibold text-blue-600">{{ __('Open the original post on Facebook') }} ↗</a>
                @elseif ($conversation->channel === 'facebook_messenger')
                    <a href="https://business.facebook.com/latest/inbox" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex font-semibold text-blue-600">{{ __('Open Meta Business Suite inbox') }} ↗</a>
                @endif
            </section>
        </aside>
    </div>
</x-layouts::app>
