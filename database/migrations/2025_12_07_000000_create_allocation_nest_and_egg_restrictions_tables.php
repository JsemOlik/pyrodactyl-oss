<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAllocationNestAndEggRestrictionsTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('allocation_nest', function (Blueprint $table) {
            $table->unsignedInteger('allocation_id');
            $table->unsignedInteger('nest_id');
            $table->timestamps();

            $table->primary(['allocation_id', 'nest_id']);
            $table->foreign('allocation_id')
                ->references('id')
                ->on('allocations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('nest_id')
                ->references('id')
                ->on('nests')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('allocation_egg', function (Blueprint $table) {
            $table->unsignedInteger('allocation_id');
            $table->unsignedInteger('egg_id');
            $table->timestamps();

            $table->primary(['allocation_id', 'egg_id']);
            $table->foreign('allocation_id')
                ->references('id')
                ->on('allocations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('egg_id')
                ->references('id')
                ->on('eggs')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_egg');
        Schema::dropIfExists('allocation_nest');
    }
}
