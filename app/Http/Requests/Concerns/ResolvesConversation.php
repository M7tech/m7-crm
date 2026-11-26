<?php

namespace App\Http\Requests\Concerns;

use App\Models\Conversation;

trait ResolvesConversation
{
    private ?Conversation $resolvedConversation = null;

    public function conversation(): Conversation
    {
        return $this->resolvedConversation ??= Conversation::query()
            ->with('integration:id,tenant_id,status')
            ->findOrFail((int) $this->route('conversation'));
    }
}
