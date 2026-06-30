<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->string('submission_method', 20)->nullable()->after('request_type');
            $table->timestamp('submitted_at')->nullable()->after('submission_method');
            $table->foreignId('submitted_by_user_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();

            $table->index(['team_id', 'submission_method']);
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by_user_id']);
            $table->dropIndex(['team_id', 'submission_method']);
            $table->dropColumn(['submission_method', 'submitted_at', 'submitted_by_user_id']);
        });
    }
};
