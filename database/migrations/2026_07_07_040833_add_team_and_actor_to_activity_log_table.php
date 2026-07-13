<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private readonly string $table;

    public function __construct()
    {
        $this->table = (string) config('activitylog.table_name', 'activity_log');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table($this->table, function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->after('causer_id')->constrained('teams')->nullOnDelete();
            $table->string('actor_type')->nullable()->after('team_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table($this->table, function (Blueprint $table): void {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn('actor_type');
        });
    }
};
