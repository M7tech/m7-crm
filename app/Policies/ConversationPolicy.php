<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;

/**
 * The inbox is a shared team queue: every active tenant user can read it.
 * Writes that change ownership or reach the customer are limited so that a
 * salesperson cannot take over, close, or reply inside a conversation that a
 * colleague owns. Company admins and sales managers can supervise any thread.
 */
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

    /** Assign, reassign, release, or change the status of the conversation. */
    public function manage(User $user, Conversation $conversation): bool
    {
        if (! $this->view($user, $conversation)) {
            return false;
        }

        return $user->role !== UserRole::Salesperson
            || $conversation->assigned_to_id === null
            || $conversation->assigned_to_id === $user->id;
    }

    /** Assigning to someone other than yourself requires supervision or current ownership. */
    public function assignOthers(User $user, Conversation $conversation): bool
    {
        if (! $this->manage($user, $conversation)) {
            return false;
        }

        return $user->role !== UserRole::Salesperson || $conversation->assigned_to_id === $user->id;
    }

    public function addNote(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->manage($user, $conversation)
            && $conversation->status !== 'resolved'
            && in_array($conversation->channel, Conversation::REPLYABLE_CHANNELS, true)
            && $conversation->integration?->status === 'active';
    }
}
