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
        Schema::create('database_services', function (Blueprint $table) {
            $table->increments('id');
            $table->string('external_id', 191)->nullable();
            $table->char('uuid', 36)->unique();
            $table->char('uuidShort', 8)->unique();
            $table->unsignedInteger('node_id');
            $table->string('name', 191);
            $table->text('description');
            $table->string('status', 191)->nullable();
            $table->boolean('skip_scripts')->default(false);
            $table->unsignedInteger('owner_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('database_type', 50)->default('mysql'); // mysql, mariadb, postgresql, mongodb
            $table->unsignedInteger('memory');
            $table->unsignedInteger('overhead_memory')->default(0);
            $table->integer('swap')->default(0);
            $table->unsignedInteger('disk');
            $table->unsignedInteger('io')->default(500);
            $table->unsignedInteger('cpu');
            $table->string('threads', 191)->nullable();
            $table->boolean('oom_disabled')->default(true);
            $table->boolean('exclude_from_resource_calculation')->default(false);
            $table->unsignedInteger('allocation_id');
            $table->unsignedInteger('nest_id');
            $table->unsignedInteger('egg_id');
            $table->text('startup');
            $table->string('image', 191);
            $table->unsignedInteger('backup_limit')->default(0);
            $table->unsignedInteger('backup_storage_limit')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            $table->foreign('allocation_id')->references('id')->on('allocations')->onDelete('cascade');
            $table->foreign('nest_id')->references('id')->on('nests')->onDelete('cascade');
            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('cascade');

            $table->unique('allocation_id');
            $table->unique('external_id');
            $table->index('node_id');
            $table->index('owner_id');
            $table->index('subscription_id');
            $table->index('nest_id');
            $table->index('egg_id');
            $table->index('database_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_services');
    }
};

