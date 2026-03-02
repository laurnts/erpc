<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->boolean('obtained')->default(false)->after('valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table): void {
            $table->dropColumn('obtained');
        });
    }
};
