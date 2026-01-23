<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate QuotationEvaluation approval names to People
        $this->migrateQuotationEvaluations();
        
        // Migrate ProfitAndLoss approval names to People
        $this->migrateProfitAndLosses();
    }

    /**
     * Migrate QuotationEvaluation approval names to People records.
     */
    private function migrateQuotationEvaluations(): void
    {
        // Get unique team_id + name combinations for each approval field using raw DB queries
        $fields = [
            'dept_head_sales_name' => 'dept_head_sales_id',
            'deputy_director_name' => 'deputy_director_id',
            'approved_by_name' => 'approved_by_id',
        ];

        foreach ($fields as $nameField => $idField) {
            // Use raw DB query to avoid Eloquent model casting issues
            $uniqueNames = DB::table('quotation_evaluations')
                ->select('team_id', $nameField)
                ->whereNotNull($nameField)
                ->distinct()
                ->get();

            foreach ($uniqueNames as $record) {
                // Find or create People record using DB query first, then model
                $person = DB::table('people')
                    ->where('team_id', $record->team_id)
                    ->where('name', $record->{$nameField})
                    ->first();

                if (!$person) {
                    // Create new People record using DB to avoid enum casting issues
                    $peopleId = DB::table('people')->insertGetId([
                        'team_id' => $record->team_id,
                        'name' => $record->{$nameField},
                        'is_key_account' => true,
                        'creation_source' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $peopleId = $person->id;
                }

                // Update all QE records with this name to use the FK
                DB::table('quotation_evaluations')
                    ->where('team_id', $record->team_id)
                    ->where($nameField, $record->{$nameField})
                    ->update([$idField => $peopleId]);
            }
        }
    }

    /**
     * Migrate ProfitAndLoss approval names to People records.
     */
    private function migrateProfitAndLosses(): void
    {
        // Get unique team_id + name combinations for each approval field using raw DB queries
        $fields = [
            'dept_head_sales_name' => 'dept_head_sales_id',
            'deputy_director_name' => 'deputy_director_id',
            'approved_by_name' => 'approved_by_id',
        ];

        foreach ($fields as $nameField => $idField) {
            // Use raw DB query to avoid Eloquent model casting issues
            $uniqueNames = DB::table('profit_and_losses')
                ->select('team_id', $nameField)
                ->whereNotNull($nameField)
                ->distinct()
                ->get();

            foreach ($uniqueNames as $record) {
                // Find or create People record using DB query first, then model
                $person = DB::table('people')
                    ->where('team_id', $record->team_id)
                    ->where('name', $record->{$nameField})
                    ->first();

                if (!$person) {
                    // Create new People record using DB to avoid enum casting issues
                    $peopleId = DB::table('people')->insertGetId([
                        'team_id' => $record->team_id,
                        'name' => $record->{$nameField},
                        'is_key_account' => true,
                        'creation_source' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $peopleId = $person->id;
                }

                // Update all PNL records with this name to use the FK
                DB::table('profit_and_losses')
                    ->where('team_id', $record->team_id)
                    ->where($nameField, $record->{$nameField})
                    ->update([$idField => $peopleId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore string names from People relationships
        $this->restoreQuotationEvaluations();
        $this->restoreProfitAndLosses();
    }

    /**
     * Restore QuotationEvaluation string names from People FKs.
     */
    private function restoreQuotationEvaluations(): void
    {
        $fields = [
            'dept_head_sales_id' => 'dept_head_sales_name',
            'deputy_director_id' => 'deputy_director_name',
            'approved_by_id' => 'approved_by_name',
        ];

        foreach ($fields as $idField => $nameField) {
            // Use raw DB query to avoid Eloquent model casting issues
            $qes = DB::table('quotation_evaluations')
                ->whereNotNull($idField)
                ->get();

            foreach ($qes as $qe) {
                $person = DB::table('people')->find($qe->{$idField});
                if ($person) {
                    DB::table('quotation_evaluations')
                        ->where('id', $qe->id)
                        ->update([$nameField => $person->name]);
                }
            }
        }
    }

    /**
     * Restore ProfitAndLoss string names from People FKs.
     */
    private function restoreProfitAndLosses(): void
    {
        $fields = [
            'dept_head_sales_id' => 'dept_head_sales_name',
            'deputy_director_id' => 'deputy_director_name',
            'approved_by_id' => 'approved_by_name',
        ];

        foreach ($fields as $idField => $nameField) {
            // Use raw DB query to avoid Eloquent model casting issues
            $pnls = DB::table('profit_and_losses')
                ->whereNotNull($idField)
                ->get();

            foreach ($pnls as $pnl) {
                $person = DB::table('people')->find($pnl->{$idField});
                if ($person) {
                    DB::table('profit_and_losses')
                        ->where('id', $pnl->id)
                        ->update([$nameField => $person->name]);
                }
            }
        }
    }
};
