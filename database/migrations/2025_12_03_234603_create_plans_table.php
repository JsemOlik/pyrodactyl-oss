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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('stripe_price_id')->nullable()->unique();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('interval')->default('month'); // month, quarter, half-year, year
            $table->integer('memory')->nullable(); // Memory in MB (for custom plans)
            $table->integer('disk')->nullable(); // Disk space in MB
            $table->integer('cpu')->nullable(); // CPU limit percentage
            $table->integer('io')->nullable(); // IO weight
            $table->integer('swap')->nullable(); // Swap in MB
            $table->boolean('is_custom')->default(false); // Whether this is a custom plan
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

