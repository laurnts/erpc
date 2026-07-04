<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_registration_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('company_name');
            $table->string('phone', 50)->nullable();
            $table->text('message')->nullable();
            $table->string('password');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Uniqueness of a pending application per email is enforced in
            // validation, not schema (re-application after rejection is legal).
            $table->index(['team_id', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_registration_requests');
    }
};
