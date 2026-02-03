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
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('type', 50); // buyer_quote, buyer_order, supplier_order, delivery_order
            $table->string('name');
            $table->text('content');
            $table->string('sender_email')->nullable();
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('team_id');
            $table->index('type');
            $table->index(['team_id', 'type']);

            // Unique constraint: team_id + type + name (allow same name for different teams/types)
            $table->unique(['team_id', 'type', 'name'], 'email_templates_team_type_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
