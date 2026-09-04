<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMetaLeadWebhook;
use App\Models\Integration;
use App\Models\WebhookEvent;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MetaWebhookController extends Controller
{
    public function verify(Request $request, string $integration, CurrentTenant $currentTenant): Response
    {
        $connection = $this->connection($integration, $currentTenant);
        // PHP normalizes dots in query-string keys to underscores. Meta sends
        // hub.mode, hub.verify_token, and hub.challenge, which therefore arrive
        // as hub_mode, hub_verify_token, and hub_challenge in production.
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $verifyToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $valid = $mode === 'subscribe'
            && is_string($verifyToken)
            && hash_equals((string) $connection->settings['verify_token'], $verifyToken);

        abort_unless($valid, 403, 'Invalid webhook verification token.');

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, string $integration, CurrentTenant $currentTenant): Response
    {
        $connection = $this->connection($integration, $currentTenant);
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $connection->credentials['app_secret']);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'Invalid webhook signature.');

        $currentTenant->set($connection->tenant);
        $payload = $request->json()->all();
        $entries = $payload['entry'] ?? [];
        if (! is_array($entries)) {
            return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if ((string) ($entry['id'] ?? '') !== (string) $connection->external_account_id) {
                continue;
            }

            $changes = $entry['changes'] ?? [];
            if (! is_array($changes)) {
                continue;
            }

            foreach ($changes as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? [];
                if (! is_array($value) || ($change['field'] ?? null) !== 'leadgen' || empty($value['leadgen_id'])) {
                    continue;
                }

                DB::transaction(function () use ($connection, $value): void {
                    $event = WebhookEvent::firstOrCreate([
                        'integration_id' => $connection->id,
                        'event_type' => 'leadgen',
                        'external_id' => (string) $value['leadgen_id'],
                    ], [
                        'provider' => 'meta_lead_ads',
                        'payload' => $value,
                        'status' => 'pending',
                    ]);

                    if ($event->wasRecentlyCreated) {
                        ProcessMetaLeadWebhook::dispatch($event->id, $event->tenant_id)->afterCommit();
                    }
                });
            }
        }

        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }

    private function connection(string $publicId, CurrentTenant $currentTenant): Integration
    {
        $currentTenant->allowGlobalAccess();

        return Integration::query()
            ->where('provider', 'meta_lead_ads')
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
