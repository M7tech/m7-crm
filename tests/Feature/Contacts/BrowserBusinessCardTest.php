<?php

namespace Tests\Feature\Contacts;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessCardOcr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BrowserBusinessCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_scanner_requires_no_server_ocr_and_lists_only_tenant_companies(): void
    {
        $user = User::factory()->create();
        $own = Company::factory()->create(['tenant_id' => $user->tenant_id]);
        $other = Company::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $ocr = Mockery::mock(BusinessCardOcr::class);
        $ocr->shouldNotReceive('isAvailable');
        $this->app->instance(BusinessCardOcr::class, $ocr);

        $this->actingAs($user)->get(route('contacts.business-cards.create'))
            ->assertOk()->assertSee('Scan on this device')->assertSee($own->name)
            ->assertDontSee($other->name);
    }

    public function test_reviewed_fields_save_without_a_scan_or_client_selected_tenant(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user)->postJson(route('contacts.business-cards.on-device.save'), [
            'create_company' => false,
            'company_id' => $company->id, 'first_name' => 'محمد', 'last_name' => 'Ahmed',
            'status' => 'active', 'tenant_id' => 999999,
        ])->assertCreated()->assertJsonStructure(['redirect']);

        $this->assertSame($user->tenant_id, Contact::withoutGlobalScopes()->sole()->tenant_id);
        $this->assertDatabaseCount('business_card_scans', 0);
    }

    public function test_scanner_can_create_a_client_company_and_contact_atomically(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('contacts.business-cards.on-device.save'), [
            'create_company' => true,
            'new_company_name' => 'Atlas Plast',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'status' => 'active',
            'tenant_id' => 999999,
        ])->assertCreated()->assertJsonStructure(['redirect']);

        $company = Company::withoutGlobalScopes()->sole();
        $contact = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($user->tenant_id, $company->tenant_id);
        $this->assertSame($user->tenant_id, $contact->tenant_id);
        $this->assertSame($company->id, $contact->company_id);
        $this->assertSame('Atlas Plast', $company->name);
    }

    public function test_browser_save_rejects_foreign_company_and_invalid_fields(): void
    {
        $user = User::factory()->create();
        $other = Company::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->actingAs($user)->postJson(route('contacts.business-cards.on-device.save'), [
            'create_company' => false,
            'company_id' => $other->id, 'first_name' => '', 'email' => 'invalid', 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'first_name', 'email']);
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_guest_cannot_save_a_scanned_contact(): void
    {
        $this->postJson(route('contacts.business-cards.on-device.save'), [
            'create_company' => true, 'new_company_name' => 'Test', 'first_name' => 'Test', 'status' => 'active',
        ])->assertUnauthorized();
        $this->assertDatabaseCount('contacts', 0);
    }
}
