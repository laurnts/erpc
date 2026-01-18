<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('delivery_type', 50)->nullable()->after('lead_time_days');
            $table->string('delivery_type_details')->nullable()->after('delivery_type');
            $table->boolean('is_taxable')->default(true)->after('delivery_type_details');
            $table->text('delivery_term')->nullable()->after('is_taxable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_type',
                'delivery_type_details',
                'is_taxable',
                'delivery_term',
            ]);
        });
    }
};
