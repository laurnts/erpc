<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\Unit;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ValueError;

/**
 * A safe enum cast for Unit that returns null for invalid enum values
 * instead of throwing an exception. This prevents errors when loading models
 * with invalid unit values like "orang".
 *
 * During migration, this cast supports both:
 * - Legacy string unit values (returns Unit enum)
 * - New unit_of_measure_id foreign keys (loads UnitOfMeasure and returns matching Unit enum)
 */
final readonly class SafeUnitCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Unit
    {
        // First, check if unit_of_measure_id exists (new format)
        if (isset($attributes['unit_of_measure_id']) && $attributes['unit_of_measure_id'] !== null) {
            $unitOfMeasure = UnitOfMeasure::find($attributes['unit_of_measure_id']);

            if ($unitOfMeasure !== null) {
                // Try to match the code to Unit enum
                try {
                    return Unit::from($unitOfMeasure->code);
                } catch (ValueError) {
                    // If code doesn't match enum, return null (custom unit)
                    return null;
                }
            }
        }

        // Fallback to legacy string unit value
        if ($value === null) {
            return null;
        }

        try {
            return Unit::from($value);
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

        if ($value instanceof Unit) {
            return $value->value;
        }

        // If UnitOfMeasure instance is provided, use its code
        if ($value instanceof UnitOfMeasure) {
            return $value->code;
        }

        // Try to cast string value to enum
        try {
            $enum = Unit::from($value);

            return $enum->value;
        } catch (ValueError) {
            // Return null for invalid enum values instead of throwing
            return null;
        }
    }
}
