<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('organization_name', 160)->nullable()->after('job_title');
            $table->string('city', 100)->nullable()->after('phone');
            $table->string('category', 100)->nullable()->after('city');
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'category']);
            $table->dropIndex(['tenant_id', 'city']);
            $table->dropColumn(['organization_name', 'city', 'category']);
        });
    }
};
