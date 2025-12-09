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
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('sales_percentage', 5, 2)->nullable()->after('price')->comment('Percentage discount for sales (e.g., 20.00 for 20% off)');
            $table->decimal('first_month_sales_percentage', 5, 2)->nullable()->after('sales_percentage')->comment('Percentage discount for first month (e.g., 50.00 for 50% off)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['sales_percentage', 'first_month_sales_percentage']);
        });
    }
};
