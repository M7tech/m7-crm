<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueWorkerHeartbeat;
use App\Models\SystemHealthCheck;
use Illuminate\Console\Command;

class RecordSystemHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Record scheduler activity and queue a worker heartbeat';

    public function handle(): int
    {
        SystemHealthCheck::record('scheduler', [
            'environment' => app()->environment(),
        ]);

        RecordQueueWorkerHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
