<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_quote_payment_terms', function (Blueprint $table): void {
            $table->unsignedTinyInteger('job_progress')->nullable()->default(null)->after('percentage');
        });

        Schema::table('supplier_quote_payment_terms', function (Blueprint $table): void {
            $table->unsignedTinyInteger('job_progress')->nullable()->default(null)->after('percentage');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_quote_payment_terms', function (Blueprint $table): void {
            $table->dropColumn('job_progress');
        });
        Schema::table('supplier_quote_payment_terms', function (Blueprint $table): void {
            $table->dropColumn('job_progress');
        });
    }
};
