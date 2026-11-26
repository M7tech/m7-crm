<?php

namespace App\Http\Controllers;

use App\Exceptions\ConcurrentReplyException;
use App\Http\Requests\AssignConversationRequest;
use App\Http\Requests\LinkConversationContactRequest;
use App\Http\Requests\SaveConversationContactRequest;
use App\Http\Requests\StoreConversationNoteRequest;
use App\Http\Requests\StoreConversationReplyRequest;
use App\Http\Requests\StoreConversationTaskRequest;
use App\Http\Requests\UpdateConversationStatusRequest;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Models\Integration;
use App\Models\Task;
use App\Models\User;
use App\Services\ConversationContactExtractor;
use App\Services\ConversationWorkflow;
use App\Support\CurrentTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InboxController extends Controller
{
    private const PRESENCE_SECONDS = 30;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);
        $filters = $this->filters($request);
        $conversations = $this->conversations($request->user(), $filters)->simplePaginate(30)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('inbox._list', ['conversations' => $conversations, 'activeId' => $request->integer('active') ?: null, 'compact' => $request->filled('active')])->render(),
            ])->header('Cache-Control', 'no-store');
        }

        return view('inbox.index', [
            'conversations' => $conversations,
            'filters' => $filters,
            'accounts' => Integration::query()
                ->select(['id', 'tenant_id', 'external_account_name', 'name', 'status'])
                ->where('provider', 'meta_lead_ads')
                ->orderBy('name')
                ->get(),
            'members' => $this->members($request->user()),
        ]);
    }

    public function show(Request $request, int $conversation, ConversationContactExtractor $extractor, ConversationWorkflow $workflow, CurrentTenant $currentTenant): View
    {
        $conversationModel = Conversation::query()
            ->with([
                'integration:id,tenant_id,external_account_name,status,settings',
                'company:id,tenant_id,name',
                'assignedTo:id,name',
                'contact:id,tenant_id,company_id,first_name,last_name,email,phone,organization_name,city,category',
            ])
            ->findOrFail($conversation);
        $this->authorize('view', $conversationModel);
        $user = $request->user();

        $messages = $conversationModel->messages()
            ->with('sentBy:id,name')
            ->select(['id', 'tenant_id', 'conversation_id', 'sent_by_id', 'direction', 'type', 'body', 'status', 'error', 'sent_at'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->simplePaginate(50, pageName: 'messages')
            ->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());

        $suggestedContact = $extractor->extract(
            $conversationModel->messages()
                ->where('direction', 'inbound')
                ->whereNotNull('body')
                ->latest('sent_at')
                ->limit(100)
                ->pluck('body')
                ->reverse()
                ->values(),
            $conversationModel->participant_name,
        );

        $workflow->markRead($conversationModel, $user);
        $contactSearch = trim((string) $request->query('contact_q', ''));

        return view('inbox.show', [
            'conversation' => $conversationModel,
            'conversations' => $this->conversations($user, $this->filters($request))->limit(50)->get(),
            'messages' => $messages,
            'latestMessageId' => (int) $conversationModel->messages()->max('id'),
            'activities' => $this->activities($conversationModel),
            'tasks' => Task::query()
                ->visibleTo($user)
                ->with('assignedTo:id,name')
                ->where('conversation_id', $conversationModel->id)
                ->orderBy('due_at')
                ->limit(20)
                ->get(),
            'members' => $this->members($user),
            'suggestedContact' => $suggestedContact,
            'contactSearch' => $contactSearch,
            'contactMatches' => mb_strlen($contactSearch) >= 2
                ? Contact::query()
                    ->with('company:id,tenant_id,name')
                    ->where(fn (Builder $query) => $query
                        ->whereLike('first_name', '%'.$contactSearch.'%')
                        ->orWhereLike('last_name', '%'.$contactSearch.'%')
                        ->orWhereLike('phone', '%'.$contactSearch.'%')
                        ->orWhereLike('email', '%'.$contactSearch.'%'))
                    ->orderBy('first_name')
                    ->limit(10)
                    ->get()
                : collect(),
            'viewers' => $this->touchPresence($conversationModel, $user),
            'timezone' => $currentTenant->tenant()->timezone ?? 'Asia/Baghdad',
        ]);
    }

    public function updates(Request $request, int $conversation, ConversationWorkflow $workflow): JsonResponse
    {
        $model = Conversation::query()->with('assignedTo:id,name')->findOrFail($conversation);
        $this->authorize('view', $model);
        $validated = $request->validate([
            'after' => ['required', 'integer', 'min:0'],
            'activity_after' => ['nullable', 'integer', 'min:0'],
        ]);
        $messages = $model->messages()
            ->with('sentBy:id,name')
            ->select(['id', 'sent_by_id', 'direction', 'body', 'status', 'error', 'sent_at'])
            ->where('id', '>', $validated['after'])
            ->orderBy('id')->limit(100)->get();
        $activities = ConversationActivity::query()
            ->with('actor:id,name')
            ->where('conversation_id', $model->id)
            ->where('id', '>', (int) ($validated['activity_after'] ?? 0))
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($messages->contains('direction', 'inbound')) {
            $workflow->markRead($model, $request->user());
        }

        return response()->json([
            'html' => $messages->isEmpty() ? '' : view('inbox._messages', ['messages' => $messages])->render(),
            'cursor' => $messages->max('id') ?? (int) $validated['after'],
            'activity_html' => $activities->isEmpty() ? '' : view('inbox._activities', ['activities' => $activities->reverse()])->render(),
            'activity_cursor' => $activities->max('id') ?? (int) ($validated['activity_after'] ?? 0),
            'deliveries' => $model->messages()
                ->where('direction', 'outbound')
                ->latest('id')
                ->limit(20)
                ->get(['id', 'status', 'error'])
                ->map(fn ($message) => [
                    'id' => $message->id,
                    'status' => $message->status,
                    'label' => __(ucfirst($message->status)),
                    'error' => $message->error,
                ])->values(),
            'conversation' => [
                'status' => $model->status,
                'status_label' => __(ucfirst($model->status)),
                'assignee_id' => $model->assigned_to_id,
                'assignee' => $model->assignedTo->name ?? __('Unassigned'),
            ],
            'viewers' => $this->touchPresence($model, $request->user()),
        ])->header('Cache-Control', 'no-store');
    }

    public function assign(AssignConversationRequest $request, int $conversation, ConversationWorkflow $workflow): RedirectResponse
    {
        $assignee = $request->filled('assigned_to_id') ? User::query()->findOrFail($request->integer('assigned_to_id')) : null;
        $workflow->assign($request->conversation(), $assignee, $request->user());

        return to_route('inbox.show', $request->conversation())->with('status', $assignee
            ? __('Conversation assigned to :name.', ['name' => $assignee->name])
            : __('Conversation returned to the unassigned queue.'));
    }

    public function updateStatus(UpdateConversationStatusRequest $request, int $conversation, ConversationWorkflow $workflow): RedirectResponse
    {
        $workflow->setStatus($request->conversation(), (string) $request->validated('status'), $request->user());

        return to_route('inbox.show', $request->conversation())->with('status', __('Conversation marked :status.', ['status' => __($request->validated('status'))]));
    }

    public function note(StoreConversationNoteRequest $request, int $conversation, ConversationWorkflow $workflow): RedirectResponse
    {
        $workflow->addNote($request->conversation(), (string) $request->validated('note'), $request->user());

        return to_route('inbox.show', $request->conversation())->with('status', __('Internal note added. Customers cannot see it.'));
    }

    public function reply(StoreConversationReplyRequest $request, int $conversation, ConversationWorkflow $workflow): RedirectResponse
    {
        $conversationModel = $request->conversation();

        try {
            $workflow->queueReply(
                $conversationModel,
                (string) $request->validated('body'),
                $request->user(),
                (int) $request->validated('seen_message_id'),
                $request->boolean('confirm_send'),
            );
        } catch (ConcurrentReplyException $exception) {
            return to_route('inbox.show', $conversationModel)
                ->withInput()
                ->with('reply_conflict', true)
                ->withErrors(['body' => $exception->senderName
                    ? __(':name sent a reply while you were writing. Review it, then confirm if your reply is still needed.', ['name' => $exception->senderName])
                    : __('A new reply was sent while you were writing. Review it, then confirm if your reply is still needed.')]);
        }

        return to_route('inbox.show', $conversationModel)
            ->with('status', __('Reply queued for delivery.'))
            ->with('reply_queued', true);
    }

    public function storeTask(StoreConversationTaskRequest $request, int $conversation, ConversationWorkflow $workflow): RedirectResponse
    {
        $task = $workflow->createTask($request->conversation(), $request->validated(), $request->user());

        return to_route('inbox.show', $request->conversation())
            ->with('status', __('Follow-up task “:title” created.', ['title' => $task->title]));
    }

    public function linkContact(LinkConversationContactRequest $request, int $conversation, ConversationWorkflow $workflow): RedirectResponse
    {
        $contact = $request->contact();
        $workflow->linkContact($request->conversation(), $contact, $request->user());

        return to_route('inbox.show', $request->conversation())
            ->with('status', __('Conversation linked to :name.', ['name' => $contact->full_name]));
    }

    public function saveContact(SaveConversationContactRequest $request, int $conversation, ConversationWorkflow $workflow): RedirectResponse
    {
        $contact = DB::transaction(function () use ($request, $workflow): Contact {
            $conversationModel = Conversation::query()->lockForUpdate()->findOrFail($request->conversation()->id);
            $contact = $conversationModel->contact;

            if ($contact) {
                $contact->update($request->validated());
            } else {
                $contact = Contact::create([
                    ...$request->validated(),
                    'company_id' => $conversationModel->company_id,
                    'status' => 'active',
                ]);
                $conversationModel->update(['contact_id' => $contact->id]);
                $workflow->activity($conversationModel, $request->user(), 'contact_linked', null, [
                    'contact_id' => $contact->id,
                    'name' => $contact->full_name,
                    'created' => true,
                ]);
            }

            return $contact;
        });

        return to_route('inbox.show', $request->conversation())
            ->with('status', __('Customer details saved to :name.', ['name' => $contact->full_name]));
    }

    /** @return array{status: string, channel: ?string, account: ?int, assignee: ?string, unread: bool} */
    private function filters(Request $request): array
    {
        $validated = validator($request->query(), [
            'status' => ['nullable', Rule::in([...Conversation::STATUSES, 'all'])],
            'channel' => ['nullable', Rule::in(Conversation::REPLYABLE_CHANNELS)],
            'account' => ['nullable', 'integer'],
            'assignee' => ['nullable', 'string', 'max:20'],
            'unread' => ['nullable', 'boolean'],
        ])->valid();

        return [
            'status' => (string) ($validated['status'] ?? 'open'),
            'channel' => $validated['channel'] ?? null,
            'account' => isset($validated['account']) ? (int) $validated['account'] : null,
            'assignee' => isset($validated['assignee']) && preg_match('/^(me|unassigned|\d+)$/', (string) $validated['assignee']) ? (string) $validated['assignee'] : null,
            'unread' => filter_var($validated['unread'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Shared queue query. Every filter value is applied to the tenant-scoped
     * query, so a foreign account or assignee ID simply matches no rows.
     *
     * @param  array{status: string, channel: ?string, account: ?int, assignee: ?string, unread: bool}  $filters
     * @return Builder<Conversation>
     */
    private function conversations(User $user, array $filters): Builder
    {
        return Conversation::query()
            ->select(['id', 'tenant_id', 'integration_id', 'company_id', 'assigned_to_id', 'channel', 'participant_name', 'status', 'last_message_at', 'last_inbound_at'])
            ->with([
                'integration:id,tenant_id,external_account_name',
                'company:id,tenant_id,name',
                'assignedTo:id,name',
                'currentRead' => fn ($query) => $query->where('user_id', $user->id),
                'latestMessage' => fn ($query) => $query->select([
                    'messages.id',
                    'messages.tenant_id',
                    'messages.conversation_id',
                    'messages.direction',
                    'messages.body',
                    'messages.sent_at',
                ]),
            ])
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['channel'], fn (Builder $query, string $channel) => $query->where('channel', $channel))
            ->when($filters['account'], fn (Builder $query, int $account) => $query->where('integration_id', $account))
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assigned_to_id', $user->id))
            ->when($filters['assignee'] === 'unassigned', fn (Builder $query) => $query->whereNull('assigned_to_id'))
            ->when(is_numeric($filters['assignee']), fn (Builder $query) => $query->where('assigned_to_id', (int) $filters['assignee']))
            ->when($filters['unread'], fn (Builder $query) => $query->unreadFor($user))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    /** @return Collection<int, ConversationActivity> */
    private function activities(Conversation $conversation): Collection
    {
        return ConversationActivity::query()
            ->with('actor:id,name')
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, User> */
    private function members(User $user): Collection
    {
        return User::query()
            ->select(['id', 'name', 'tenant_id', 'status'])
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Advisory presence so colleagues see who else has the thread open. It is
     * stored per tenant and conversation and expires on its own.
     *
     * @return array<int, string>
     */
    private function touchPresence(Conversation $conversation, User $user): array
    {
        $key = 'inbox-presence:'.$conversation->tenant_id.':'.$conversation->id;
        $now = now()->getTimestamp();
        /** @var array<int, array{name: string, seen: int}> $viewers */
        $viewers = Cache::get($key, []);
        $viewers = array_filter($viewers, fn (array $viewer): bool => $viewer['seen'] >= $now - self::PRESENCE_SECONDS);
        $viewers[$user->id] = ['name' => $user->name, 'seen' => $now];
        Cache::put($key, $viewers, self::PRESENCE_SECONDS * 2);
        unset($viewers[$user->id]);

        return array_values(array_map(fn (array $viewer): string => $viewer['name'], $viewers));
    }
}
