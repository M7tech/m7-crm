<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $conversation->tenant_id;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $conversation->status === 'open'
            && $conversation->channel === 'facebook_messenger';
    }
}
