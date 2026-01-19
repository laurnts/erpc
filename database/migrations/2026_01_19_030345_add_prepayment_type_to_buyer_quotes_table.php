<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_quotes', function (Blueprint $table) {
            $table->string('prepayment_type', 10)->default('percent')->after('total');
            $table->decimal('prepayment_amount', 15, 4)->default(0)->after('prepayment_type');
        });

        // Migrate existing prepayment_percent values to prepayment_amount
        DB::table('buyer_quotes')
            ->where('prepayment_percent', '>', 0)
            ->update([
                'prepayment_amount' => DB::raw('prepayment_percent'),
            ]);
    }

    public function down(): void
    {
        Schema::table('buyer_quotes', function (Blueprint $table) {
            $table->dropColumn(['prepayment_type', 'prepayment_amount']);
        });
    }
};
