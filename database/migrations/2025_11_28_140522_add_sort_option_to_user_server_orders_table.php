<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_server_orders', function (Blueprint $table) {
            $table->string('sort_option')->default('default')->after('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_server_orders', function (Blueprint $table) {
            $table->dropColumn('sort_option');
        });
    }
};
