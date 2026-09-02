<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TeamMemberController extends Controller
{
    public function update(UpdateTeamMemberRequest $request, int $user): RedirectResponse
    {
        $member = User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($user);

        $member->update($request->validated());

        return to_route('team.index')->with('status', 'Team member updated successfully.');
    }
}
