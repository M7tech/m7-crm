<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assigned_to_id')->nullable()->after('contact_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_id');
            $table->timestamp('last_inbound_at')->nullable()->after('last_message_at');
            $table->timestamp('resolved_at')->nullable()->after('last_inbound_at');
            $table->index(['tenant_id', 'assigned_to_id', 'status', 'last_message_at']);
            $table->index(['tenant_id', 'integration_id', 'last_message_at']);
            $table->index(['tenant_id', 'channel', 'last_message_at']);
        });

        DB::table('conversations')->update([
            'last_inbound_at' => DB::raw("(select max(messages.sent_at) from messages where messages.conversation_id = conversations.id and messages.direction = 'inbound')"),
        ]);

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sent_by_id')->nullable()->after('conversation_id')->constrained('users')->nullOnDelete();
            $table->string('error', 500)->nullable()->after('status');
        });

        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at');
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'conversation_id']);
        });

        Schema::create('conversation_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'conversation_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'company_id']);
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'conversation_id']);
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->foreignId('connected_by_id')->nullable()->after('connected_at')->constrained('users')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable()->after('connected_by_id');
            $table->foreignId('disconnected_by_id')->nullable()->after('disconnected_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disconnected_by_id');
            $table->dropColumn('disconnected_at');
            $table->dropConstrainedForeignId('connected_by_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'company_id']);
            $table->dropIndex(['tenant_id', 'contact_id']);
            $table->dropIndex(['tenant_id', 'conversation_id']);
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('conversation_activities');
        Schema::dropIfExists('conversation_reads');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by_id');
            $table->dropColumn('error');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'assigned_to_id', 'status', 'last_message_at']);
            $table->dropIndex(['tenant_id', 'integration_id', 'last_message_at']);
            $table->dropIndex(['tenant_id', 'channel', 'last_message_at']);
            $table->dropConstrainedForeignId('assigned_to_id');
            $table->dropColumn(['assigned_at', 'last_inbound_at', 'resolved_at']);
        });
    }
};
