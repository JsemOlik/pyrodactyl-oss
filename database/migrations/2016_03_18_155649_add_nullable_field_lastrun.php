<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class AddNullableFieldLastrun extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    $driver = DB::getDriverName();

    if ($driver === 'pgsql') {
      // PostgreSQL-specific syntax
      $table = DB::getQueryGrammar()->wrapTable('tasks');
      DB::statement('ALTER TABLE ' . $table . ' ALTER COLUMN last_run DROP NOT NULL;');
    } elseif ($driver === 'sqlite') {
      // SQLite doesn't support ALTER COLUMN, skip this migration for SQLite
      // SQLite columns are nullable by default
      return;
    } else {
      // MySQL/MariaDB-specific syntax
      $table = DB::getQueryGrammar()->wrapTable('tasks');
      DB::statement('ALTER TABLE ' . $table . ' CHANGE `last_run` `last_run` TIMESTAMP NULL;');
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    $driver = DB::getDriverName();

    if ($driver === 'pgsql') {
      // PostgreSQL-specific syntax
      $table = DB::getQueryGrammar()->wrapTable('tasks');
      DB::statement('ALTER TABLE ' . $table . ' ALTER COLUMN last_run SET NOT NULL;');
    } elseif ($driver === 'sqlite') {
      // SQLite doesn't support ALTER COLUMN, skip this migration for SQLite
      return;
    } else {
      // MySQL/MariaDB-specific syntax
      $table = DB::getQueryGrammar()->wrapTable('tasks');
      DB::statement('ALTER TABLE ' . $table . ' CHANGE `last_run` `last_run` TIMESTAMP;');
    }
  }
}
