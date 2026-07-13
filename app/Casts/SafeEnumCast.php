<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ValueError;

/**
 * A safe enum cast that returns null for invalid enum values instead of throwing an exception.
 * This is useful during migrations when data might have invalid enum values.
 */
final readonly class SafeEnumCast implements CastsAttributes
{
    /**
     * @param  class-string<\BackedEnum>  $enumClass
     */
    public function __construct(
        private string $enumClass
    ) {}

    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?\BackedEnum
    {
        if ($value === null) {
            return null;
        }

        try {
            return $this->enumClass::from($value);
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

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        // Try to cast string value to enum
        try {
            $enum = $this->enumClass::from($value);

            return $enum->value;
        } catch (ValueError) {
            // Return null for invalid enum values instead of throwing
            return null;
        }
    }
}
