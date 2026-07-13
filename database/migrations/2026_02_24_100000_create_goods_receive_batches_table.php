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
        Schema::create('goods_receive_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('supplier_order_id')->constrained('supplier_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('media_ids')->comment('Array of media IDs in this batch');
            $table->timestamps();

            $table->index('request_id');
        });

        $this->migrateExistingMediaToBatches();
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receive_batches');
    }

    private function migrateExistingMediaToBatches(): void
    {
        $media = DB::table('media')
            ->where('collection_name', 'goods_receive')
            ->where('model_type', \App\Models\Request::class)
            ->get();

        foreach ($media as $m) {
            $requestId = (int) $m->model_id;
            $props = json_decode((string) $m->custom_properties, true);
            $supplierOrderId = isset($props['supplier_order_id']) ? (int) $props['supplier_order_id'] : null;
            $userId = isset($props['uploaded_by']) ? (int) $props['uploaded_by'] : null;
            if ($supplierOrderId === null) {
                continue;
            }
            if ($userId === null) {
                continue;
            }

            $orderExists = DB::table('supplier_orders')->where('id', $supplierOrderId)->where('request_id', $requestId)->exists();
            $userExists = DB::table('users')->where('id', $userId)->exists();
            if (! $orderExists) {
                continue;
            }
            if (! $userExists) {
                continue;
            }

            DB::table('goods_receive_batches')->insert([
                'request_id' => $requestId,
                'supplier_order_id' => $supplierOrderId,
                'user_id' => $userId,
                'media_ids' => json_encode([(int) $m->id]),
                'created_at' => $m->created_at ?? now(),
                'updated_at' => $m->updated_at ?? now(),
            ]);
        }
    }
};
