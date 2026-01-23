<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum DeliveryType: string implements HasDescription, HasLabel
{
    case FOB = 'fob';
    case CIF = 'cif';
    case EXW = 'exw';
    case DDP = 'ddp';
    case DAP = 'dap';

    public function getLabel(): string
    {
        return match ($this) {
            self::FOB => 'FOB',
            self::CIF => 'CIF',
            self::EXW => 'EXW',
            self::DDP => 'DDP',
            self::DAP => 'DAP',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::FOB => 'Free on Board - Seller delivers goods on board the vessel',
            self::CIF => 'Cost, Insurance & Freight - Seller pays for delivery to destination port',
            self::EXW => 'Ex Works - Buyer bears all costs from seller\'s premises',
            self::DDP => 'Delivered Duty Paid - Seller delivers goods cleared for import',
            self::DAP => 'Delivered at Place - Seller delivers goods ready for unloading',
        };
    }

    /**
     * Get the full name of the Incoterm.
     */
    public function getFullName(): string
    {
        return match ($this) {
            self::FOB => 'Free on Board',
            self::CIF => 'Cost, Insurance and Freight',
            self::EXW => 'Ex Works',
            self::DDP => 'Delivered Duty Paid',
            self::DAP => 'Delivered at Place',
        };
    }

    /**
     * Get the label with full description for detailed views.
     */
    public function getLabelWithDescription(): string
    {
        return $this->getLabel().' - '.$this->getFullName();
    }

    /**
     * Check if seller is responsible for shipping.
     */
    public function sellerPaysShipping(): bool
    {
        return in_array($this, [self::CIF, self::DDP, self::DAP], true);
    }

    /**
     * Check if seller is responsible for insurance.
     */
    public function sellerPaysInsurance(): bool
    {
        return in_array($this, [self::CIF, self::DDP], true);
    }
}
