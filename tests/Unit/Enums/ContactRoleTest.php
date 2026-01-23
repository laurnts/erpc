<?php

declare(strict_types=1);

use App\Enums\ContactRole;

test('ContactRole enum has all expected cases', function (): void {
    $cases = ContactRole::cases();

    expect($cases)->toHaveCount(6)
        ->and($cases)->toContain(ContactRole::PRIMARY)
        ->and($cases)->toContain(ContactRole::BILLING)
        ->and($cases)->toContain(ContactRole::TECHNICAL)
        ->and($cases)->toContain(ContactRole::SALES)
        ->and($cases)->toContain(ContactRole::SUPPORT)
        ->and($cases)->toContain(ContactRole::OTHER);
});

test('ContactRole enum values are correct', function (): void {
    expect(ContactRole::PRIMARY->value)->toBe('primary')
        ->and(ContactRole::BILLING->value)->toBe('billing')
        ->and(ContactRole::TECHNICAL->value)->toBe('technical')
        ->and(ContactRole::SALES->value)->toBe('sales')
        ->and(ContactRole::SUPPORT->value)->toBe('support')
        ->and(ContactRole::OTHER->value)->toBe('other');
});

test('ContactRole implements HasLabel', function (): void {
    expect(ContactRole::PRIMARY)->toBeInstanceOf(\Filament\Support\Contracts\HasLabel::class);
});

test('ContactRole implements HasDescription', function (): void {
    expect(ContactRole::PRIMARY)->toBeInstanceOf(\Filament\Support\Contracts\HasDescription::class);
});

test('ContactRole getLabel returns correct labels', function (): void {
    expect(ContactRole::PRIMARY->getLabel())->toBe('Primary Contact')
        ->and(ContactRole::BILLING->getLabel())->toBe('Billing Contact')
        ->and(ContactRole::TECHNICAL->getLabel())->toBe('Technical Contact')
        ->and(ContactRole::SALES->getLabel())->toBe('Sales Contact')
        ->and(ContactRole::SUPPORT->getLabel())->toBe('Support Contact')
        ->and(ContactRole::OTHER->getLabel())->toBe('Other');
});

test('ContactRole getDescription returns descriptions', function (): void {
    expect(ContactRole::PRIMARY->getDescription())->toBeString()
        ->and(ContactRole::BILLING->getDescription())->toBeString()
        ->and(ContactRole::TECHNICAL->getDescription())->toBeString()
        ->and(ContactRole::SALES->getDescription())->toBeString()
        ->and(ContactRole::SUPPORT->getDescription())->toBeString()
        ->and(ContactRole::OTHER->getDescription())->toBeString();
});

test('ContactRole can be created from string value', function (): void {
    expect(ContactRole::from('primary'))->toBe(ContactRole::PRIMARY)
        ->and(ContactRole::from('billing'))->toBe(ContactRole::BILLING)
        ->and(ContactRole::from('technical'))->toBe(ContactRole::TECHNICAL)
        ->and(ContactRole::from('sales'))->toBe(ContactRole::SALES)
        ->and(ContactRole::from('support'))->toBe(ContactRole::SUPPORT)
        ->and(ContactRole::from('other'))->toBe(ContactRole::OTHER);
});

test('ContactRole tryFrom returns null for invalid value', function (): void {
    expect(ContactRole::tryFrom('invalid'))->toBeNull();
});
