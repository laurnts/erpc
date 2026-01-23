<?php

declare(strict_types=1);

use App\Enums\PrepaymentType;

uses()->group('enum');

test('prepayment type has expected cases', function () {
    $cases = PrepaymentType::cases();

    expect($cases)->toHaveCount(2)
        ->and(PrepaymentType::PERCENT)->toBeInstanceOf(PrepaymentType::class)
        ->and(PrepaymentType::FIXED)->toBeInstanceOf(PrepaymentType::class);
});

test('prepayment type has correct values', function () {
    expect(PrepaymentType::PERCENT->value)->toBe('percent')
        ->and(PrepaymentType::FIXED->value)->toBe('fixed');
});

test('prepayment type get label returns non-empty strings', function () {
    foreach (PrepaymentType::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getLabel())->not->toBeEmpty();
    }
});

test('prepayment type get label returns expected values', function () {
    expect(PrepaymentType::PERCENT->getLabel())->toBe('Percentage')
        ->and(PrepaymentType::FIXED->getLabel())->toBe('Fixed Amount');
});

test('prepayment type get color returns valid filament colors', function () {
    expect(PrepaymentType::PERCENT->getColor())->toBe('info')
        ->and(PrepaymentType::FIXED->getColor())->toBe('success');
});

test('prepayment type get suffix returns expected values', function () {
    expect(PrepaymentType::PERCENT->getSuffix())->toBe('%')
        ->and(PrepaymentType::FIXED->getSuffix())->toBe('');
});

test('prepayment type get max value returns correct limits', function () {
    expect(PrepaymentType::PERCENT->getMaxValue())->toBe(100.0)
        ->and(PrepaymentType::FIXED->getMaxValue())->toBeNull();
});

test('prepayment type implements has label interface', function () {
    expect(class_implements(PrepaymentType::class))->toContain(\Filament\Support\Contracts\HasLabel::class);
});

test('prepayment type implements has color interface', function () {
    expect(class_implements(PrepaymentType::class))->toContain(\Filament\Support\Contracts\HasColor::class);
});
