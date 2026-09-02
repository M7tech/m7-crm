<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, Response::HTTP_UNAUTHORIZED);

        if ($user->role === UserRole::SuperAdmin) {
            $this->currentTenant->allowGlobalAccess();

            return $next($request);
        }

        $tenant = $user->tenant;

        abort_unless(
            $tenant && $tenant->status === TenantStatus::Active,
            Response::HTTP_FORBIDDEN,
            'This company account is not active.',
        );

        $this->currentTenant->set($tenant);

        return $next($request);
    }
}
