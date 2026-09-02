<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token): View
    {
        return view('invitations.accept', [
            'invitation' => $this->validInvitation($token),
            'token' => $token,
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $token): User {
            // Guest acceptance is the documented exception to tenant scoping: possession
            // of the 384-bit, single-use token authorizes access to this invitation only.
            $invitation = Invitation::withoutGlobalScopes()
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(User::query()->where('email', $invitation->email)->exists(), 422, 'A user with this email already exists.');

            $user = User::create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $request->validated('name'),
                'email' => $invitation->email,
                'email_verified_at' => now(),
                'password' => $request->validated('password'),
                'role' => $invitation->role,
                'status' => 'active',
            ]);

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard')->with('status', 'Welcome to your company workspace.');
    }

    private function validInvitation(string $token): Invitation
    {
        return Invitation::withoutGlobalScopes()
            ->with('tenant')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
