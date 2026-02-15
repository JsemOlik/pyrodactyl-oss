<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('server_id');
            $table->timestamp('timestamp');
            $table->float('cpu', 5, 2);
            $table->unsignedBigInteger('memory_bytes');
            $table->unsignedBigInteger('network_rx_bytes');
            $table->unsignedBigInteger('network_tx_bytes');

            $table->index(['server_id', 'timestamp']);
            $table->foreign('server_id')
                ->references('id')
                ->on('servers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
