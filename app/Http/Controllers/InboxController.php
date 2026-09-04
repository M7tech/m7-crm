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
            'conversations' => $this->conversations()->paginate(30),
        ]);
    }

    public function show(int $conversation): View
    {
        $conversationModel = Conversation::query()
            ->with(['integration', 'company', 'contact', 'messages' => fn ($query) => $query->orderBy('sent_at')->orderBy('id')])
            ->findOrFail($conversation);
        $this->authorize('view', $conversationModel);

        return view('inbox.show', [
            'conversation' => $conversationModel,
            'conversations' => $this->conversations()->limit(50)->get(),
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
            ->with(['integration', 'company', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }
}
