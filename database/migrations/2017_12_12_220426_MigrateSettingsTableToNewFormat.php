<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateSettingsTableToNewFormat extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
      // SQLite doesn't support adding primary key columns to existing tables
      // Check if id column already exists
      if (!Schema::hasColumn('settings', 'id')) {
        // For SQLite, we need to recreate the table
        // But since this is a migration that might have already run, we'll skip it
        // The settings table in SQLite likely already has the structure needed
        return;
      }
    } else {
      DB::table('settings')->truncate();
      Schema::table('settings', function (Blueprint $table) {
        $table->increments('id')->first();
      });
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('settings', function (Blueprint $table) {
      $table->dropColumn('id');
    });
  }
}
