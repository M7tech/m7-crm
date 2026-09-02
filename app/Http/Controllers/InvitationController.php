<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($request, $token): Invitation {
            Invitation::query()
                ->where('email', $request->validated('email'))
                ->whereNull('accepted_at')
                ->delete();

            return Invitation::create([
                'invited_by_id' => $request->user()->id,
                'email' => $request->validated('email'),
                'role' => $request->validated('role'),
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);
        });

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation, $token));

        return to_route('team.index')->with('status', 'Invitation sent successfully.');
    }

    public function destroy(int $invitation): RedirectResponse
    {
        $invitationModel = Invitation::query()->findOrFail($invitation);
        $this->authorize('delete', $invitationModel);
        $invitationModel->delete();

        return to_route('team.index')->with('status', 'Invitation revoked.');
    }
}
