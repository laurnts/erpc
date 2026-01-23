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
        // Update quotation_evaluations.prepared_by_id
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by_id']);
        });

        // Update prepared_by_id values using mapping
        $mappings = DB::table('key_account_people_mapping')->get()->keyBy('key_account_id');
        
        DB::table('quotation_evaluations')
            ->whereNotNull('prepared_by_id')
            ->get()
            ->each(function ($qe) use ($mappings): void {
                $mapping = $mappings->get($qe->prepared_by_id);
                if ($mapping) {
                    DB::table('quotation_evaluations')
                        ->where('id', $qe->id)
                        ->update(['prepared_by_id' => $mapping->people_id]);
                }
            });

        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->foreign('prepared_by_id')
                ->references('id')
                ->on('people')
                ->nullOnDelete();
        });

        // Update profit_and_losses.prepared_by_id
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by_id']);
        });

        DB::table('profit_and_losses')
            ->whereNotNull('prepared_by_id')
            ->get()
            ->each(function ($pnl) use ($mappings): void {
                $mapping = $mappings->get($pnl->prepared_by_id);
                if ($mapping) {
                    DB::table('profit_and_losses')
                        ->where('id', $pnl->id)
                        ->update(['prepared_by_id' => $mapping->people_id]);
                }
            });

        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->foreign('prepared_by_id')
                ->references('id')
                ->on('people')
                ->nullOnDelete();
        });

        // Update key_account_buyers.key_account_id
        Schema::table('key_account_buyers', function (Blueprint $table): void {
            $table->dropForeign(['key_account_id']);
            $table->dropUnique(['key_account_id', 'buyer_id']);
        });

        DB::table('key_account_buyers')
            ->get()
            ->each(function ($pivot) use ($mappings): void {
                $mapping = $mappings->get($pivot->key_account_id);
                if ($mapping) {
                    DB::table('key_account_buyers')
                        ->where('id', $pivot->id)
                        ->update(['key_account_id' => $mapping->people_id]);
                }
            });

        Schema::table('key_account_buyers', function (Blueprint $table): void {
            $table->foreign('key_account_id')
                ->references('id')
                ->on('people')
                ->cascadeOnDelete();
            $table->unique(['key_account_id', 'buyer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $mappings = DB::table('key_account_people_mapping')->get()->keyBy('people_id');

        // Revert key_account_buyers
        Schema::table('key_account_buyers', function (Blueprint $table): void {
            $table->dropForeign(['key_account_id']);
            $table->dropUnique(['key_account_id', 'buyer_id']);
        });

        DB::table('key_account_buyers')
            ->get()
            ->each(function ($pivot) use ($mappings): void {
                $mapping = $mappings->get($pivot->key_account_id);
                if ($mapping) {
                    DB::table('key_account_buyers')
                        ->where('id', $pivot->id)
                        ->update(['key_account_id' => $mapping->key_account_id]);
                }
            });

        Schema::table('key_account_buyers', function (Blueprint $table): void {
            $table->foreign('key_account_id')
                ->references('id')
                ->on('key_accounts')
                ->cascadeOnDelete();
            $table->unique(['key_account_id', 'buyer_id']);
        });

        // Revert profit_and_losses
        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by_id']);
        });

        DB::table('profit_and_losses')
            ->whereNotNull('prepared_by_id')
            ->get()
            ->each(function ($pnl) use ($mappings): void {
                $mapping = $mappings->get($pnl->prepared_by_id);
                if ($mapping) {
                    DB::table('profit_and_losses')
                        ->where('id', $pnl->id)
                        ->update(['prepared_by_id' => $mapping->key_account_id]);
                }
            });

        Schema::table('profit_and_losses', function (Blueprint $table): void {
            $table->foreign('prepared_by_id')
                ->references('id')
                ->on('key_accounts')
                ->nullOnDelete();
        });

        // Revert quotation_evaluations
        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by_id']);
        });

        DB::table('quotation_evaluations')
            ->whereNotNull('prepared_by_id')
            ->get()
            ->each(function ($qe) use ($mappings): void {
                $mapping = $mappings->get($qe->prepared_by_id);
                if ($mapping) {
                    DB::table('quotation_evaluations')
                        ->where('id', $qe->id)
                        ->update(['prepared_by_id' => $mapping->key_account_id]);
                }
            });

        Schema::table('quotation_evaluations', function (Blueprint $table): void {
            $table->foreign('prepared_by_id')
                ->references('id')
                ->on('key_accounts')
                ->nullOnDelete();
        });
    }
};
