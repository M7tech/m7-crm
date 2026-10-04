<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'last_message_at', 'id'],
                'conversations_tenant_last_message_id_index',
            );
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'conversation_id', 'sent_at', 'id'],
                'messages_tenant_conversation_sent_id_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_tenant_conversation_sent_id_index');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_tenant_last_message_id_index');
        });
    }
};
