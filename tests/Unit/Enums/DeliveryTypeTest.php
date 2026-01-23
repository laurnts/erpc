<?php

declare(strict_types=1);

use App\Enums\DeliveryType;

uses()->group('enum');

test('delivery type has expected cases', function () {
    $cases = DeliveryType::cases();

    expect($cases)->toHaveCount(5)
        ->and(DeliveryType::FOB)->toBeInstanceOf(DeliveryType::class)
        ->and(DeliveryType::CIF)->toBeInstanceOf(DeliveryType::class)
        ->and(DeliveryType::EXW)->toBeInstanceOf(DeliveryType::class)
        ->and(DeliveryType::DDP)->toBeInstanceOf(DeliveryType::class)
        ->and(DeliveryType::DAP)->toBeInstanceOf(DeliveryType::class);
});

test('delivery type has correct values', function () {
    expect(DeliveryType::FOB->value)->toBe('fob')
        ->and(DeliveryType::CIF->value)->toBe('cif')
        ->and(DeliveryType::EXW->value)->toBe('exw')
        ->and(DeliveryType::DDP->value)->toBe('ddp')
        ->and(DeliveryType::DAP->value)->toBe('dap');
});

test('delivery type get label returns non-empty strings', function () {
    foreach (DeliveryType::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getLabel())->not->toBeEmpty();
    }
});

test('delivery type get label returns expected values', function () {
    expect(DeliveryType::FOB->getLabel())->toBe('FOB')
        ->and(DeliveryType::CIF->getLabel())->toBe('CIF')
        ->and(DeliveryType::EXW->getLabel())->toBe('EXW')
        ->and(DeliveryType::DDP->getLabel())->toBe('DDP')
        ->and(DeliveryType::DAP->getLabel())->toBe('DAP');
});

test('delivery type get description returns non-empty strings', function () {
    foreach (DeliveryType::cases() as $case) {
        expect($case->getDescription())->toBeString()
            ->and($case->getDescription())->not->toBeEmpty();
    }
});

test('delivery type get description returns expected values', function () {
    expect(DeliveryType::FOB->getDescription())->toBe('Free on Board - Seller delivers goods on board the vessel')
        ->and(DeliveryType::CIF->getDescription())->toBe('Cost, Insurance & Freight - Seller pays for delivery to destination port')
        ->and(DeliveryType::EXW->getDescription())->toBe("Ex Works - Buyer bears all costs from seller's premises")
        ->and(DeliveryType::DDP->getDescription())->toBe('Delivered Duty Paid - Seller delivers goods cleared for import')
        ->and(DeliveryType::DAP->getDescription())->toBe('Delivered at Place - Seller delivers goods ready for unloading');
});

test('delivery type get full name returns non-empty strings', function () {
    foreach (DeliveryType::cases() as $case) {
        expect($case->getFullName())->toBeString()
            ->and($case->getFullName())->not->toBeEmpty();
    }
});

test('delivery type get full name returns expected values', function () {
    expect(DeliveryType::FOB->getFullName())->toBe('Free on Board')
        ->and(DeliveryType::CIF->getFullName())->toBe('Cost, Insurance and Freight')
        ->and(DeliveryType::EXW->getFullName())->toBe('Ex Works')
        ->and(DeliveryType::DDP->getFullName())->toBe('Delivered Duty Paid')
        ->and(DeliveryType::DAP->getFullName())->toBe('Delivered at Place');
});

test('delivery type get label with description combines label and full name', function () {
    expect(DeliveryType::FOB->getLabelWithDescription())->toBe('FOB - Free on Board')
        ->and(DeliveryType::CIF->getLabelWithDescription())->toBe('CIF - Cost, Insurance and Freight')
        ->and(DeliveryType::EXW->getLabelWithDescription())->toBe('EXW - Ex Works')
        ->and(DeliveryType::DDP->getLabelWithDescription())->toBe('DDP - Delivered Duty Paid')
        ->and(DeliveryType::DAP->getLabelWithDescription())->toBe('DAP - Delivered at Place');
});

test('delivery type seller pays shipping identifies correct types', function () {
    expect(DeliveryType::CIF->sellerPaysShipping())->toBeTrue()
        ->and(DeliveryType::DDP->sellerPaysShipping())->toBeTrue()
        ->and(DeliveryType::DAP->sellerPaysShipping())->toBeTrue()
        ->and(DeliveryType::FOB->sellerPaysShipping())->toBeFalse()
        ->and(DeliveryType::EXW->sellerPaysShipping())->toBeFalse();
});

test('delivery type seller pays insurance identifies correct types', function () {
    expect(DeliveryType::CIF->sellerPaysInsurance())->toBeTrue()
        ->and(DeliveryType::DDP->sellerPaysInsurance())->toBeTrue()
        ->and(DeliveryType::FOB->sellerPaysInsurance())->toBeFalse()
        ->and(DeliveryType::EXW->sellerPaysInsurance())->toBeFalse()
        ->and(DeliveryType::DAP->sellerPaysInsurance())->toBeFalse();
});

test('delivery type implements has label interface', function () {
    expect(class_implements(DeliveryType::class))->toContain(\Filament\Support\Contracts\HasLabel::class);
});

test('delivery type implements has description interface', function () {
    expect(class_implements(DeliveryType::class))->toContain(\Filament\Support\Contracts\HasDescription::class);
});
