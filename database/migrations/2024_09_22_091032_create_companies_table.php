<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('set null');

            // Account Owner: Team member responsible for managing the company account
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->onDelete('set null');

            // Default currency for transactions with this company
            $table->foreignId('default_currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            // Auto-generated unique code per team, e.g., CMP-0001
            $table->string('code', 50);

            // Basic company information
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('country')->nullable();

            // Company type flags - a company can be both buyer and supplier
            $table->boolean('is_buyer')->default(false);
            $table->boolean('is_supplier')->default(false);

            // Buyer-specific fields
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('credit_used', 15, 2)->default(0);
            $table->boolean('is_on_hold')->default(false);
            $table->text('on_hold_reason')->nullable();

            // Supplier-specific fields
            $table->integer('lead_time_days')->default(0)->comment('Default lead time in days for this supplier');

            // Shared fields
            $table->integer('payment_terms_days')->default(30)->comment('Default payment terms in days');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('creation_source')->default('web');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'is_buyer']);
            $table->index(['team_id', 'is_supplier']);
            $table->index(['team_id', 'is_on_hold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
