<?php

namespace App\Console\Commands;

use App\Models\BusinessCardScan;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredBusinessCardScans extends Command
{
    protected $signature = 'business-cards:purge-expired';

    protected $description = 'Delete expired business-card images and scan records';

    public function handle(CurrentTenant $currentTenant): int
    {
        $currentTenant->allowGlobalAccess();

        BusinessCardScan::query()
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($scans): void {
                foreach ($scans as $scan) {
                    if (filled($scan->image_path)) {
                        Storage::disk($scan->disk)->delete($scan->image_path);
                    }

                    $scan->delete();
                }
            });

        return self::SUCCESS;
    }
}
