<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationRuleRequest;
use App\Http\Requests\UpdateAutomationRuleStatusRequest;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Services\PlanEntitlements;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AutomationRuleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AutomationRule::class);

        return view('automations.index', [
            'rules' => AutomationRule::query()->with(['stage.pipeline', 'createdBy'])->latest()->get(),
            'pipelines' => Pipeline::query()->with('stages')->orderByDesc('is_default')->orderBy('name')->get(),
            'runs' => AutomationRun::query()->with(['rule', 'lead', 'task'])->latest()->limit(25)->get(),
        ]);
    }

    public function store(StoreAutomationRuleRequest $request, PlanEntitlements $plans): RedirectResponse
    {
        DB::transaction(function () use ($request, $plans): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($request->user()->tenant_id);
            $plans->assertCapacity($tenant, 'automation_rules', 'name');
            AutomationRule::create([
                ...$request->validated(),
                'created_by_id' => $request->user()->id,
                'trigger_type' => 'lead_entered_stage',
                'action_type' => 'create_task',
                'is_active' => true,
            ]);
        });

        return to_route('automations.index')->with('status', 'Automation rule created.');
    }

    public function updateStatus(UpdateAutomationRuleStatusRequest $request, int $automationRule): RedirectResponse
    {
        $rule = $request->automationRule();
        $rule->update(['is_active' => $request->boolean('is_active')]);

        return to_route('automations.index')->with('status', $rule->name.' is now '.($rule->is_active ? 'active.' : 'paused.'));
    }

    public function destroy(int $automationRule): RedirectResponse
    {
        $rule = AutomationRule::query()->findOrFail($automationRule);
        $this->authorize('delete', $rule);
        $name = $rule->name;
        $rule->delete();

        return to_route('automations.index')->with('status', $name.' was deleted. Existing run history was preserved.');
    }
}
