<?php

declare(strict_types=1);

use App\Enums\PrepaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->string('prepayment_type', 10)->default(PrepaymentType::PERCENT->value)->after('total');
            $table->decimal('prepayment_amount', 15, 4)->default(0)->after('prepayment_type');
            $table->integer('prepayment_percent')->default(0)->after('prepayment_amount');
        });

        Schema::create('supplier_quote_payment_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_quote_id')->constrained('supplier_quotes')->cascadeOnDelete();
            $table->integer('due_days')->default(0);
            $table->integer('percentage')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['supplier_quote_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->dropColumn(['prepayment_type', 'prepayment_amount', 'prepayment_percent']);
        });
        Schema::dropIfExists('supplier_quote_payment_terms');
    }
};
