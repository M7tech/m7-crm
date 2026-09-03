<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\LeadWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);
        $pipelines = Pipeline::query()->orderByDesc('is_default')->orderBy('name')->get();
        $pipeline = Pipeline::query()
            ->when($request->integer('pipeline'), fn ($query, int $id) => $query->whereKey($id))
            ->orderByDesc('is_default')
            ->firstOrFail();
        $pipeline->load(['stages.leads' => fn ($query) => $query->with(['company', 'assignedTo'])->latest()]);

        return view('leads.index', compact('pipelines', 'pipeline'));
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('leads.create', $this->formData());
    }

    public function store(StoreLeadRequest $request, LeadWorkflow $workflow): RedirectResponse
    {
        $lead = $workflow->create($request->validated(), $request->user());

        return to_route('leads.show', $lead)->with('status', 'Lead created successfully.');
    }

    public function show(int $lead): View
    {
        $leadModel = Lead::query()->with(['company', 'contact', 'pipeline', 'stage', 'assignedTo', 'activities.actor'])->findOrFail($lead);
        $this->authorize('view', $leadModel);

        return view('leads.show', ['lead' => $leadModel]);
    }

    public function edit(int $lead): View
    {
        $leadModel = Lead::query()->findOrFail($lead);
        $this->authorize('update', $leadModel);

        return view('leads.edit', ['lead' => $leadModel, ...$this->formData()]);
    }

    public function update(UpdateLeadRequest $request, int $lead, LeadWorkflow $workflow): RedirectResponse
    {
        $leadModel = Lead::query()->findOrFail($lead);
        $workflow->update($leadModel, $request->validated(), $request->user());

        return to_route('leads.show', $leadModel)->with('status', 'Lead updated successfully.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $tenantId = request()->user()->tenant_id;

        return [
            'companies' => Company::query()->orderBy('name')->get(),
            'contacts' => Contact::query()->with('company')->orderBy('first_name')->get(),
            'pipelines' => Pipeline::query()->with('stages')->orderByDesc('is_default')->get(),
            'members' => User::query()->where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(),
        ];
    }
}
