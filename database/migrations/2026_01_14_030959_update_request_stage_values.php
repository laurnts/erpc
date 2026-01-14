<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map old stage values to new stage values.
     *
     * @var array<string, string>
     */
    private array $stageMapping = [
        'quoting_supplier' => 'awaiting_supplier_response',
        'quoting_buyer' => 'preparing_buyer_quote',
        'quote_sent' => 'awaiting_buyer_confirmation',
        'quote_accepted' => 'preparing_supplier_order',
        'ordered' => 'preparing_supplier_order',
        'in_progress' => 'awaiting_shipment',
    ];

    public function up(): void
    {
        foreach ($this->stageMapping as $oldValue => $newValue) {
            DB::table('requests')
                ->where('stage', $oldValue)
                ->update(['stage' => $newValue]);
        }
    }

    public function down(): void
    {
        // Reverse mapping (note: quote_accepted and ordered both map to preparing_supplier_order,
        // so we can only reverse to 'ordered' as a reasonable default)
        $reverseMapping = [
            'awaiting_supplier_response' => 'quoting_supplier',
            'preparing_buyer_quote' => 'quoting_buyer',
            'awaiting_buyer_confirmation' => 'quote_sent',
            'preparing_supplier_order' => 'ordered',
            'awaiting_shipment' => 'in_progress',
        ];

        foreach ($reverseMapping as $newValue => $oldValue) {
            DB::table('requests')
                ->where('stage', $newValue)
                ->update(['stage' => $oldValue]);
        }
    }
};
