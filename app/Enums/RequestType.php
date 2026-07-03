<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RequestType: string implements HasColor, HasIcon, HasLabel
{
    case GOODS = 'goods';
    case SERVICE = 'services';

    public function getLabel(): string
    {
        return match ($this) {
            self::GOODS => 'Goods',
            self::SERVICE => 'Services',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GOODS => 'primary',
            self::SERVICE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::GOODS => 'heroicon-o-cube',
            self::SERVICE => 'heroicon-o-wrench-screwdriver',
        };
    }

    /**
     * Whether request items form a main/child hierarchy and totals
     * roll up from main items only (children are informational).
     */
    public function supportsItemHierarchy(): bool
    {
        return match ($this) {
            self::GOODS => false,
            self::SERVICE => true,
        };
    }

    /**
     * Whether fulfillment is confirmed via acceptance reports.
     */
    public function usesAcceptanceReports(): bool
    {
        return match ($this) {
            self::GOODS => false,
            self::SERVICE => true,
        };
    }

    /**
     * Whether fulfillment happens through physical shipments.
     */
    public function requiresShipments(): bool
    {
        return match ($this) {
            self::GOODS => true,
            self::SERVICE => false,
        };
    }

    /**
     * Whether quote items track job progress.
     */
    public function hasJobProgress(): bool
    {
        return match ($this) {
            self::GOODS => false,
            self::SERVICE => true,
        };
    }

    /**
     * Whether supplier quotes are compared via a Quotation Evaluation document.
     */
    public function usesQuotationEvaluation(): bool
    {
        return match ($this) {
            self::GOODS => true,
            self::SERVICE => false,
        };
    }

    /**
     * Request types whose fulfillment is confirmed via acceptance reports,
     * for use in query constraints.
     *
     * @return list<self>
     */
    public static function casesUsingAcceptanceReports(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type->usesAcceptanceReports(),
        ));
    }
}
