<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\Company;
use App\Models\Integration;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlanEntitlements
{
    /** @var array<string, string> */
    private const LABELS = [
        'members' => 'Team members',
        'companies' => 'Companies',
        'automation_rules' => 'Automation rules',
        'meta_connections' => 'Meta Page connections',
    ];

    public function label(Tenant $tenant): string
    {
        return (string) data_get($this->definition($tenant), 'label', ucfirst($tenant->plan));
    }

    public function limit(Tenant $tenant, string $resource): ?int
    {
        $limit = data_get($this->definition($tenant), 'limits.'.$resource);

        return is_int($limit) ? $limit : null;
    }

    public function usage(Tenant $tenant, string $resource, ?string $ignoredInvitationEmail = null): int
    {
        return match ($resource) {
            'members' => $this->memberUsage($tenant, $ignoredInvitationEmail),
            'companies' => Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'automation_rules' => AutomationRule::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->count(),
            'meta_connections' => Integration::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('provider', 'meta_lead_ads')
                ->count(),
            default => 0,
        };
    }

    public function hasCapacity(Tenant $tenant, string $resource, ?string $ignoredInvitationEmail = null): bool
    {
        $limit = $this->limit($tenant, $resource);

        return $limit === null || $this->usage($tenant, $resource, $ignoredInvitationEmail) < $limit;
    }

    public function limitMessage(Tenant $tenant, string $resource): string
    {
        $label = self::LABELS[$resource] ?? ucfirst(str_replace('_', ' ', $resource));
        $limit = $this->limit($tenant, $resource);

        return $limit === null
            ? ''
            : $label.' reached the '.$this->label($tenant).' plan limit of '.$limit.'.';
    }

    /** @throws ValidationException */
    public function assertCapacity(
        Tenant $tenant,
        string $resource,
        string $field,
        ?string $ignoredInvitationEmail = null,
    ): void
    {
        if (! $this->hasCapacity($tenant, $resource, $ignoredInvitationEmail)) {
            throw ValidationException::withMessages([$field => $this->limitMessage($tenant, $resource)]);
        }
    }

    /** @return array<int, array{key: string, label: string, used: int, limit: ?int}> */
    public function summary(Tenant $tenant): array
    {
        return collect(self::LABELS)->map(fn (string $label, string $resource): array => [
            'key' => $resource,
            'label' => $label,
            'used' => $this->usage($tenant, $resource),
            'limit' => $this->limit($tenant, $resource),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function definition(Tenant $tenant): array
    {
        $plans = config('plans.plans', []);
        $fallback = (string) config('plans.default', 'starter');
        $definition = is_array($plans) ? ($plans[$tenant->plan] ?? $plans[$fallback] ?? []) : [];

        return is_array($definition) ? $definition : [];
    }

    private function memberUsage(Tenant $tenant, ?string $ignoredInvitationEmail): int
    {
        $members = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->count();
        $invitations = Invitation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->when($ignoredInvitationEmail, fn ($query, string $email) => $query->where('email', '!=', $email))
            ->count();

        return $members + $invitations;
    }
}
