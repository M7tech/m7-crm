<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'name']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('agent_id')
                ->nullable()
                ->after('assigned_to_id')
                ->constrained('agents')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'agent_id', 'created_at'], 'leads_tenant_agent_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_agent_created_index');
            $table->dropConstrainedForeignId('agent_id');
        });

        Schema::dropIfExists('agents');
    }
};
