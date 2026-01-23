<?php

declare(strict_types=1);

use App\Enums\Unit;

uses()->group('enum');

test('unit has expected cases', function () {
    $cases = Unit::cases();

    expect($cases)->toHaveCount(9)
        ->and(Unit::PCS)->toBeInstanceOf(Unit::class)
        ->and(Unit::KG)->toBeInstanceOf(Unit::class)
        ->and(Unit::MT)->toBeInstanceOf(Unit::class)
        ->and(Unit::SET)->toBeInstanceOf(Unit::class)
        ->and(Unit::BOX)->toBeInstanceOf(Unit::class)
        ->and(Unit::ROLL)->toBeInstanceOf(Unit::class)
        ->and(Unit::PAIR)->toBeInstanceOf(Unit::class)
        ->and(Unit::L)->toBeInstanceOf(Unit::class)
        ->and(Unit::M)->toBeInstanceOf(Unit::class);
});

test('unit has correct values', function () {
    expect(Unit::PCS->value)->toBe('pcs')
        ->and(Unit::KG->value)->toBe('kg')
        ->and(Unit::MT->value)->toBe('mt')
        ->and(Unit::SET->value)->toBe('set')
        ->and(Unit::BOX->value)->toBe('box')
        ->and(Unit::ROLL->value)->toBe('roll')
        ->and(Unit::PAIR->value)->toBe('pair')
        ->and(Unit::L->value)->toBe('l')
        ->and(Unit::M->value)->toBe('m');
});

test('unit get label returns non-empty strings', function () {
    foreach (Unit::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getLabel())->not->toBeEmpty();
    }
});

test('unit get label returns expected values', function () {
    expect(Unit::PCS->getLabel())->toBe('Pieces')
        ->and(Unit::KG->getLabel())->toBe('Kilograms')
        ->and(Unit::MT->getLabel())->toBe('Metric Tons')
        ->and(Unit::SET->getLabel())->toBe('Sets')
        ->and(Unit::BOX->getLabel())->toBe('Boxes')
        ->and(Unit::ROLL->getLabel())->toBe('Rolls')
        ->and(Unit::PAIR->getLabel())->toBe('Pairs')
        ->and(Unit::L->getLabel())->toBe('Liters')
        ->and(Unit::M->getLabel())->toBe('Meters');
});

test('unit get abbreviation returns the value', function () {
    foreach (Unit::cases() as $case) {
        expect($case->getAbbreviation())->toBe($case->value);
    }
});

test('unit is weight identifies weight units correctly', function () {
    expect(Unit::KG->isWeight())->toBeTrue()
        ->and(Unit::MT->isWeight())->toBeTrue()
        ->and(Unit::PCS->isWeight())->toBeFalse()
        ->and(Unit::L->isWeight())->toBeFalse()
        ->and(Unit::M->isWeight())->toBeFalse();
});

test('unit is volume identifies volume units correctly', function () {
    expect(Unit::L->isVolume())->toBeTrue()
        ->and(Unit::KG->isVolume())->toBeFalse()
        ->and(Unit::M->isVolume())->toBeFalse()
        ->and(Unit::PCS->isVolume())->toBeFalse();
});

test('unit is length identifies length units correctly', function () {
    expect(Unit::M->isLength())->toBeTrue()
        ->and(Unit::KG->isLength())->toBeFalse()
        ->and(Unit::L->isLength())->toBeFalse()
        ->and(Unit::PCS->isLength())->toBeFalse();
});

test('unit implements has label interface', function () {
    expect(class_implements(Unit::class))->toContain(\Filament\Support\Contracts\HasLabel::class);
});
