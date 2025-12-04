<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyChecksumsColumnForBackups extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    $driver = DB::getDriverName();
    
    if ($driver === 'sqlite') {
      // SQLite doesn't support ALTER TABLE RENAME COLUMN directly
      // Check if column already renamed or doesn't exist
      if (Schema::hasColumn('backups', 'sha256_hash') && !Schema::hasColumn('backups', 'checksum')) {
        // For SQLite, we'll add the new column, copy data, then drop old column
        Schema::table('backups', function (Blueprint $table) {
          $table->string('checksum')->nullable()->after('sha256_hash');
        });
        
        DB::update("UPDATE backups SET checksum = 'sha256:' || sha256_hash WHERE sha256_hash IS NOT NULL");
        
        Schema::table('backups', function (Blueprint $table) {
          $table->dropColumn('sha256_hash');
        });
      } elseif (Schema::hasColumn('backups', 'checksum')) {
        // Column already exists, just update values
        DB::update("UPDATE backups SET checksum = 'sha256:' || checksum WHERE checksum IS NOT NULL AND checksum NOT LIKE 'sha256:%'");
      }
    } else {
      Schema::table('backups', function (Blueprint $table) {
        $table->renameColumn('sha256_hash', 'checksum');
      });

      // Update existing checksums to include 'sha256:' prefix
      DB::update("UPDATE backups SET checksum = CONCAT('sha256:', checksum) WHERE checksum IS NOT NULL");
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    $driver = DB::getDriverName();
    
    if ($driver === 'sqlite') {
      // SQLite doesn't support ALTER TABLE RENAME COLUMN directly
      if (Schema::hasColumn('backups', 'checksum') && !Schema::hasColumn('backups', 'sha256_hash')) {
        // Remove 'sha256:' prefix first
        DB::update("UPDATE backups SET checksum = SUBSTR(checksum, 8) WHERE checksum LIKE 'sha256:%'");
        
        // Add old column, copy data, drop new column
        Schema::table('backups', function (Blueprint $table) {
          $table->string('sha256_hash')->nullable()->after('checksum');
        });
        
        DB::update("UPDATE backups SET sha256_hash = checksum");
        
        Schema::table('backups', function (Blueprint $table) {
          $table->dropColumn('checksum');
        });
      }
    } else {
      Schema::table('backups', function (Blueprint $table) {
        $table->renameColumn('checksum', 'sha256_hash');
      });

      // Remove 'sha256:' prefix from checksums
      DB::update("UPDATE backups SET sha256_hash = SUBSTRING(sha256_hash, 8) WHERE sha256_hash LIKE 'sha256:%'");
    }
  }
}
