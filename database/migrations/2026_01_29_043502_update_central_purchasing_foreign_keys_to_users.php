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
        // Note: Data migration should run first to update People IDs to User IDs

        // Clean up orphaned foreign key references before adding constraints
        $this->cleanupOrphanedReferences('quotation_evaluations');
        $this->cleanupOrphanedReferences('profit_and_losses');

        // Update quotation_evaluations table - drop old foreign key constraints
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            // Drop foreign keys if they exist
            try {
                $table->dropForeign(['prepared_by_id']);
            } catch (\Exception) {
                // Foreign key might not exist or have different name
            }

            if (Schema::hasColumn('quotation_evaluations', 'dept_head_sales_id')) {
                try {
                    $table->dropForeign(['dept_head_sales_id']);
                } catch (\Exception) {
                    // Foreign key might not exist
                }
            }

            if (Schema::hasColumn('quotation_evaluations', 'deputy_director_id')) {
                try {
                    $table->dropForeign(['deputy_director_id']);
                } catch (\Exception) {
                    // Foreign key might not exist
                }
            }

            if (Schema::hasColumn('quotation_evaluations', 'approved_by_id')) {
                try {
                    $table->dropForeign(['approved_by_id']);
                } catch (\Exception) {
                    // Foreign key might not exist
                }
            }
        });

        // Add columns if they don't exist (for dept_head_sales_id, deputy_director_id, approved_by_id)
        if (! Schema::hasColumn('quotation_evaluations', 'dept_head_sales_id')) {
            Schema::table('quotation_evaluations', function (Blueprint $table): void {
                $table->foreignId('dept_head_sales_id')->nullable()->after('prepared_by_id');
                $table->foreignId('deputy_director_id')->nullable()->after('dept_head_sales_id');
                $table->foreignId('approved_by_id')->nullable()->after('deputy_director_id');
            });
        }

        // Update foreign key constraints to reference users table
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->foreign('prepared_by_id')->references('id')->on('users')->nullOnDelete();

            if (Schema::hasColumn('quotation_evaluations', 'dept_head_sales_id')) {
                $table->foreign('dept_head_sales_id')->references('id')->on('users')->nullOnDelete();
            }

            if (Schema::hasColumn('quotation_evaluations', 'deputy_director_id')) {
                $table->foreign('deputy_director_id')->references('id')->on('users')->nullOnDelete();
            }

            if (Schema::hasColumn('quotation_evaluations', 'approved_by_id')) {
                $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        // Update profit_and_losses table - drop old foreign key constraints
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            try {
                $table->dropForeign(['prepared_by_id']);
            } catch (\Exception) {
                // Foreign key might not exist
            }

            if (Schema::hasColumn('profit_and_losses', 'dept_head_sales_id')) {
                try {
                    $table->dropForeign(['dept_head_sales_id']);
                } catch (\Exception) {
                    // Foreign key might not exist
                }
            }

            if (Schema::hasColumn('profit_and_losses', 'deputy_director_id')) {
                try {
                    $table->dropForeign(['deputy_director_id']);
                } catch (\Exception) {
                    // Foreign key might not exist
                }
            }

            if (Schema::hasColumn('profit_and_losses', 'approved_by_id')) {
                try {
                    $table->dropForeign(['approved_by_id']);
                } catch (\Exception) {
                    // Foreign key might not exist
                }
            }
        });

        // Add columns if they don't exist
        if (! Schema::hasColumn('profit_and_losses', 'dept_head_sales_id')) {
            Schema::table('profit_and_losses', function (Blueprint $table): void {
                $table->foreignId('dept_head_sales_id')->nullable()->after('prepared_by_id');
                $table->foreignId('deputy_director_id')->nullable()->after('dept_head_sales_id');
                $table->foreignId('approved_by_id')->nullable()->after('deputy_director_id');
            });
        }

        // Update foreign key constraints to reference users table
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->foreign('prepared_by_id')->references('id')->on('users')->nullOnDelete();

            if (Schema::hasColumn('profit_and_losses', 'dept_head_sales_id')) {
                $table->foreign('dept_head_sales_id')->references('id')->on('users')->nullOnDelete();
            }

            if (Schema::hasColumn('profit_and_losses', 'deputy_director_id')) {
                $table->foreign('deputy_director_id')->references('id')->on('users')->nullOnDelete();
            }

            if (Schema::hasColumn('profit_and_losses', 'approved_by_id')) {
                $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Clean up orphaned foreign key references that don't exist in users table.
     */
    private function cleanupOrphanedReferences(string $table): void
    {
        $validUserIds = DB::table('users')->pluck('id')->toArray();
        $fields = ['prepared_by_id', 'dept_head_sales_id', 'deputy_director_id', 'approved_by_id'];

        foreach ($fields as $field) {
            if (Schema::hasColumn($table, $field)) {
                // Set orphaned references to NULL
                DB::table($table)
                    ->whereNotNull($field)
                    ->whereNotIn($field, $validUserIds)
                    ->update([$field => null]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by_id']);
            $table->dropForeign(['dept_head_sales_id']);
            $table->dropForeign(['deputy_director_id']);
            $table->dropForeign(['approved_by_id']);

            // Restore old foreign key (if key_accounts table still exists)
            $table->foreign('prepared_by_id')->references('id')->on('key_accounts')->nullOnDelete();
        });

        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by_id']);
            $table->dropForeign(['dept_head_sales_id']);
            $table->dropForeign(['deputy_director_id']);
            $table->dropForeign(['approved_by_id']);

            // Restore old foreign key (if key_accounts table still exists)
            $table->foreign('prepared_by_id')->references('id')->on('key_accounts')->nullOnDelete();
        });
    }
};
