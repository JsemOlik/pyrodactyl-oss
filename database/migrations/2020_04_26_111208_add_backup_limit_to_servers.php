<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBackupLimitToServers extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // Check if the column already exists using Laravel's cross-database method
    if (Schema::hasColumn('servers', 'backup_limit')) {
      // Column exists, just update it
      Schema::table('servers', function (Blueprint $table) {
        $table->unsignedInteger('backup_limit')->default(0)->change();
      });
    } else {
      // Column doesn't exist, add it
      Schema::table('servers', function (Blueprint $table) {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
          // SQLite doesn't support 'after' clause
          $table->unsignedInteger('backup_limit')->default(0);
        } else {
          $table->unsignedInteger('backup_limit')->default(0)->after('database_limit');
        }
      });
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('servers', function (Blueprint $table) {
      $table->dropColumn('backup_limit');
    });
  }
}
