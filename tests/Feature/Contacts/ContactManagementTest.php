<?php

namespace Tests\Feature\Contacts;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_contacts_from_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $otherCompany = Company::factory()->for($otherTenant)->create();
        $user = User::factory()->for($tenant)->create();

        Contact::factory()->for($tenant)->for($company)->create(['first_name' => 'Visible']);
        Contact::factory()->for($otherTenant)->for($otherCompany)->create(['first_name' => 'Hidden']);

        $this->actingAs($user)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('Hidden');
    }

    public function test_user_can_create_a_contact_for_a_company_in_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $user = User::factory()->for($tenant)->create();

        $response = $this->actingAs($user)->post(route('contacts.store'), [
            'tenant_id' => Tenant::factory()->create()->id,
            'company_id' => $company->id,
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'job_title' => 'Purchasing Manager',
            'email' => 'sara@example.com',
            'phone' => '07500000000',
            'status' => 'active',
            'notes' => 'Prefers morning calls.',
        ]);

        $contact = Contact::query()->sole();

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('contacts.show', $contact));
        $this->assertDatabaseHas('contacts', [
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'first_name' => 'Sara',
        ]);
    }

    public function test_user_cannot_assign_a_contact_to_another_tenants_company(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $otherCompany = Company::factory()->for($otherTenant)->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), [
                'company_id' => $otherCompany->id,
                'first_name' => 'Blocked',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('contacts', ['first_name' => 'Blocked']);
    }

    public function test_contact_creation_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), [
                'company_id' => 999999,
                'first_name' => '',
                'email' => 'not-an-email',
                'status' => 'unknown',
            ])
            ->assertSessionHasErrors(['company_id', 'first_name', 'email', 'status']);
    }

    public function test_user_can_update_a_contact_in_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $contact = Contact::factory()->for($tenant)->for($company)->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->put(route('contacts.update', $contact), [
                'company_id' => $company->id,
                'first_name' => 'Updated',
                'last_name' => 'Contact',
                'email' => 'updated@example.com',
                'status' => 'inactive',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contacts.show', $contact));

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'tenant_id' => $tenant->id,
            'first_name' => 'Updated',
            'status' => 'inactive',
        ]);
    }

    public function test_user_cannot_view_another_tenants_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $otherCompany = Company::factory()->for($otherTenant)->create();
        $contact = Contact::factory()->for($otherTenant)->for($otherCompany)->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->get(route('contacts.show', $contact))
            ->assertNotFound();
    }

    public function test_salesperson_cannot_delete_a_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $contact = Contact::factory()->for($tenant)->for($company)->create();
        $user = User::factory()->for($tenant)->create(['role' => UserRole::Salesperson]);

        $this->actingAs($user)
            ->delete(route('contacts.destroy', $contact))
            ->assertForbidden();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_company_admin_can_delete_a_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $contact = Contact::factory()->for($tenant)->for($company)->create();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }
}
