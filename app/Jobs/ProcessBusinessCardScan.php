<?php

namespace App\Jobs;

use App\Models\BusinessCardScan;
use App\Models\Tenant;
use App\Services\BusinessCardOcr;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessBusinessCardScan implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 300];

    public int $uniqueFor = 600;

    public function __construct(
        public int $scanId,
        public int $tenantId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->scanId;
    }

    public function handle(BusinessCardOcr $ocr, CurrentTenant $currentTenant): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $currentTenant->set($tenant);
        $scan = BusinessCardScan::query()->find($this->scanId);
        if (! $scan) {
            return;
        }

        if (in_array($scan->status, ['completed', 'saved'], true)) {
            return;
        }

        $scan->update([
            'status' => 'processing',
            'attempts' => $scan->attempts + 1,
            'error' => null,
        ]);

        if (blank($scan->image_path) || ! Storage::disk($scan->disk)->exists($scan->image_path)) {
            throw new RuntimeException('The private business-card image is no longer available.');
        }

        $result = $ocr->extract(
            Storage::disk($scan->disk)->get($scan->image_path),
            $scan->mime_type,
        );

        $scan->update([
            'status' => 'completed',
            'extracted_data' => $result['data'],
            'provider_model' => $result['model'],
            'provider_response_id' => $result['response_id'],
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant) {
            return;
        }

        app(CurrentTenant::class)->set($tenant);
        BusinessCardScan::query()->whereKey($this->scanId)->update([
            'status' => 'failed',
            'error' => mb_substr($exception?->getMessage() ?? 'The scanner failed.', 0, 1000),
        ]);
    }
}
