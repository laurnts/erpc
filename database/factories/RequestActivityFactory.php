<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Request;
use App\Models\RequestActivity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<RequestActivity>
 */
final class RequestActivityFactory extends Factory
{
    protected $model = RequestActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'request_id' => Request::factory(),
            'user_id' => User::factory(),
            'activity_type' => $this->faker->randomElement(ActivityType::cases()),
            'description' => $this->faker->sentence(),
            'subject_type' => null,
            'subject_id' => null,
            'metadata' => null,
        ];
    }

    /**
     * Set a specific request.
     */
    public function forRequest(?Request $request = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'request_id' => $request ?? Request::factory(),
        ]);
    }

    /**
     * Set a specific user.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user ?? User::factory(),
        ]);
    }

    /**
     * Set the activity as system-generated (no user).
     */
    public function systemGenerated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }

    /**
     * Set a specific activity type.
     */
    public function withType(ActivityType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => $type,
        ]);
    }

    /**
     * Set metadata for the activity.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Set a subject entity for the activity.
     */
    public function withSubject(Model $subject): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    /**
     * Create a request created activity.
     */
    public function requestCreated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::REQUEST_CREATED,
            'description' => 'Request was created',
        ]);
    }

    /**
     * Create a stage changed activity.
     */
    public function stageChanged(?string $from = null, ?string $to = null): static
    {
        $description = 'Stage was changed';
        if ($from !== null && $to !== null) {
            $description = sprintf('Stage changed from %s to %s', $from, $to);
        }

        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::STAGE_CHANGED,
            'description' => $description,
            'metadata' => ($from !== null && $to !== null) ? ['from' => $from, 'to' => $to] : null,
        ]);
    }

    /**
     * Create an item added activity.
     */
    public function itemAdded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::ITEM_ADDED,
            'description' => 'Item was added to the request',
        ]);
    }

    /**
     * Create an item matched activity.
     */
    public function itemMatched(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::ITEM_MATCHED,
            'description' => 'Item was matched to an article',
        ]);
    }

    /**
     * Create a supplier quote received activity.
     */
    public function supplierQuoteReceived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::SUPPLIER_QUOTE_RECEIVED,
            'description' => 'Supplier quote was received',
        ]);
    }

    /**
     * Create a buyer quote sent activity.
     */
    public function buyerQuoteSent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::BUYER_QUOTE_SENT,
            'description' => 'Buyer quote was sent',
        ]);
    }

    /**
     * Create a buyer order created activity.
     */
    public function buyerOrderCreated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::BUYER_ORDER_CREATED,
            'description' => 'Buyer order was created',
        ]);
    }

    /**
     * Create a supplier order created activity.
     */
    public function supplierOrderCreated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::SUPPLIER_ORDER_CREATED,
            'description' => 'Supplier order was created',
        ]);
    }

    /**
     * Create a shipment created activity.
     */
    public function shipmentCreated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::SHIPMENT_CREATED,
            'description' => 'Shipment was created',
        ]);
    }

    /**
     * Create a shipment delivered activity.
     */
    public function shipmentDelivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::SHIPMENT_DELIVERED,
            'description' => 'Shipment was delivered',
        ]);
    }

    /**
     * Create a payment received activity.
     */
    public function paymentReceived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::PAYMENT_RECEIVED,
            'description' => 'Payment was received',
        ]);
    }

    /**
     * Create a payment made activity.
     */
    public function paymentMade(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::PAYMENT_MADE,
            'description' => 'Payment was made',
        ]);
    }

    /**
     * Create a note added activity.
     */
    public function noteAdded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::NOTE_ADDED,
            'description' => 'Note was added',
        ]);
    }

    /**
     * Create a task completed activity.
     */
    public function taskCompleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_type' => ActivityType::TASK_COMPLETED,
            'description' => 'Task was completed',
        ]);
    }
}
