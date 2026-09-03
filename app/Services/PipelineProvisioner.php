<?php

namespace App\Services;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;

class PipelineProvisioner
{
    public function createDefault(Tenant $tenant): Pipeline
    {
        $pipeline = new Pipeline(['name' => 'Sales Pipeline', 'is_default' => true]);
        $pipeline->tenant_id = $tenant->id;
        $pipeline->save();

        foreach ([
            ['New', 'open', 'sky'],
            ['Qualified', 'open', 'violet'],
            ['Proposal', 'open', 'amber'],
            ['Won', 'won', 'emerald'],
            ['Lost', 'lost', 'red'],
        ] as $index => [$name, $type, $color]) {
            $stage = new PipelineStage([
                'pipeline_id' => $pipeline->id,
                'name' => $name,
                'position' => $index + 1,
                'type' => $type,
                'color' => $color,
            ]);
            $stage->tenant_id = $tenant->id;
            $stage->save();
        }

        return $pipeline;
    }
}
