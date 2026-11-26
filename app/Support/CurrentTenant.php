<?php

namespace App\Support;

use App\Models\Tenant;

class CurrentTenant
{
    private ?Tenant $tenant = null;

    private bool $globalAccess = false;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->globalAccess = false;
    }

    public function allowGlobalAccess(): void
    {
        $this->tenant = null;
        $this->globalAccess = true;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function hasGlobalAccess(): bool
    {
        return $this->globalAccess;
    }

    /**
     * Run a callback with its own tenant context and then restore the
     * caller's context. Queued jobs set the tenant they belong to; when a job
     * runs in-process (sync queue or dispatchSync) this keeps its tenant from
     * leaking into the request or command that dispatched it.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function isolate(callable $callback): mixed
    {
        $tenant = $this->tenant;
        $globalAccess = $this->globalAccess;

        try {
            return $callback();
        } finally {
            $this->tenant = $tenant;
            $this->globalAccess = $globalAccess;
        }
    }
}
