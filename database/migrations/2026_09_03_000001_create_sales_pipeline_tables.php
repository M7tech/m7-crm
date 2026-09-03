<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_default']);
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->string('type')->default('open');
            $table->string('color', 20)->default('zinc');
            $table->timestamps();
            $table->unique(['pipeline_id', 'position']);
            $table->index(['tenant_id', 'pipeline_id']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('pipeline_stages')->restrictOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->bigInteger('expected_value_minor')->default(0);
            $table->char('currency', 3)->default('IQD');
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->string('loss_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'pipeline_id', 'stage_id']);
            $table->index(['tenant_id', 'assigned_to_id']);
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'lead_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $this->createDefaultPipeline((int) $tenantId);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }

    private function createDefaultPipeline(int $tenantId): void
    {
        $now = now();
        $pipelineId = DB::table('pipelines')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Sales Pipeline',
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('pipeline_stages')->insert([
            ['tenant_id' => $tenantId, 'pipeline_id' => $pipelineId, 'name' => 'New', 'position' => 1, 'type' => 'open', 'color' => 'sky', 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'pipeline_id' => $pipelineId, 'name' => 'Qualified', 'position' => 2, 'type' => 'open', 'color' => 'violet', 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'pipeline_id' => $pipelineId, 'name' => 'Proposal', 'position' => 3, 'type' => 'open', 'color' => 'amber', 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'pipeline_id' => $pipelineId, 'name' => 'Won', 'position' => 4, 'type' => 'won', 'color' => 'emerald', 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'pipeline_id' => $pipelineId, 'name' => 'Lost', 'position' => 5, 'type' => 'lost', 'color' => 'red', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
