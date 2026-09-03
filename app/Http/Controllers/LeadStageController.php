<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveLeadRequest;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Services\LeadWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LeadStageController extends Controller
{
    public function update(MoveLeadRequest $request, int $lead, LeadWorkflow $workflow): JsonResponse|RedirectResponse
    {
        $leadModel = Lead::query()->findOrFail($lead);
        $stage = PipelineStage::query()->findOrFail($request->integer('stage_id'));
        $validated = $request->validated();
        $lossReason = isset($validated['loss_reason']) ? (string) $validated['loss_reason'] : null;
        $leadModel = $workflow->move($leadModel, $stage, $lossReason, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Lead moved to '.$stage->name.'.',
                'lead' => ['id' => $leadModel->id, 'stage_id' => $leadModel->stage_id],
            ]);
        }

        return back()->with('status', 'Lead stage updated.');
    }
}
