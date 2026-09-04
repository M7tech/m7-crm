<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamMemberRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanEntitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TeamMemberController extends Controller
{
    public function update(UpdateTeamMemberRequest $request, int $user, PlanEntitlements $plans): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $plans): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($request->user()->tenant_id);
            $member = User::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->findOrFail($user);
            if ($member->status !== 'active' && $request->validated('status') === 'active') {
                $plans->assertCapacity($tenant, 'members', 'status');
            }

            $member->update($request->validated());
        });

        return to_route('team.index')->with('status', 'Team member updated successfully.');
    }
}
