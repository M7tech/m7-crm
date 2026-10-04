<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationReplyRequest;
use App\Jobs\SendMetaMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Conversation::class);

        return view('inbox.index', [
            'conversations' => $this->conversations()->simplePaginate(30),
        ]);
    }

    public function show(int $conversation): View
    {
        $conversationModel = Conversation::query()
            ->with([
                'integration:id,tenant_id,external_account_name',
                'company:id,tenant_id,name',
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

        return view('inbox.show', [
            'conversation' => $conversationModel,
            'conversations' => $this->conversations()->limit(50)->get(),
            'messages' => $messages,
        ]);
    }

    public function reply(StoreConversationReplyRequest $request, int $conversation): RedirectResponse
    {
        $conversationModel = $request->conversation();

        DB::transaction(function () use ($conversationModel, $request): void {
            $message = Message::create([
                'conversation_id' => $conversationModel->id,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $request->validated('body'),
                'status' => 'queued',
                'sent_at' => now(),
            ]);
            $conversationModel->update(['last_message_at' => $message->sent_at]);
            SendMetaMessage::dispatch($message->id, $message->tenant_id)->afterCommit();
        });

        return to_route('inbox.show', $conversationModel)->with('status', 'Reply queued for delivery.');
    }

    /** @return Builder<Conversation> */
    private function conversations(): Builder
    {
        return Conversation::query()
            ->select(['id', 'tenant_id', 'integration_id', 'company_id', 'participant_name', 'last_message_at'])
            ->with([
                'integration:id,tenant_id,external_account_name',
                'company:id,tenant_id,name',
                'latestMessage:id,tenant_id,conversation_id,body,sent_at',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }
}
