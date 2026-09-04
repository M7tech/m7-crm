<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\AutomationRun;
use App\Models\SystemHealthCheck;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class OperationsMonitor
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'health' => [
                $this->connectionHealth('Database', fn () => DB::select('select 1')),
                $this->connectionHealth('Redis', fn () => Redis::connection()->command('ping')),
                $this->heartbeatHealth('Scheduler', 'scheduler'),
                $this->heartbeatHealth('Queue worker', 'queue_worker'),
            ],
            'counts' => [
                'activeTenants' => Tenant::query()->where('status', TenantStatus::Active)->count(),
                'suspendedTenants' => Tenant::query()->where('status', TenantStatus::Suspended)->count(),
                'users' => User::query()->whereNotNull('tenant_id')->count(),
                'queuedJobs' => $this->queueDepth(),
                'failedJobs' => DB::table('failed_jobs')->count(),
                'failedWebhooks' => WebhookEvent::query()->where('status', 'failed')->count(),
                'failedAutomations' => AutomationRun::query()->where('status', 'failed')->count(),
            ],
            'failedJobs' => DB::table('failed_jobs')
                ->latest('failed_at')
                ->limit(10)
                ->get()
                ->map(fn (object $job): array => [
                    'name' => $this->jobName((string) $job->payload),
                    'queue' => (string) $job->connection.' / '.(string) $job->queue,
                    'error' => $this->errorSummary((string) $job->exception),
                    'failed_at' => $job->failed_at,
                ]),
            'failedWebhooks' => WebhookEvent::query()
                ->with(['tenant:id,name', 'integration:id,name'])
                ->where('status', 'failed')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (WebhookEvent $event): array => [
                    'tenant' => $event->tenant?->name ?? 'Unknown workspace',
                    'source' => $event->integration?->name ?? $event->provider,
                    'type' => $event->event_type,
                    'error' => $this->errorSummary($event->error),
                    'failed_at' => $event->updated_at,
                ]),
            'failedAutomations' => AutomationRun::query()
                ->with(['tenant:id,name', 'rule:id,name'])
                ->where('status', 'failed')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AutomationRun $run): array => [
                    'tenant' => $run->tenant?->name ?? 'Unknown workspace',
                    'rule' => $run->rule?->name ?? 'Deleted rule',
                    'error' => $this->errorSummary($run->error),
                    'failed_at' => $run->completed_at ?? $run->updated_at,
                ]),
        ];
    }

    /**
     * @param  callable(): mixed  $probe
     * @return array<string, mixed>
     */
    private function connectionHealth(string $name, callable $probe): array
    {
        $startedAt = hrtime(true);

        try {
            $probe();

            return [
                'name' => $name,
                'status' => 'healthy',
                'detail' => number_format((hrtime(true) - $startedAt) / 1_000_000).' ms response',
                'checked_at' => now(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'name' => $name,
                'status' => 'unavailable',
                'detail' => 'Connection failed',
                'checked_at' => now(),
            ];
        }
    }

    /** @return array<string, mixed> */
    private function heartbeatHealth(string $name, string $key): array
    {
        $heartbeat = SystemHealthCheck::query()->where('key', $key)->first();

        if (! $heartbeat) {
            return [
                'name' => $name,
                'status' => 'missing',
                'detail' => 'No heartbeat recorded yet',
                'checked_at' => null,
            ];
        }

        $healthy = $heartbeat->checked_at->greaterThanOrEqualTo(now()->subMinutes(3));

        return [
            'name' => $name,
            'status' => $healthy ? 'healthy' : 'stale',
            'detail' => $healthy ? 'Reporting normally' : 'Heartbeat is more than 3 minutes old',
            'checked_at' => $heartbeat->checked_at,
        ];
    }

    private function queueDepth(): ?int
    {
        try {
            return Queue::size();
        } catch (Throwable) {
            return null;
        }
    }

    private function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) && is_string($decoded['displayName'] ?? null)
            ? $decoded['displayName']
            : 'Unknown job';
    }

    private function errorSummary(?string $error): string
    {
        if (blank($error)) {
            return 'No error detail recorded';
        }

        return Str::limit(Str::before(str_replace("\r", '', $error), "\n"), 220);
    }
}
