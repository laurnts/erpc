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
        // Migrate existing payment_terms_days to the new payment_terms table
        // Only migrate quotes that don't already have payment terms
        $quotes = DB::table('buyer_quotes')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('buyer_quote_payment_terms')
                    ->whereColumn('buyer_quote_payment_terms.buyer_quote_id', 'buyer_quotes.id');
            })
            ->get();

        foreach ($quotes as $quote) {
            $dueDays = $quote->payment_terms_days ?? 30;

            DB::table('buyer_quote_payment_terms')->insert([
                'buyer_quote_id' => $quote->id,
                'due_days' => $dueDays,
                'percentage' => 100,
                'sort_order' => 0,
                'created_at' => $quote->created_at ?? now(),
                'updated_at' => $quote->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we can't determine which payment term
        // was the original payment_terms_days value
    }
};
