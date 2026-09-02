<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_owned_queries_fail_closed_without_a_resolved_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        Contact::factory()->for($tenant)->for($company)->create();
        $contactImport = new ContactImport([
            'original_name' => 'contacts.csv',
            'preview_token_hash' => hash('sha256', 'test-token'),
        ]);
        $contactImport->tenant_id = $tenant->id;
        $contactImport->save();
        Invitation::factory()->for($tenant)->create();

        $this->assertSame(0, Company::query()->count());
        $this->assertSame(1, Company::withoutGlobalScopes()->count());
        $this->assertSame(0, Contact::query()->count());
        $this->assertSame(1, Contact::withoutGlobalScopes()->count());
        $this->assertSame(0, ContactImport::query()->count());
        $this->assertSame(1, ContactImport::withoutGlobalScopes()->count());
        $this->assertSame(0, Invitation::query()->count());
        $this->assertSame(1, Invitation::withoutGlobalScopes()->count());
    }

    public function test_suspended_tenant_cannot_access_the_crm(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_companies_across_tenants(): void
    {
        Company::factory()->create(['name' => 'Company One']);
        Company::factory()->create(['name' => 'Company Two']);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Company One')
            ->assertSee('Company Two');
    }
}
