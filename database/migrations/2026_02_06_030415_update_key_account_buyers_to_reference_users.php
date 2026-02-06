<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists
        if (! Schema::hasTable('key_account_buyers')) {
            return;
        }

        // Drop old foreign key constraint that references people table
        DB::statement('ALTER TABLE key_account_buyers DROP CONSTRAINT IF EXISTS key_account_buyers_key_account_id_foreign');

        // Clean up orphaned data - delete records where key_account_id doesn't exist in users table
        DB::statement('
            DELETE FROM key_account_buyers 
            WHERE key_account_id NOT IN (SELECT id FROM users)
        ');

        // Update foreign key to reference users table
        Schema::table('key_account_buyers', function (Blueprint $table): void {
            $table->foreign('key_account_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('key_account_buyers')) {
            return;
        }

        Schema::table('key_account_buyers', function (Blueprint $table): void {
            try {
                $table->dropForeign(['key_account_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
        });

        // Note: Cannot restore reference to key_accounts table as it no longer exists
        // This migration is one-way
    }
};
