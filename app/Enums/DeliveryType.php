<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum DeliveryType: string implements HasDescription, HasLabel
{
    case FOB = 'fob';
    case CIF = 'cif';
    case CFR = 'cfr';
    case EXW = 'exw';
    case DDP = 'ddp';
    case DAP = 'dap';
    case DTH = 'dth'; // Delivered to House - Seller bears all costs until buyer's warehouse
    case W2W = 'w2w'; // Warehouse to Warehouse - Buyer bears all costs from seller's warehouse

    public function getLabel(): string
    {
        return match ($this) {
            self::FOB => 'FOB',
            self::CIF => 'CIF',
            self::CFR => 'CFR',
            self::EXW => 'EXW',
            self::DDP => 'DDP',
            self::DAP => 'DAP',
            self::DTH => 'DTH',
            self::W2W => 'W2W',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::FOB => 'Free on Board - Seller delivers goods on board the vessel',
            self::CIF => 'Cost, Insurance & Freight - Seller pays for delivery to destination port',
            self::CFR => 'Cost and Freight - Seller pays for delivery to destination port (buyer arranges insurance)',
            self::EXW => 'Ex Works - Buyer bears all costs from seller\'s premises',
            self::DDP => 'Delivered Duty Paid - Seller delivers goods cleared for import',
            self::DAP => 'Delivered at Place - Seller delivers goods ready for unloading',
            self::DTH => 'Delivered to House - Seller bears all costs and risks until goods arrive at buyer\'s warehouse',
            self::W2W => 'Warehouse to Warehouse - Buyer bears all costs and risks from seller\'s warehouse',
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
            self::CFR => 'Cost and Freight',
            self::EXW => 'Ex Works',
            self::DDP => 'Delivered Duty Paid',
            self::DAP => 'Delivered at Place',
            self::DTH => 'Delivered to House',
            self::W2W => 'Warehouse to Warehouse',
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
        return in_array($this, [self::CIF, self::CFR, self::DDP, self::DAP, self::DTH], true);
    }

    /**
     * Check if seller is responsible for insurance.
     */
    public function sellerPaysInsurance(): bool
    {
        return in_array($this, [self::CIF, self::DDP, self::DTH], true);
    }
}
