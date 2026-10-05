<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveConversationContactRequest;
use App\Http\Requests\StoreConversationReplyRequest;
use App\Jobs\SendMetaCommentReply;
use App\Jobs\SendMetaMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationContactExtractor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    public function updates(Request $request, int $conversation): JsonResponse
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);
        $validated = $request->validate(['after' => ['required', 'integer', 'min:0']]);
        $messages = $model->messages()
            ->select(['id', 'direction', 'body', 'status', 'sent_at'])
            ->where('id', '>', $validated['after'])
            ->orderBy('id')->limit(100)->get();

        return response()->json([
            'html' => $messages->isEmpty() ? '' : view('inbox._messages', ['messages' => $messages])->render(),
            'cursor' => $messages->max('id') ?? (int) $validated['after'],
        ])->header('Cache-Control', 'no-store');
    }

    public function index(): View
    {
        $this->authorize('viewAny', Conversation::class);

        return view('inbox.index', [
            'conversations' => $this->conversations()->simplePaginate(30),
        ]);
    }

    public function show(int $conversation, ConversationContactExtractor $extractor): View
    {
        $conversationModel = Conversation::query()
            ->with([
                'integration:id,tenant_id,external_account_name',
                'company:id,tenant_id,name',
                'contact:id,tenant_id,company_id,first_name,last_name,email,phone,organization_name,city,category',
            ])
            ->findOrFail($conversation);
        $this->authorize('view', $conversationModel);

        $messages = $conversationModel->messages()
            ->select(['id', 'tenant_id', 'conversation_id', 'direction', 'type', 'body', 'status', 'sent_at'])
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

        return view('inbox.show', [
            'conversation' => $conversationModel,
            'conversations' => $this->conversations()->limit(50)->get(),
            'messages' => $messages,
            'suggestedContact' => $suggestedContact,
        ]);
    }

    public function saveContact(SaveConversationContactRequest $request, int $conversation): RedirectResponse
    {
        $contact = DB::transaction(function () use ($request): Contact {
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
            }

            return $contact;
        });

        return to_route('inbox.show', $request->conversation())
            ->with('status', __('Customer details saved to :name.', ['name' => $contact->full_name]));
    }

    public function reply(StoreConversationReplyRequest $request, int $conversation): RedirectResponse
    {
        $conversationModel = $request->conversation();

        DB::transaction(function () use ($conversationModel, $request): void {
            $replyToCommentId = $conversationModel->channel === 'facebook_comments'
                ? $conversationModel->messages()
                    ->where('direction', 'inbound')
                    ->whereNotNull('external_id')
                    ->orderByDesc('sent_at')
                    ->orderByDesc('id')
                    ->value('external_id')
                : null;
            $message = Message::create([
                'conversation_id' => $conversationModel->id,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $request->validated('body'),
                'payload' => $replyToCommentId ? ['reply_to_comment_id' => $replyToCommentId] : null,
                'status' => 'queued',
                'sent_at' => now(),
            ]);
            $conversationModel->update(['last_message_at' => $message->sent_at]);
            match ($conversationModel->channel) {
                'facebook_comments' => SendMetaCommentReply::dispatch($message->id, $message->tenant_id)->afterCommit(),
                default => SendMetaMessage::dispatch($message->id, $message->tenant_id)->afterCommit(),
            };
        });

        return to_route('inbox.show', $conversationModel)->with('status', 'Reply queued for delivery.');
    }

    /** @return Builder<Conversation> */
    private function conversations(): Builder
    {
        return Conversation::query()
            ->select(['id', 'tenant_id', 'integration_id', 'company_id', 'channel', 'participant_name', 'last_message_at'])
            ->with([
                'integration:id,tenant_id,external_account_name',
                'company:id,tenant_id,name',
                'latestMessage' => fn ($query) => $query->select([
                    'messages.id',
                    'messages.tenant_id',
                    'messages.conversation_id',
                    'messages.body',
                    'messages.sent_at',
                ]),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }
}
