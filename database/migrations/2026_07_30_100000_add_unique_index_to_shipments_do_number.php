<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * do_number had no unique index at all, unlike every other document-number
 * column. Combined with the unlocked read-max generator it replaced, two
 * concurrent DO generations could silently save the same number on two
 * shipments. Scoped to (team_id, do_number), matching the convention set by
 * 2026_07_05_130000_scope_document_number_uniqueness_to_team. do_number is
 * nullable and both PostgreSQL and SQLite treat NULLs as distinct for a
 * unique index, so unnumbered shipments are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->unique(['team_id', 'do_number']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'do_number']);
        });
    }
};
