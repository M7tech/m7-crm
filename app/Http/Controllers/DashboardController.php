<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Task;
use App\Services\PlanEntitlements;
use App\Services\WorkspaceOnboarding;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CurrentTenant $currentTenant,
        PlanEntitlements $plans,
        WorkspaceOnboarding $onboarding,
    ): View
    {
        $tenant = $currentTenant->tenant();
        $timezone = $tenant?->timezone ?? 'Asia/Baghdad';
        $startOfToday = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $endOfToday = CarbonImmutable::now($timezone)->endOfDay()->utc();
        $visibleTasks = Task::query()->visibleTo($request->user());

        return view('dashboard', [
            'tenant' => $tenant,
            'companyCount' => Company::query()->count(),
            'openLeadCount' => Lead::query()->whereHas('stage', fn ($query) => $query->where('type', 'open'))->count(),
            'dueTodayCount' => (clone $visibleTasks)->where('status', 'pending')->whereBetween('due_at', [$startOfToday, $endOfToday])->count(),
            'overdueTaskCount' => (clone $visibleTasks)->where('status', 'pending')->where('due_at', '<', now())->count(),
            'recentCompanies' => Company::query()->latest()->limit(5)->get(),
            'planName' => $tenant ? $plans->label($tenant) : null,
            'planUsage' => $tenant && $request->user()->role === UserRole::CompanyAdmin ? $plans->summary($tenant) : null,
            'onboarding' => $tenant && $request->user()->role === UserRole::CompanyAdmin ? $onboarding->summary($tenant) : null,
        ]);
    }
}
