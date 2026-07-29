<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial documents must outlive the entities they reference. A request or a
 * company is archived (soft-deleted / disabled), never hard-deleted, once it has
 * produced an order, invoice, payment or P&L. RESTRICT makes that the enforced
 * default instead of a convention. team_id cascades are deliberately untouched:
 * deleting a team is account closure and must remove its data.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, references: string}>
     */
    private array $constraints = [
        ['table' => 'buyer_orders', 'column' => 'request_id', 'references' => 'requests'],
        ['table' => 'buyer_orders', 'column' => 'buyer_id', 'references' => 'companies'],
        ['table' => 'buyer_invoices', 'column' => 'request_id', 'references' => 'requests'],
        ['table' => 'buyer_payments', 'column' => 'buyer_invoice_id', 'references' => 'buyer_invoices'],
        ['table' => 'supplier_invoices', 'column' => 'request_id', 'references' => 'requests'],
        ['table' => 'supplier_invoices', 'column' => 'supplier_id', 'references' => 'companies'],
        ['table' => 'supplier_payments', 'column' => 'supplier_invoice_id', 'references' => 'supplier_invoices'],
        ['table' => 'profit_and_losses', 'column' => 'request_id', 'references' => 'requests'],
    ];

    public function up(): void
    {
        foreach ($this->constraints as $constraint) {
            Schema::table($constraint['table'], function (Blueprint $table) use ($constraint): void {
                $table->dropForeign([$constraint['column']]);
                $table->foreign($constraint['column'])
                    ->references('id')
                    ->on($constraint['references'])
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->constraints as $constraint) {
            Schema::table($constraint['table'], function (Blueprint $table) use ($constraint): void {
                $table->dropForeign([$constraint['column']]);
                $table->foreign($constraint['column'])
                    ->references('id')
                    ->on($constraint['references'])
                    ->cascadeOnDelete();
            });
        }
    }
};
