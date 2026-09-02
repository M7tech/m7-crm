<?php

namespace Tests\Feature\Contacts;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_and_import_valid_rows_with_an_audit_record(): void
    {
        $tenant = Tenant::factory()->create();
        Company::factory()->for($tenant)->create(['name' => 'Atlas Trading']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $file = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "company,first_name,last_name,email,phone,status\nAtlas Trading,Sara,Ahmed,sara@example.com,07500000000,active\nUnknown Co,Bad,Row,bad@example.com,,active",
        );

        $preview = $this->actingAs($admin)->post(route('contacts.import.preview'), ['csv_file' => $file]);

        $preview->assertOk()->assertSee('Ready')->assertSee('Company was not found');
        $import = ContactImport::query()->sole();

        $this->actingAs($admin)->post(route('contacts.import.store'), [
            'import_id' => $import->id,
            'token' => $preview->viewData('token'),
            'duplicate_strategy' => 'skip',
        ])->assertSessionHasNoErrors()->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('contacts', [
            'tenant_id' => $tenant->id,
            'first_name' => 'Sara',
            'email' => 'sara@example.com',
        ]);
        $this->assertDatabaseMissing('contacts', ['first_name' => 'Bad']);
        $this->assertDatabaseHas('contact_imports', [
            'id' => $import->id,
            'tenant_id' => $tenant->id,
            'status' => 'completed',
            'total_rows' => 2,
            'imported_rows' => 1,
            'skipped_rows' => 1,
        ]);
    }

    public function test_update_strategy_updates_a_duplicate_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create(['name' => 'Atlas Trading']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $contact = Contact::factory()->for($tenant)->for($company)->create([
            'first_name' => 'Old',
            'email' => 'same@example.com',
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "company,first_name,last_name,email,status\nAtlas Trading,New,Name,same@example.com,active",
        );

        $preview = $this->actingAs($admin)->post(route('contacts.import.preview'), ['csv_file' => $file]);
        $import = ContactImport::query()->sole();

        $this->actingAs($admin)->post(route('contacts.import.store'), [
            'import_id' => $import->id,
            'token' => $preview->viewData('token'),
            'duplicate_strategy' => 'update',
        ])->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'first_name' => 'New', 'last_name' => 'Name']);
        $this->assertDatabaseHas('contact_imports', ['id' => $import->id, 'updated_rows' => 1]);
    }

    public function test_import_does_not_match_a_company_from_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        Company::factory()->for($otherTenant)->create(['name' => 'Hidden Company']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $file = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "company,first_name,email\nHidden Company,Blocked,blocked@example.com",
        );

        $this->actingAs($admin)
            ->post(route('contacts.import.preview'), ['csv_file' => $file])
            ->assertOk()
            ->assertSee('Company was not found');

        $this->assertDatabaseMissing('contacts', ['email' => 'blocked@example.com']);
    }

    public function test_salesperson_cannot_access_contact_import(): void
    {
        $user = User::factory()->create(['role' => UserRole::Salesperson]);

        $this->actingAs($user)
            ->get(route('contacts.import.create'))
            ->assertForbidden();
    }

    public function test_csv_requires_the_expected_headers(): void
    {
        $admin = User::factory()->companyAdmin()->create();
        $file = UploadedFile::fake()->createWithContent('contacts.csv', "name,email\nSara,sara@example.com");

        $this->actingAs($admin)
            ->post(route('contacts.import.preview'), ['csv_file' => $file])
            ->assertSessionHasErrors('csv_file');
    }
}
