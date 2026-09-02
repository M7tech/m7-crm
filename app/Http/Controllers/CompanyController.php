<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CompanyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Company::class);

        return view('companies.index', [
            'companies' => Company::query()->latest()->paginate(20),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        Company::create($request->validated());

        return to_route('companies.index')->with('status', 'Company added successfully.');
    }
}
