<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\Tenant;
use App\Services\PlanEntitlements;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Company::class);

        return view('companies.index', [
            'companies' => Company::query()->latest()->paginate(20),
        ]);
    }

    public function store(StoreCompanyRequest $request, PlanEntitlements $plans): RedirectResponse
    {
        DB::transaction(function () use ($request, $plans): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($request->user()->tenant_id);
            $plans->assertCapacity($tenant, 'companies', 'name');
            Company::create($request->validated());
        });

        return to_route('companies.index')->with('status', 'Company added successfully.');
    }
}
