<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePipelineRequest;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PipelineController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Pipeline::class);

        return view('pipelines.index', ['pipelines' => Pipeline::query()->with('stages')->orderByDesc('is_default')->get()]);
    }

    public function store(StorePipelineRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $pipeline = Pipeline::create(['name' => $request->validated('name'), 'is_default' => false]);
            $position = 1;

            foreach ($request->stageNames() as $name) {
                PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => $name, 'position' => $position++, 'type' => 'open', 'color' => 'zinc']);
            }
            PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Won', 'position' => $position++, 'type' => 'won', 'color' => 'emerald']);
            PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Lost', 'position' => $position, 'type' => 'lost', 'color' => 'red']);
        });

        return to_route('pipelines.index')->with('status', 'Pipeline created successfully.');
    }
}
