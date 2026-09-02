<?php

namespace Tests\Feature\Team;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_invite_a_team_member(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)
            ->post(route('team.invitations.store'), [
                'email' => 'new.member@example.com',
                'role' => UserRole::SalesManager->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('invitations', [
            'tenant_id' => $tenant->id,
            'invited_by_id' => $admin->id,
            'email' => 'new.member@example.com',
            'role' => UserRole::SalesManager->value,
            'accepted_at' => null,
        ]);
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_non_admin_cannot_invite_a_team_member(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => UserRole::SalesManager]);

        $this->actingAs($user)
            ->post(route('team.invitations.store'), [
                'email' => 'blocked@example.com',
                'role' => UserRole::Salesperson->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('invitations', ['email' => 'blocked@example.com']);
        Notification::assertNothingSent();
    }

    public function test_admin_sees_only_their_tenants_pending_invitations(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        Invitation::factory()->for($tenant)->create(['email' => 'visible@example.com']);
        Invitation::factory()->for($otherTenant)->create(['email' => 'hidden@example.com']);

        $this->actingAs($admin)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee('visible@example.com')
            ->assertDontSee('hidden@example.com');
    }

    public function test_invitation_acceptance_creates_a_user_in_the_invited_tenant(): void
    {
        $token = 'known-secure-invitation-token';
        $tenant = Tenant::factory()->create();
        $invitation = Invitation::factory()->for($tenant)->create([
            'email' => 'invitee@example.com',
            'role' => UserRole::Salesperson,
            'token_hash' => hash('sha256', $token),
        ]);

        $this->post(route('invitations.accept.store', ['token' => $token]), [
            'name' => 'Invited User',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'invitee@example.com',
            'role' => UserRole::Salesperson->value,
            'status' => 'active',
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_expired_or_used_invitation_cannot_be_accepted(): void
    {
        $token = 'expired-invitation-token';
        Invitation::factory()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->post(route('invitations.accept.store', ['token' => $token]), [
            'name' => 'Expired User',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['name' => 'Expired User']);
    }

    public function test_company_admin_can_change_another_members_role_and_status(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $member = User::factory()->for($tenant)->create();

        $this->actingAs($admin)
            ->put(route('team.members.update', $member), [
                'role' => UserRole::SalesManager->value,
                'status' => 'inactive',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'tenant_id' => $tenant->id,
            'role' => UserRole::SalesManager->value,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_cannot_manage_self_or_another_tenants_member(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $otherMember = User::factory()->for($otherTenant)->create();
        $payload = ['role' => UserRole::Salesperson->value, 'status' => 'inactive'];

        $this->actingAs($admin)
            ->put(route('team.members.update', $admin), $payload)
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('team.members.update', $otherMember), $payload)
            ->assertForbidden();
    }

    public function test_inactive_member_cannot_access_the_crm(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }
}
