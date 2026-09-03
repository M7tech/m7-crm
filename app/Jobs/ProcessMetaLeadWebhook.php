<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Services\MetaGraphClient;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessMetaLeadWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public int $eventId,
        public int $tenantId,
    ) {}

    public function handle(MetaGraphClient $client, CurrentTenant $currentTenant): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $currentTenant->set($tenant);
        $event = WebhookEvent::query()->with('integration.tenant')->findOrFail($this->eventId);

        if ($event->status === 'processed') {
            return;
        }

        try {
            $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1, 'error' => null]);
            $data = $client->lead($event->integration, $event->external_id);
            $this->storeLead($event, $data);
        } catch (Throwable $exception) {
            $event->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function storeLead(WebhookEvent $event, array $data): void
    {
        $fields = collect($data['field_data'] ?? [])->mapWithKeys(function (array $field): array {
            return [(string) ($field['name'] ?? '') => (string) (($field['values'][0] ?? ''))];
        });
        $fullName = trim((string) ($fields['full_name'] ?? ''));
        $firstName = trim((string) ($fields['first_name'] ?? ''));
        $lastName = trim((string) ($fields['last_name'] ?? ''));
        if ($firstName === '' && $fullName !== '') {
            [$firstName, $lastName] = array_pad(explode(' ', $fullName, 2), 2, '');
        }
        $firstName = $firstName !== '' ? $firstName : 'Meta lead';
        $email = filter_var($fields['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;
        $phone = trim((string) ($fields['phone_number'] ?? '')) ?: null;
        $integration = $event->integration;

        DB::transaction(function () use ($event, $data, $integration, $firstName, $lastName, $email, $phone): void {
            $locked = WebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($locked->status === 'processed') {
                return;
            }

            $contact = Contact::query()
                ->where('company_id', $integration->company_id)
                ->when($email, fn ($query) => $query->where('email', $email))
                ->when(! $email && $phone, fn ($query) => $query->where('phone', $phone))
                ->when(! $email && ! $phone, fn ($query) => $query->whereRaw('1 = 0'))
                ->first();
            $contact ??= Contact::create([
                'company_id' => $integration->company_id,
                'first_name' => $firstName,
                'last_name' => $lastName ?: null,
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',
                'notes' => 'Created from Meta Lead Ads.',
            ]);
            $lead = Lead::create([
                'company_id' => $integration->company_id,
                'contact_id' => $contact->id,
                'pipeline_id' => $integration->pipeline_id,
                'stage_id' => $integration->stage_id,
                'assigned_to_id' => $integration->assigned_to_id,
                'title' => 'Meta lead: '.$contact->full_name,
                'expected_value_minor' => 0,
                'currency' => 'IQD',
                'source' => 'Meta Lead Ads',
                'notes' => collect([$data['campaign_name'] ?? null, $data['ad_name'] ?? null])->filter()->implode(' · ') ?: null,
            ]);
            LeadActivity::create([
                'lead_id' => $lead->id,
                'actor_id' => null,
                'type' => 'created',
                'description' => 'Lead created from Meta Lead Ads.',
                'metadata' => ['provider' => 'meta_lead_ads', 'external_lead_id' => $event->external_id, 'webhook_event_id' => $event->id],
            ]);
            $locked->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        });
    }
}
