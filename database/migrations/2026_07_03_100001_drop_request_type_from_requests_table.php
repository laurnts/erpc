<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'request_type']);
            $table->dropColumn('request_type');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->string('request_type', 20)->default('goods')->after('priority');
            $table->index(['team_id', 'request_type']);
        });

        // Conservative inverse: a request is "services" only if every item is a
        // services item (mixed requests did not exist before item-level types).
        DB::statement(<<<'SQL'
            UPDATE requests
            SET request_type = 'services'
            WHERE EXISTS (
                SELECT 1 FROM request_items WHERE request_items.request_id = requests.id
            )
            AND NOT EXISTS (
                SELECT 1 FROM request_items
                WHERE request_items.request_id = requests.id
                AND request_items.item_type != 'services'
            )
        SQL);
    }
};
