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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('next_billing_at')->nullable()->after('ends_at');
            $table->string('billing_interval')->nullable()->after('next_billing_at'); // month, quarter, half-year, year
            $table->decimal('billing_amount', 10, 2)->nullable()->after('billing_interval');
            $table->boolean('is_credits_based')->default(false)->after('billing_amount');
            
            $table->index('next_billing_at');
            $table->index('is_credits_based');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['next_billing_at']);
            $table->dropIndex(['is_credits_based']);
            $table->dropColumn(['next_billing_at', 'billing_interval', 'billing_amount', 'is_credits_based']);
        });
    }
};
