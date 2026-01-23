<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

/**
 * Schema component for approval workflow personnel fields.
 * Uses KeyAccountSelect for consistent key account selection across the application.
 *
 * @deprecated Use ApprovalPersonnelSchema instead. This class is kept for backward compatibility.
 */
final class CentralPurchasingSchema
{
    /**
     * Get the approval workflow schema.
     *
     * @param  int|null  $buyerId  Optional buyer ID to filter key accounts
     * @return array<int, mixed>
     */
    public static function make(?int $buyerId = null): array
    {
        return ApprovalPersonnelSchema::make($buyerId);
    }
}
