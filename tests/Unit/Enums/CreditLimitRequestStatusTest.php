<?php

declare(strict_types=1);

use App\Enums\CreditLimitRequestStatus;

uses()->group('enum');

test('credit limit request status has expected cases', function () {
    $cases = CreditLimitRequestStatus::cases();

    expect($cases)->toHaveCount(3)
        ->and(CreditLimitRequestStatus::PENDING)->toBeInstanceOf(CreditLimitRequestStatus::class)
        ->and(CreditLimitRequestStatus::APPROVED)->toBeInstanceOf(CreditLimitRequestStatus::class)
        ->and(CreditLimitRequestStatus::REJECTED)->toBeInstanceOf(CreditLimitRequestStatus::class);
});

test('credit limit request status has correct values', function () {
    expect(CreditLimitRequestStatus::PENDING->value)->toBe('pending')
        ->and(CreditLimitRequestStatus::APPROVED->value)->toBe('approved')
        ->and(CreditLimitRequestStatus::REJECTED->value)->toBe('rejected');
});

test('credit limit request status get label returns non-empty strings', function () {
    foreach (CreditLimitRequestStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getLabel())->not->toBeEmpty();
    }
});

test('credit limit request status get label returns expected values', function () {
    expect(CreditLimitRequestStatus::PENDING->getLabel())->toBe('Pending')
        ->and(CreditLimitRequestStatus::APPROVED->getLabel())->toBe('Approved')
        ->and(CreditLimitRequestStatus::REJECTED->getLabel())->toBe('Rejected');
});

test('credit limit request status get color returns valid colors', function () {
    expect(CreditLimitRequestStatus::PENDING->getColor())->toBe('warning')
        ->and(CreditLimitRequestStatus::APPROVED->getColor())->toBe('success')
        ->and(CreditLimitRequestStatus::REJECTED->getColor())->toBe('danger');
});

test('credit limit request status get icon returns valid icons', function () {
    expect(CreditLimitRequestStatus::PENDING->getIcon())->toBe('heroicon-o-clock')
        ->and(CreditLimitRequestStatus::APPROVED->getIcon())->toBe('heroicon-o-check-circle')
        ->and(CreditLimitRequestStatus::REJECTED->getIcon())->toBe('heroicon-o-x-circle');
});

test('credit limit request status implements has label interface', function () {
    expect(class_implements(CreditLimitRequestStatus::class))->toContain(\Filament\Support\Contracts\HasLabel::class);
});

test('credit limit request status implements has color interface', function () {
    expect(class_implements(CreditLimitRequestStatus::class))->toContain(\Filament\Support\Contracts\HasColor::class);
});

test('credit limit request status implements has icon interface', function () {
    expect(class_implements(CreditLimitRequestStatus::class))->toContain(\Filament\Support\Contracts\HasIcon::class);
});
