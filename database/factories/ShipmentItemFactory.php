<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Models\BuyerOrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\SupplierOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentItem>
 */
final class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantityShipped = $this->faker->randomFloat(4, 1, 100);

        return [
            'shipment_id' => Shipment::factory(),
            'supplier_order_item_id' => null,
            'buyer_order_item_id' => null,
            'quantity_shipped' => (string) round($quantityShipped, 4),
            'quantity_received' => null,
            'condition' => ItemCondition::GOOD,
            'condition_notes' => null,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Associate with a specific shipment.
     */
    public function forShipment(?Shipment $shipment = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'shipment_id' => $shipment ?? Shipment::factory(),
        ]);
    }

    /**
     * Associate with a supplier order item (for inbound shipments).
     */
    public function forSupplierOrderItem(?SupplierOrderItem $item = null): static
    {
        return $this->state(function (array $attributes) use ($item): array {
            $orderItem = $item ?? SupplierOrderItem::factory()->create();

            return [
                'supplier_order_item_id' => $orderItem->getKey(),
                'buyer_order_item_id' => null,
                'quantity_shipped' => $orderItem->quantity,
            ];
        });
    }

    /**
     * Associate with a buyer order item (for outbound shipments).
     */
    public function forBuyerOrderItem(?BuyerOrderItem $item = null): static
    {
        return $this->state(function (array $attributes) use ($item): array {
            $orderItem = $item ?? BuyerOrderItem::factory()->create();

            return [
                'buyer_order_item_id' => $orderItem->getKey(),
                'supplier_order_item_id' => null,
                'quantity_shipped' => $orderItem->quantity,
            ];
        });
    }

    /**
     * Set shipped quantity.
     */
    public function withQuantityShipped(float $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity_shipped' => (string) round($quantity, 4),
        ]);
    }

    /**
     * Set received quantity (marking as received).
     */
    public function received(?float $quantityReceived = null): static
    {
        return $this->state(function (array $attributes) use ($quantityReceived): array {
            $shipped = (float) ($attributes['quantity_shipped'] ?? 0);
            $received = $quantityReceived ?? $shipped;

            return [
                'quantity_received' => (string) round($received, 4),
            ];
        });
    }

    /**
     * Set fully received.
     */
    public function fullyReceived(): static
    {
        return $this->afterMaking(function (ShipmentItem $item): void {
            $item->quantity_received = $item->quantity_shipped;
            $item->condition = ItemCondition::GOOD;
        })->afterCreating(function (ShipmentItem $item): void {
            // Ensure quantity_received matches quantity_shipped after creation
            if ($item->quantity_received !== $item->quantity_shipped) {
                $item->quantity_received = $item->quantity_shipped;
                $item->condition = ItemCondition::GOOD;
                $item->save();
            }
        });
    }

    /**
     * Set partially received.
     */
    public function partiallyReceived(float $percentReceived = 50): static
    {
        return $this->state(function (array $attributes) use ($percentReceived): array {
            $shipped = (float) ($attributes['quantity_shipped'] ?? 0);
            $received = $shipped * ($percentReceived / 100);

            return [
                'quantity_received' => (string) round($received, 4),
            ];
        });
    }

    /**
     * Set condition to good.
     */
    public function good(): static
    {
        return $this->state(fn (array $attributes): array => [
            'condition' => ItemCondition::GOOD,
            'condition_notes' => null,
        ]);
    }

    /**
     * Set condition to damaged.
     */
    public function damaged(?string $notes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'condition' => ItemCondition::DAMAGED,
            'condition_notes' => $notes ?? $this->faker->sentence(),
        ]);
    }

    /**
     * Set condition to rejected.
     */
    public function rejected(?string $notes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'condition' => ItemCondition::REJECTED,
            'condition_notes' => $notes ?? $this->faker->sentence(),
        ]);
    }

    /**
     * Set specific condition.
     */
    public function withCondition(ItemCondition $condition, ?string $notes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'condition' => $condition,
            'condition_notes' => $notes ?? ($condition !== ItemCondition::GOOD ? $this->faker->sentence() : null),
        ]);
    }

    /**
     * Set sort order.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $position,
        ]);
    }
}
