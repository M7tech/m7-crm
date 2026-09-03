<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveLeadRequest;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Services\LeadWorkflow;
use Illuminate\Http\RedirectResponse;

class LeadStageController extends Controller
{
    public function update(MoveLeadRequest $request, int $lead, LeadWorkflow $workflow): RedirectResponse
    {
        $leadModel = Lead::query()->findOrFail($lead);
        $stage = PipelineStage::query()->findOrFail($request->integer('stage_id'));
        $workflow->move($leadModel, $stage, $request->validated('loss_reason'), $request->user());

        return back()->with('status', 'Lead stage updated.');
    }
}
