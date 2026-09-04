<?php

namespace App\Jobs;

use App\Models\SystemHealthCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordQueueWorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        SystemHealthCheck::record('queue_worker', [
            'connection' => config('queue.default'),
        ]);
    }
}
