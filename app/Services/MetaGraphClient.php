<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class MetaGraphClient
{
    /** @return array<string, mixed> */
    public function exchangeCode(Integration $integration, string $code, string $redirectUri): array
    {
        $result = $this->graph($integration)->get('oauth/access_token', [
            'client_id' => $integration->credentials['app_id'],
            'client_secret' => $integration->credentials['app_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ])->throw()->json();

        if (! is_array($result) || ! is_string($result['access_token'] ?? null)) {
            throw new UnexpectedValueException('Meta did not return an authorization access token.');
        }

        return $result;
    }

    public function longLivedToken(Integration $integration, string $token): string
    {
        $result = $this->graph($integration)->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $integration->credentials['app_id'],
            'client_secret' => $integration->credentials['app_secret'],
            'fb_exchange_token' => $token,
        ])->throw()->json();

        if (! is_array($result) || ! is_string($result['access_token'] ?? null)) {
            throw new UnexpectedValueException('Meta did not return a long-lived access token.');
        }

        return $result['access_token'];
    }

    /** @return array<int, array<string, mixed>> */
    public function pages(Integration $integration, string $userToken): array
    {
        $pages = $this->graph($integration)->withToken($userToken)->get('me/accounts', [
            'fields' => 'id,name,access_token,tasks',
            'limit' => 100,
        ])->throw()->json('data', []);

        if (! is_array($pages)) {
            throw new UnexpectedValueException('Meta did not return a Page list.');
        }

        return array_values(array_filter(
            $pages,
            fn (mixed $page): bool => is_array($page)
                && is_string($page['id'] ?? null)
                && is_string($page['name'] ?? null)
                && is_string($page['access_token'] ?? null),
        ));
    }

    public function subscribePage(Integration $integration, string $pageId, string $pageToken): bool
    {
        try {
            $this->graph($integration)
                ->asForm()
                ->withToken($pageToken)
                ->post($pageId.'/subscribed_apps', ['subscribed_fields' => 'leadgen,messages'])
                ->throw();

            return true;
        } catch (RequestException) {
            $this->graph($integration)
                ->asForm()
                ->withToken($pageToken)
                ->post($pageId.'/subscribed_apps', ['subscribed_fields' => 'leadgen'])
                ->throw();

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function lead(Integration $integration, string $leadId): array
    {
        $lead = $this->graph($integration)->withToken($integration->credentials['page_access_token'])->get($leadId, [
            'fields' => 'id,created_time,form_id,ad_id,ad_name,campaign_id,campaign_name,field_data',
        ])->throw()->json();

        if (! is_array($lead)) {
            throw new UnexpectedValueException('Meta did not return lead data.');
        }

        return $lead;
    }

    public function sendMessage(Integration $integration, string $recipientId, string $body): string
    {
        $result = $this->graph($integration)
            ->withToken((string) $integration->credentials['page_access_token'])
            ->post((string) $integration->external_account_id.'/messages', [
                'recipient' => ['id' => $recipientId],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $body],
            ])->throw()->json();

        if (! is_array($result) || ! is_string($result['message_id'] ?? null)) {
            throw new UnexpectedValueException('Meta did not return a Messenger message ID.');
        }

        return $result['message_id'];
    }

    private function graph(Integration $integration): PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.$integration->settings['graph_version'])
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500);
    }
}
