<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Originally converted order_column to the Flowforge position format and
     * backfilled ranks via the package's Rank service. The relaticle/flowforge
     * dependency was removed with the Tasks Board (see
     * 2026_07_04_113447_remove_tasks_and_notes_entities), so this now recreates
     * the column shape without the package. The rank backfill only mattered for
     * databases that had already run it; fresh installs have no rows to rank,
     * and both tables are dropped by later migrations anyway.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('order_column');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('order_column')->nullable();
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn('order_column');
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('order_column')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('order_column');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->integer('order_column')->nullable();
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn('order_column');
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->integer('order_column')->nullable();
        });
    }
};
