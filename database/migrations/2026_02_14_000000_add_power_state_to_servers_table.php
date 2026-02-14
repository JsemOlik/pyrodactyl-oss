<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'power_state')) {
                $table->string('power_state', 32)
                    ->nullable()
                    ->index()
                    ->comment('Cached power state from wings/elytra (running, offline, stopping, error, etc.)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'power_state')) {
                $table->dropColumn('power_state');
            }
        });
    }
};
