<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\DeliveryType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ValueError;

/**
 * A safe enum cast for DeliveryType that returns null for invalid enum values
 * instead of throwing an exception. This prevents errors when loading models
 * with invalid delivery_type values like "Franco".
 */
final readonly class SafeDeliveryTypeCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?DeliveryType
    {
        if ($value === null) {
            return null;
        }

        try {
            return DeliveryType::from($value);
        } catch (ValueError) {
            // Return null for invalid enum values instead of throwing
            return null;
        }
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DeliveryType) {
            return $value->value;
        }

        // Try to cast string value to enum
        try {
            $enum = DeliveryType::from($value);

            return $enum->value;
        } catch (ValueError) {
            // Return null for invalid enum values instead of throwing
            return null;
        }
    }
}
