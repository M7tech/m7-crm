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
}
