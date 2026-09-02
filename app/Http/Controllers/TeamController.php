<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Invitation::class);
        $tenantId = request()->user()->tenant_id;

        return view('team.index', [
            'members' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'invitations' => Invitation::query()
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->latest()
                ->get(),
        ]);
    }
}
