<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('people_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'people_id']);
        });

        // Migrate existing company_id data from people table to pivot
        $now = now()->toDateTimeString();
        DB::table('people')
            ->whereNotNull('company_id')
            ->get(['id', 'company_id'])
            ->each(function ($person) use ($now): void {
                DB::table('company_people')->insert([
                    'company_id' => $person->company_id,
                    'people_id' => $person->id,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_people');
    }
};
