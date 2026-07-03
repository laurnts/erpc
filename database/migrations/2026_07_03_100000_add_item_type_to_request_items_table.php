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
        Schema::table('request_items', function (Blueprint $table): void {
            $table->string('item_type', 20)->default('goods')->after('description');
            $table->index(['request_id', 'item_type']);
        });

        if (Schema::hasColumn('requests', 'request_type')) {
            DB::statement(<<<'SQL'
                UPDATE request_items
                SET item_type = requests.request_type
                FROM requests
                WHERE requests.id = request_items.request_id
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table): void {
            $table->dropIndex(['request_id', 'item_type']);
            $table->dropColumn('item_type');
        });
    }
};
