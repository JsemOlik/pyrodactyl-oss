<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('server_subdomains', function (Blueprint $table) {
            $table->unsignedInteger('proxy_port')->nullable()->after('record_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_subdomains', function (Blueprint $table) {
            $table->dropColumn('proxy_port');
        });
    }
};
