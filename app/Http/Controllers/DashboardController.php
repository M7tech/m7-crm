<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\CurrentTenant;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->tenant();

        return view('dashboard', [
            'tenant' => $tenant,
            'companyCount' => Company::query()->count(),
            'teamCount' => $tenant?->users()->count() ?? 0,
            'recentCompanies' => Company::query()->latest()->limit(5)->get(),
        ]);
    }
}
