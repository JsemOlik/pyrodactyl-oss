<?php

namespace Database\Factories;

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Pterodactyl\Models\DatabaseService;
use Illuminate\Database\Eloquent\Factories\Factory;

class DatabaseServiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DatabaseService::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'uuidShort' => Str::lower(Str::random(8)),
            'name' => $this->faker->firstName . ' Database',
            'description' => implode(' ', $this->faker->sentences()),
            'skip_scripts' => false,
            'status' => DatabaseService::STATUS_INSTALLING,
            'database_type' => DatabaseService::DATABASE_TYPE_MYSQL,
            'memory' => 512,
            'overhead_memory' => 0,
            'swap' => 0,
            'disk' => 1024,
            'io' => 500,
            'cpu' => 0,
            'threads' => null,
            'oom_disabled' => true,
            'exclude_from_resource_calculation' => false,
            'startup' => '/bin/bash echo "database startup"',
            'image' => 'mysql:8.0',
            'backup_limit' => 0,
            'backup_storage_limit' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

