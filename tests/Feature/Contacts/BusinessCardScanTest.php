<?php

namespace Tests\Feature\Contacts;

use App\Jobs\ProcessBusinessCardScan;
use App\Models\BusinessCardScan;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessCardOcr;
use App\Services\BusinessCardTextParser;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BusinessCardScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_a_private_card_and_queue_multilingual_extraction(): void
    {
        Storage::fake('local');
        Queue::fake();
        $ocr = Mockery::mock(BusinessCardOcr::class);
        $ocr->shouldReceive('isAvailable')->once()->andReturnTrue();
        $this->app->instance(BusinessCardOcr::class, $ocr);
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $response = $this->actingAs($user)->post(route('contacts.business-cards.store'), [
            'card_image' => UploadedFile::fake()->image('بطاقة-business-card.jpg', 1200, 700),
        ]);

        $scan = BusinessCardScan::withoutGlobalScopes()->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('contacts.business-cards.show', $scan));
        $this->assertSame($tenant->id, $scan->tenant_id);
        $this->assertSame('queued', $scan->status);
        Storage::disk('local')->assertExists($scan->image_path);
        Queue::assertPushed(ProcessBusinessCardScan::class, fn (ProcessBusinessCardScan $job): bool => $job->scanId === $scan->id
            && $job->tenantId === $tenant->id);
    }

    public function test_card_upload_rejects_unsupported_files_and_requires_server_configuration(): void
    {
        Storage::fake('local');
        $ocr = Mockery::mock(BusinessCardOcr::class);
        $ocr->shouldReceive('isAvailable')->once()->andReturnFalse();
        $this->app->instance(BusinessCardOcr::class, $ocr);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.business-cards.store'), [
                'card_image' => UploadedFile::fake()->create('card.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('card_image');

        $this->actingAs($user)
            ->post(route('contacts.business-cards.store'), [
                'card_image' => UploadedFile::fake()->image('card.jpg'),
            ])
            ->assertSessionHasErrors('card_image');

        $this->assertDatabaseCount('business_card_scans', 0);
    }

    public function test_worker_stores_local_multilingual_ocr_result(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $scan = BusinessCardScan::factory()->for($tenant)->create([
            'uploaded_by_id' => $user->id,
            'image_path' => 'business-card-scans/'.$tenant->id.'/card.jpg',
        ]);
        Storage::disk('local')->put($scan->image_path, 'fake-image-bytes');
        $extracted = [
            'first_name' => 'محمد',
            'last_name' => 'شاكر',
            'job_title' => 'بەڕێوەبەری فرۆشتن',
            'company_name' => 'کۆمپانیای هەولێر',
            'email' => 'INFO@EXAMPLE.IQ',
            'phone' => '+964 750 000 0000',
            'website' => 'example.iq',
            'address' => 'هەولێر، عێراق',
            'notes' => null,
            'detected_languages' => ['Arabic', 'Kurdish Sorani'],
        ];
        $ocr = Mockery::mock(BusinessCardOcr::class);
        $ocr->shouldReceive('extract')
            ->once()
            ->with('fake-image-bytes', 'image/jpeg')
            ->andReturn([
                'data' => $extracted,
                'model' => 'tesseract-local:eng+ara+lat',
                'response_id' => null,
            ]);

        (new ProcessBusinessCardScan($scan->id, $tenant->id))->handle(
            $ocr,
            app(CurrentTenant::class),
        );

        $scan->refresh();
        $this->assertSame('completed', $scan->status);
        $this->assertSame('محمد', $scan->extracted_data['first_name']);
        $this->assertSame('بەڕێوەبەری فرۆشتن', $scan->extracted_data['job_title']);
        $this->assertSame('INFO@EXAMPLE.IQ', $scan->extracted_data['email']);
        $this->assertSame('tesseract-local:eng+ara+lat', $scan->provider_model);
        $this->assertNull($scan->provider_response_id);
    }

    public function test_local_parser_extracts_reviewable_contact_fields_from_mixed_script_text(): void
    {
        $parsed = app(BusinessCardTextParser::class)->parse(<<<'CARD'
محمد شاكر
بەڕێوەبەری فرۆشتن
کۆمپانیای هەولێر
INFO@EXAMPLE.IQ
+964 750 000 0000
example.iq
هەولێر، عێراق
CARD);

        $this->assertSame('محمد', $parsed['first_name']);
        $this->assertSame('شاكر', $parsed['last_name']);
        $this->assertSame('بەڕێوەبەری فرۆشتن', $parsed['job_title']);
        $this->assertSame('کۆمپانیای هەولێر', $parsed['company_name']);
        $this->assertSame('info@example.iq', $parsed['email']);
        $this->assertSame('+964 750 000 0000', $parsed['phone']);
        $this->assertSame('example.iq', $parsed['website']);
        $this->assertSame('هەولێر، عێراق', $parsed['address']);
        $this->assertContains('Kurdish Sorani', $parsed['detected_languages']);
        $this->assertStringContainsString('OCR text:', $parsed['notes']);

        $kurmanji = app(BusinessCardTextParser::class)->parse(<<<'CARD'
Baran Ahmed
Rêveberê Firotanê
Kurd Tech Company
baran@example.com
+964 751 000 0000
Erbil, Iraq
CARD);

        $this->assertSame('Baran', $kurmanji['first_name']);
        $this->assertSame('Ahmed', $kurmanji['last_name']);
        $this->assertSame('Rêveberê Firotanê', $kurmanji['job_title']);
        $this->assertSame('Kurd Tech Company', $kurmanji['company_name']);
        $this->assertContains('Kurdish Kurmanji', $kurmanji['detected_languages']);
    }

    public function test_user_reviews_and_saves_extracted_fields_then_image_is_deleted(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $user = User::factory()->for($tenant)->create();
        $scan = BusinessCardScan::factory()->for($tenant)->create([
            'uploaded_by_id' => $user->id,
            'status' => 'completed',
            'image_path' => 'business-card-scans/'.$tenant->id.'/completed.jpg',
            'extracted_data' => ['first_name' => 'هێمن', 'detected_languages' => ['Kurdish Sorani']],
            'processed_at' => now(),
        ]);
        Storage::disk('local')->put($scan->image_path, 'private-card');

        $response = $this->actingAs($user)->post(route('contacts.business-cards.save', $scan), [
            'tenant_id' => Tenant::factory()->create()->id,
            'company_id' => $company->id,
            'first_name' => 'هێمن',
            'last_name' => 'کەریم',
            'job_title' => 'Sales manager',
            'email' => 'hemin@example.iq',
            'phone' => '+9647500000000',
            'status' => 'active',
            'notes' => 'Reviewed from card.',
        ]);

        $contact = Contact::withoutGlobalScopes()->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('contacts.show', $contact));
        $this->assertSame($tenant->id, $contact->tenant_id);
        $this->assertSame($company->id, $contact->company_id);
        $scan->refresh();
        $this->assertSame('saved', $scan->status);
        $this->assertSame($contact->id, $scan->contact_id);
        $this->assertNull($scan->extracted_data);
        $this->assertNull($scan->image_path);
        Storage::disk('local')->assertMissing('business-card-scans/'.$tenant->id.'/completed.jpg');
    }

    public function test_scan_images_and_results_are_isolated_by_tenant(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $otherUser = User::factory()->for($otherTenant)->create();
        $scan = BusinessCardScan::factory()->for($otherTenant)->create([
            'uploaded_by_id' => $otherUser->id,
            'status' => 'completed',
            'extracted_data' => ['first_name' => 'Hidden', 'detected_languages' => ['English']],
        ]);
        Storage::disk('local')->put($scan->image_path, 'secret-card');

        $this->actingAs($user)
            ->get(route('contacts.business-cards.show', $scan))
            ->assertNotFound();
        $this->actingAs($user)
            ->get(route('contacts.business-cards.image', $scan))
            ->assertNotFound();
    }
}
