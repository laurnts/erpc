<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();

            // Shipment type and status
            $table->string('type')->default(ShipmentType::INBOUND->value);
            $table->string('status')->default(ShipmentStatus::PENDING->value);

            // Order references (one or the other based on type)
            $table->foreignId('supplier_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_order_id')->nullable()->constrained()->cascadeOnDelete();

            // Shipment details
            $table->string('shipment_number')->unique();
            $table->string('carrier_name')->nullable();
            $table->string('tracking_number')->nullable();

            // Dates
            $table->datetime('shipped_at')->nullable();
            $table->datetime('expected_delivery_at')->nullable();
            $table->datetime('delivered_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'status']);
            $table->index(['request_id', 'type']);
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
