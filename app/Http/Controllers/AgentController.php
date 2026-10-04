<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentStatusRequest;
use App\Models\Agent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AgentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Agent::class);

        return view('agents.index', [
            'agents' => Agent::query()->withCount(['leads', 'leads as won_leads_count' => fn ($query) => $query
                ->whereHas('stage', fn ($stage) => $stage->where('type', 'won'))])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        Agent::create([
            ...$request->validated(),
            'code' => $request->string('code')->trim()->value() ?: null,
            'status' => 'active',
        ]);

        return to_route('agents.index')->with('status', 'Agent added for sales attribution.');
    }

    public function updateStatus(UpdateAgentStatusRequest $request, int $agent): RedirectResponse
    {
        $agentModel = $request->agent();
        $agentModel->update(['status' => $request->validated('status')]);

        return to_route('agents.index')->with('status', $agentModel->name.' is now '.$agentModel->status.'.');
    }
}
