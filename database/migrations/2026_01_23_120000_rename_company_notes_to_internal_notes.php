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
     * Renames the 'notes' column to 'internal_notes' to avoid conflict
     * with the notes() relationship from HasNotes trait which links
     * to Note entities.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->renameColumn('notes', 'internal_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->renameColumn('internal_notes', 'notes');
        });
    }
};
