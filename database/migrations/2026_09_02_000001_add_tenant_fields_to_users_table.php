<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('role')->default('salesperson')->after('password')->index();
            $table->string('status')->default('active')->after('role')->index();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['role', 'status']);
        });
    }
};
