<?php

namespace App\Exceptions;

use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * A classified Graph API failure. Messages stored for employees never contain
 * tokens or raw provider payloads.
 */
class MetaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $kind,
        public readonly ?int $metaCode = null,
        public readonly ?int $metaSubcode = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function fromRequest(RequestException $exception): self
    {
        $error = $exception->response->json('error');
        $code = is_array($error) && is_numeric($error['code'] ?? null) ? (int) $error['code'] : null;
        $subcode = is_array($error) && is_numeric($error['error_subcode'] ?? null) ? (int) $error['error_subcode'] : null;

        return match (true) {
            $code === 190 => new self(__('The Facebook connection expired. A company admin must reconnect the Page.'), 'expired', $code, $subcode),
            in_array($code, [4, 17, 32, 613, 80001, 80006], true) || $exception->response->status() === 429 => new self(
                __('Facebook rate limit reached. Delivery will be retried automatically.'),
                'rate_limited',
                $code,
                $subcode,
                self::retryAfter($exception),
            ),
            $code === 10 && in_array($subcode, [2018278, 2534022], true) => new self(__('Messenger only allows replies within 24 hours of the customer\'s last message.'), 'window_closed', $code, $subcode),
            $code === 551 => new self(__('This person is not available to receive messages from the Page.'), 'recipient_unavailable', $code, $subcode),
            $code === 200 || $code === 10 => new self(__('Facebook refused the reply because the app lacks the required permission or review.'), 'permission', $code, $subcode),
            $exception->response->serverError() => new self(__('Facebook is temporarily unavailable. Delivery will be retried automatically.'), 'transient', $code, $subcode),
            default => new self(__('Facebook rejected the reply.').($code !== null ? ' (#'.$code.')' : ''), 'rejected', $code, $subcode),
        };
    }

    public function isRetryable(): bool
    {
        return in_array($this->kind, ['rate_limited', 'transient'], true);
    }

    private static function retryAfter(RequestException $exception): ?int
    {
        $usage = json_decode((string) $exception->response->header('X-Business-Use-Case-Usage'), true);
        $minutes = is_array($usage)
            ? collect($usage)->flatten(1)->pluck('estimated_time_to_regain_access')->filter(fn ($value) => is_numeric($value))->max()
            : null;

        return is_numeric($minutes) && $minutes > 0 ? (int) min(3600, $minutes * 60) : null;
    }
}
