<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure default units exist for all teams
        $seeder = new \Database\Seeders\UnitOfMeasureSeeder();
        $seeder->run();

        // Tables that have unit fields
        $tables = [
            'articles',
            'request_items',
            'buyer_quote_items',
            'supplier_quote_items',
            'buyer_order_items',
            'supplier_order_items',
            'buyer_invoice_items',
            'supplier_invoice_items',
        ];

        // Get all teams
        $teams = Team::all();

        foreach ($teams as $team) {
            // Get all unique unit values for this team from all tables
            $unitValues = collect();

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $teamIdColumn = $this->getTeamIdColumn($table);

                if ($teamIdColumn !== null) {
                    // Direct team_id column
                    $units = DB::table($table)
                        ->where($teamIdColumn, $team->id)
                        ->whereNotNull('unit')
                        ->where('unit', '!=', '')
                        ->distinct()
                        ->pluck('unit')
                        ->filter();
                } else {
                    // Need to join to get team_id
                    $units = match ($table) {
                        'request_items' => DB::table('request_items')
                            ->join('requests', 'request_items.request_id', '=', 'requests.id')
                            ->where('requests.team_id', $team->id)
                            ->whereNotNull('request_items.unit')
                            ->where('request_items.unit', '!=', '')
                            ->distinct()
                            ->pluck('request_items.unit')
                            ->filter(),
                        'buyer_quote_items' => DB::table('buyer_quote_items')
                            ->join('buyer_quotes', 'buyer_quote_items.buyer_quote_id', '=', 'buyer_quotes.id')
                            ->join('requests', 'buyer_quotes.request_id', '=', 'requests.id')
                            ->where('requests.team_id', $team->id)
                            ->whereNotNull('buyer_quote_items.unit')
                            ->where('buyer_quote_items.unit', '!=', '')
                            ->distinct()
                            ->pluck('buyer_quote_items.unit')
                            ->filter(),
                        'supplier_quote_items' => DB::table('supplier_quote_items')
                            ->join('supplier_quotes', 'supplier_quote_items.supplier_quote_id', '=', 'supplier_quotes.id')
                            ->join('requests', 'supplier_quotes.request_id', '=', 'requests.id')
                            ->where('requests.team_id', $team->id)
                            ->whereNotNull('supplier_quote_items.unit')
                            ->where('supplier_quote_items.unit', '!=', '')
                            ->distinct()
                            ->pluck('supplier_quote_items.unit')
                            ->filter(),
                        'buyer_order_items' => DB::table('buyer_order_items')
                            ->join('buyer_orders', 'buyer_order_items.buyer_order_id', '=', 'buyer_orders.id')
                            ->where('buyer_orders.team_id', $team->id)
                            ->whereNotNull('buyer_order_items.unit')
                            ->where('buyer_order_items.unit', '!=', '')
                            ->distinct()
                            ->pluck('buyer_order_items.unit')
                            ->filter(),
                        'supplier_order_items' => DB::table('supplier_order_items')
                            ->join('supplier_orders', 'supplier_order_items.supplier_order_id', '=', 'supplier_orders.id')
                            ->where('supplier_orders.team_id', $team->id)
                            ->whereNotNull('supplier_order_items.unit')
                            ->where('supplier_order_items.unit', '!=', '')
                            ->distinct()
                            ->pluck('supplier_order_items.unit')
                            ->filter(),
                        'buyer_invoice_items' => DB::table('buyer_invoice_items')
                            ->join('buyer_invoices', 'buyer_invoice_items.buyer_invoice_id', '=', 'buyer_invoices.id')
                            ->where('buyer_invoices.team_id', $team->id)
                            ->whereNotNull('buyer_invoice_items.unit')
                            ->where('buyer_invoice_items.unit', '!=', '')
                            ->distinct()
                            ->pluck('buyer_invoice_items.unit')
                            ->filter(),
                        'supplier_invoice_items' => DB::table('supplier_invoice_items')
                            ->join('supplier_invoices', 'supplier_invoice_items.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->where('supplier_invoices.team_id', $team->id)
                            ->whereNotNull('supplier_invoice_items.unit')
                            ->where('supplier_invoice_items.unit', '!=', '')
                            ->distinct()
                            ->pluck('supplier_invoice_items.unit')
                            ->filter(),
                        default => collect(),
                    };
                }

                $unitValues = $unitValues->merge($units);
            }

            $unitValues = $unitValues->unique()->values();

            // Create UnitOfMeasure records for any units that don't exist
            foreach ($unitValues as $unitCode) {
                $unitCode = strtolower(trim($unitCode));

                if (empty($unitCode)) {
                    continue;
                }

                // Find or create the unit
                $unitOfMeasure = UnitOfMeasure::firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'code' => $unitCode,
                    ],
                    [
                        'label' => $this->getLabelForCode($unitCode),
                        'is_active' => true,
                        'sort_order' => 999, // Custom units go to the end
                    ]
                );

                // Now update all records with this unit value
                foreach ($tables as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    $teamIdColumn = $this->getTeamIdColumn($table);

                    if ($teamIdColumn !== null) {
                        // Direct team_id column
                        DB::table($table)
                            ->where($teamIdColumn, $team->id)
                            ->where('unit', $unitCode)
                            ->whereNull('unit_of_measure_id')
                            ->update(['unit_of_measure_id' => $unitOfMeasure->id]);
                    } else {
                        // Need to get IDs first, then update
                        $ids = match ($table) {
                            'request_items' => DB::table('request_items')
                                ->join('requests', 'request_items.request_id', '=', 'requests.id')
                                ->where('requests.team_id', $team->id)
                                ->where('request_items.unit', $unitCode)
                                ->whereNull('request_items.unit_of_measure_id')
                                ->pluck('request_items.id'),
                            'buyer_quote_items' => DB::table('buyer_quote_items')
                                ->join('buyer_quotes', 'buyer_quote_items.buyer_quote_id', '=', 'buyer_quotes.id')
                                ->join('requests', 'buyer_quotes.request_id', '=', 'requests.id')
                                ->where('requests.team_id', $team->id)
                                ->where('buyer_quote_items.unit', $unitCode)
                                ->whereNull('buyer_quote_items.unit_of_measure_id')
                                ->pluck('buyer_quote_items.id'),
                            'supplier_quote_items' => DB::table('supplier_quote_items')
                                ->join('supplier_quotes', 'supplier_quote_items.supplier_quote_id', '=', 'supplier_quotes.id')
                                ->join('requests', 'supplier_quotes.request_id', '=', 'requests.id')
                                ->where('requests.team_id', $team->id)
                                ->where('supplier_quote_items.unit', $unitCode)
                                ->whereNull('supplier_quote_items.unit_of_measure_id')
                                ->pluck('supplier_quote_items.id'),
                            'buyer_order_items' => DB::table('buyer_order_items')
                                ->join('buyer_orders', 'buyer_order_items.buyer_order_id', '=', 'buyer_orders.id')
                                ->where('buyer_orders.team_id', $team->id)
                                ->where('buyer_order_items.unit', $unitCode)
                                ->whereNull('buyer_order_items.unit_of_measure_id')
                                ->pluck('buyer_order_items.id'),
                            'supplier_order_items' => DB::table('supplier_order_items')
                                ->join('supplier_orders', 'supplier_order_items.supplier_order_id', '=', 'supplier_orders.id')
                                ->where('supplier_orders.team_id', $team->id)
                                ->where('supplier_order_items.unit', $unitCode)
                                ->whereNull('supplier_order_items.unit_of_measure_id')
                                ->pluck('supplier_order_items.id'),
                            'buyer_invoice_items' => DB::table('buyer_invoice_items')
                                ->join('buyer_invoices', 'buyer_invoice_items.buyer_invoice_id', '=', 'buyer_invoices.id')
                                ->where('buyer_invoices.team_id', $team->id)
                                ->where('buyer_invoice_items.unit', $unitCode)
                                ->whereNull('buyer_invoice_items.unit_of_measure_id')
                                ->pluck('buyer_invoice_items.id'),
                            'supplier_invoice_items' => DB::table('supplier_invoice_items')
                                ->join('supplier_invoices', 'supplier_invoice_items.supplier_invoice_id', '=', 'supplier_invoices.id')
                                ->where('supplier_invoices.team_id', $team->id)
                                ->where('supplier_invoice_items.unit', $unitCode)
                                ->whereNull('supplier_invoice_items.unit_of_measure_id')
                                ->pluck('supplier_invoice_items.id'),
                            default => collect(),
                        };

                        if ($ids->isNotEmpty()) {
                            DB::table($table)
                                ->whereIn('id', $ids)
                                ->update(['unit_of_measure_id' => $unitOfMeasure->id]);
                        }
                    }
                }
            }

            // Set default 'pcs' unit for any remaining null values
            $defaultUnit = UnitOfMeasure::where('team_id', $team->id)
                ->where('code', 'pcs')
                ->first();

            if ($defaultUnit) {
                foreach ($tables as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    $teamIdColumn = $this->getTeamIdColumn($table);

                    if ($teamIdColumn !== null) {
                        // Direct team_id column
                        DB::table($table)
                            ->where($teamIdColumn, $team->id)
                            ->whereNull('unit_of_measure_id')
                            ->update(['unit_of_measure_id' => $defaultUnit->id]);
                    } else {
                        // Need to get IDs first, then update
                        $ids = match ($table) {
                            'request_items' => DB::table('request_items')
                                ->join('requests', 'request_items.request_id', '=', 'requests.id')
                                ->where('requests.team_id', $team->id)
                                ->whereNull('request_items.unit_of_measure_id')
                                ->pluck('request_items.id'),
                            'buyer_quote_items' => DB::table('buyer_quote_items')
                                ->join('buyer_quotes', 'buyer_quote_items.buyer_quote_id', '=', 'buyer_quotes.id')
                                ->join('requests', 'buyer_quotes.request_id', '=', 'requests.id')
                                ->where('requests.team_id', $team->id)
                                ->whereNull('buyer_quote_items.unit_of_measure_id')
                                ->pluck('buyer_quote_items.id'),
                            'supplier_quote_items' => DB::table('supplier_quote_items')
                                ->join('supplier_quotes', 'supplier_quote_items.supplier_quote_id', '=', 'supplier_quotes.id')
                                ->join('requests', 'supplier_quotes.request_id', '=', 'requests.id')
                                ->where('requests.team_id', $team->id)
                                ->whereNull('supplier_quote_items.unit_of_measure_id')
                                ->pluck('supplier_quote_items.id'),
                            'buyer_order_items' => DB::table('buyer_order_items')
                                ->join('buyer_orders', 'buyer_order_items.buyer_order_id', '=', 'buyer_orders.id')
                                ->where('buyer_orders.team_id', $team->id)
                                ->whereNull('buyer_order_items.unit_of_measure_id')
                                ->pluck('buyer_order_items.id'),
                            'supplier_order_items' => DB::table('supplier_order_items')
                                ->join('supplier_orders', 'supplier_order_items.supplier_order_id', '=', 'supplier_orders.id')
                                ->where('supplier_orders.team_id', $team->id)
                                ->whereNull('supplier_order_items.unit_of_measure_id')
                                ->pluck('supplier_order_items.id'),
                            'buyer_invoice_items' => DB::table('buyer_invoice_items')
                                ->join('buyer_invoices', 'buyer_invoice_items.buyer_invoice_id', '=', 'buyer_invoices.id')
                                ->where('buyer_invoices.team_id', $team->id)
                                ->whereNull('buyer_invoice_items.unit_of_measure_id')
                                ->pluck('buyer_invoice_items.id'),
                            'supplier_invoice_items' => DB::table('supplier_invoice_items')
                                ->join('supplier_invoices', 'supplier_invoice_items.supplier_invoice_id', '=', 'supplier_invoices.id')
                                ->where('supplier_invoices.team_id', $team->id)
                                ->whereNull('supplier_invoice_items.unit_of_measure_id')
                                ->pluck('supplier_invoice_items.id'),
                            default => collect(),
                        };

                        if ($ids->isNotEmpty()) {
                            DB::table($table)
                                ->whereIn('id', $ids)
                                ->update(['unit_of_measure_id' => $defaultUnit->id]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be easily reversed as we'd lose the mapping
        // The unit string values are preserved, so we can re-run the migration
    }

    /**
     * Get the team_id column name for a table.
     */
    private function getTeamIdColumn(string $table): ?string
    {
        return match ($table) {
            'articles' => 'team_id',
            default => null, // Other tables get team_id via joins
        };
    }

    /**
     * Get team IDs for records in a table that doesn't have direct team_id.
     */
    private function getTeamIdsForTable(string $table): array
    {
        return match ($table) {
            'request_items' => DB::table('request_items')
                ->join('requests', 'request_items.request_id', '=', 'requests.id')
                ->distinct()
                ->pluck('requests.team_id')
                ->toArray(),
            'buyer_quote_items' => DB::table('buyer_quote_items')
                ->join('buyer_quotes', 'buyer_quote_items.buyer_quote_id', '=', 'buyer_quotes.id')
                ->join('requests', 'buyer_quotes.request_id', '=', 'requests.id')
                ->distinct()
                ->pluck('requests.team_id')
                ->toArray(),
            'supplier_quote_items' => DB::table('supplier_quote_items')
                ->join('supplier_quotes', 'supplier_quote_items.supplier_quote_id', '=', 'supplier_quotes.id')
                ->join('requests', 'supplier_quotes.request_id', '=', 'requests.id')
                ->distinct()
                ->pluck('requests.team_id')
                ->toArray(),
            'buyer_order_items' => DB::table('buyer_order_items')
                ->join('buyer_orders', 'buyer_order_items.buyer_order_id', '=', 'buyer_orders.id')
                ->distinct()
                ->pluck('buyer_orders.team_id')
                ->toArray(),
            'supplier_order_items' => DB::table('supplier_order_items')
                ->join('supplier_orders', 'supplier_order_items.supplier_order_id', '=', 'supplier_orders.id')
                ->distinct()
                ->pluck('supplier_orders.team_id')
                ->toArray(),
            'buyer_invoice_items' => DB::table('buyer_invoice_items')
                ->join('buyer_invoices', 'buyer_invoice_items.buyer_invoice_id', '=', 'buyer_invoices.id')
                ->distinct()
                ->pluck('buyer_invoices.team_id')
                ->toArray(),
            'supplier_invoice_items' => DB::table('supplier_invoice_items')
                ->join('supplier_invoices', 'supplier_invoice_items.supplier_invoice_id', '=', 'supplier_invoices.id')
                ->distinct()
                ->pluck('supplier_invoices.team_id')
                ->toArray(),
            default => [],
        };
    }

    /**
     * Get a label for a unit code.
     */
    private function getLabelForCode(string $code): string
    {
        $labels = [
            'pcs' => 'Pieces',
            'kg' => 'Kilograms',
            'mt' => 'Metric Tons',
            'set' => 'Sets',
            'box' => 'Boxes',
            'roll' => 'Rolls',
            'pair' => 'Pairs',
            'l' => 'Liters',
            'm' => 'Meters',
        ];

        return $labels[strtolower($code)] ?? ucfirst($code);
    }
};
