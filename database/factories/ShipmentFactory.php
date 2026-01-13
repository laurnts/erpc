<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\BuyerOrder;
use App\Models\Request;
use App\Models\Shipment;
use App\Models\SupplierOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
final class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = date('Y');

        return [
            'shipment_number' => 'SHP-'.$year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'type' => ShipmentType::INBOUND,
            'status' => ShipmentStatus::PENDING,
            'carrier_name' => $this->faker->optional()->randomElement(['DHL', 'FedEx', 'UPS', 'TNT', 'Local Courier']),
            'tracking_number' => $this->faker->optional()->bothify('TRK-????-####-????'),
            'shipped_at' => null,
            'expected_delivery_at' => null,
            'delivered_at' => null,
            'notes' => $this->faker->optional()->sentence(),
            'team_id' => Team::factory(),
            'creator_id' => User::factory(),
            'request_id' => Request::factory(),
            'supplier_order_id' => null,
            'buyer_order_id' => null,
        ];
    }

    /**
     * Create an inbound shipment.
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ShipmentType::INBOUND,
            'buyer_order_id' => null,
        ]);
    }

    /**
     * Create an outbound shipment.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ShipmentType::OUTBOUND,
            'supplier_order_id' => null,
        ]);
    }

    /**
     * Set pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShipmentStatus::PENDING,
            'shipped_at' => null,
            'delivered_at' => null,
        ]);
    }

    /**
     * Set in transit status.
     */
    public function inTransit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShipmentStatus::IN_TRANSIT,
            'shipped_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'expected_delivery_at' => $this->faker->dateTimeBetween('now', '+14 days'),
            'delivered_at' => null,
        ]);
    }

    /**
     * Set delivered status.
     */
    public function delivered(): static
    {
        return $this->state(function (array $attributes): array {
            $shippedAt = $this->faker->dateTimeBetween('-14 days', '-3 days');

            return [
                'status' => ShipmentStatus::DELIVERED,
                'shipped_at' => $shippedAt,
                'delivered_at' => $this->faker->dateTimeBetween($shippedAt, 'now'),
            ];
        });
    }

    /**
     * Set partial delivery status.
     */
    public function partial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShipmentStatus::PARTIAL,
            'shipped_at' => $this->faker->dateTimeBetween('-14 days', '-3 days'),
        ]);
    }

    /**
     * Set failed status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShipmentStatus::FAILED,
            'notes' => $this->faker->sentence().' Failure reason: '.$this->faker->sentence(),
        ]);
    }

    /**
     * Set specific status.
     */
    public function withStatus(ShipmentStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    /**
     * Associate with a specific request.
     */
    public function forRequest(?Request $request = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_id' => $request ?? Request::factory(),
        ]);
    }

    /**
     * Associate with a supplier order (inbound).
     */
    public function forSupplierOrder(?SupplierOrder $supplierOrder = null): static
    {
        return $this->state(function (array $attributes) use ($supplierOrder): array {
            $order = $supplierOrder ?? SupplierOrder::factory()->create();

            return [
                'type' => ShipmentType::INBOUND,
                'supplier_order_id' => $order->getKey(),
                'buyer_order_id' => null,
                'request_id' => $order->request_id,
                'team_id' => $order->team_id,
            ];
        });
    }

    /**
     * Associate with a buyer order (outbound).
     */
    public function forBuyerOrder(?BuyerOrder $buyerOrder = null): static
    {
        return $this->state(function (array $attributes) use ($buyerOrder): array {
            $order = $buyerOrder ?? BuyerOrder::factory()->create();

            return [
                'type' => ShipmentType::OUTBOUND,
                'buyer_order_id' => $order->getKey(),
                'supplier_order_id' => null,
                'request_id' => $order->request_id,
                'team_id' => $order->team_id,
            ];
        });
    }

    /**
     * Set carrier information.
     */
    public function withCarrier(string $carrierName, ?string $trackingNumber = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'carrier_name' => $carrierName,
            'tracking_number' => $trackingNumber ?? $this->faker->bothify('TRK-????-####-????'),
        ]);
    }

    /**
     * Set expected delivery date.
     */
    public function withExpectedDelivery(\DateTimeInterface|string|null $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'expected_delivery_at' => $date ?? $this->faker->dateTimeBetween('now', '+14 days'),
        ]);
    }
}
