<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->decimal('credit_released', 15, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_orders', function (Blueprint $table): void {
            $table->dropColumn('credit_released');
        });
    }
};
