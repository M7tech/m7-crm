<?php

namespace Tests\Feature\Contacts;

use App\Jobs\ProcessBusinessCardScan;
use App\Models\BusinessCardScan;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessCardOcr;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessCardScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_a_private_card_and_queue_multilingual_extraction(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('services.openai.api_key', 'test-key');
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

    public function test_worker_extracts_arabic_and_kurdish_contact_data_without_api_storage(): void
    {
        Storage::fake('local');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.business_card_model', 'gpt-5.6-luna');
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
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_business_card_1',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($extracted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
            ]),
        ]);

        (new ProcessBusinessCardScan($scan->id, $tenant->id))->handle(
            app(BusinessCardOcr::class),
            app(CurrentTenant::class),
        );

        $scan->refresh();
        $this->assertSame('completed', $scan->status);
        $this->assertSame('محمد', $scan->extracted_data['first_name']);
        $this->assertSame('بەڕێوەبەری فرۆشتن', $scan->extracted_data['job_title']);
        $this->assertSame('info@example.iq', $scan->extracted_data['email']);
        $this->assertSame('resp_business_card_1', $scan->provider_response_id);
        Http::assertSent(function (Request $request): bool {
            $imageUrl = data_get($request->data(), 'input.0.content.1.image_url');

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['store'] === false
                && $request['model'] === 'gpt-5.6-luna'
                && is_string($imageUrl)
                && str_starts_with($imageUrl, 'data:image/jpeg;base64,');
        });
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
