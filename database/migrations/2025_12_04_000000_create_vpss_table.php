<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vpss', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->char('uuidShort', 8)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('owner_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            
            // Resource specifications
            $table->unsignedInteger('memory'); // RAM in MB
            $table->unsignedInteger('disk'); // Disk space in MB
            $table->unsignedInteger('cpu_cores'); // Number of CPU cores
            $table->unsignedInteger('cpu_sockets')->default(1); // Number of CPU sockets
            
            // Proxmox integration fields
            $table->integer('proxmox_vm_id')->nullable()->unique();
            $table->string('proxmox_node')->nullable();
            $table->string('proxmox_storage')->nullable();
            
            // Network information
            $table->string('ip_address')->nullable();
            $table->string('ipv6_address')->nullable();
            
            // Operating system
            $table->string('distribution')->default('ubuntu-server');
            
            // Timestamps
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            
            // Indexes
            $table->index('owner_id');
            $table->index('subscription_id');
            $table->index('status');
            $table->index('proxmox_vm_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vpss');
    }
};

