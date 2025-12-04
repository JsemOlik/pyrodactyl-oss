<?php

namespace Database\Seeders;

use Pterodactyl\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed if no plans exist
        if (Plan::count() > 0) {
            return;
        }

        $plans = [
            [
                'name' => 'Starter',
                'description' => 'Perfect for small projects and getting started',
                'price' => 5.00,
                'currency' => 'USD',
                'interval' => 'month',
                'memory' => 1024, // 1GB
                'disk' => 10240, // 10GB
                'cpu' => 100, // 100% of 1 core
                'io' => 500,
                'swap' => 0,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard',
                'description' => 'Great for medium-sized applications',
                'price' => 15.00,
                'currency' => 'USD',
                'interval' => 'month',
                'memory' => 4096, // 4GB
                'disk' => 40960, // 40GB
                'cpu' => 200, // 200% of 1 core (2 cores)
                'io' => 500,
                'swap' => 0,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'description' => 'Ideal for high-performance applications',
                'price' => 35.00,
                'currency' => 'USD',
                'interval' => 'month',
                'memory' => 8192, // 8GB
                'disk' => 81920, // 80GB
                'cpu' => 400, // 400% of 1 core (4 cores)
                'io' => 500,
                'swap' => 0,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Maximum resources for demanding workloads',
                'price' => 75.00,
                'currency' => 'USD',
                'interval' => 'month',
                'memory' => 16384, // 16GB
                'disk' => 163840, // 160GB
                'cpu' => 800, // 800% of 1 core (8 cores)
                'io' => 500,
                'swap' => 0,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }
    }
}
