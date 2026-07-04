<?php

declare(strict_types=1);

use App\Support\SafeCast;

describe('toFloat()', function (): void {
    it('returns the default for invalid values', function (mixed $value): void {
        expect(SafeCast::toFloat($value, 1.5))->toBe(1.5);
    })->with([
        'null' => [null],
        'empty string' => [''],
        'non-numeric string' => ['abc'],
        'array' => [['1.5']],
        'bool' => [true],
    ]);

    it('passes through valid values', function (mixed $value, float $expected): void {
        expect(SafeCast::toFloat($value))->toBe($expected);
    })->with([
        'int' => [5, 5.0],
        'float' => [2.5, 2.5],
        'numeric string' => ['3.14', 3.14],
        'integer string' => ['42', 42.0],
        'negative numeric string' => ['-2.5', -2.5],
    ]);

    it('uses the default value of 0.0 when omitted', function (): void {
        expect(SafeCast::toFloat(null))->toBe(0.0);
    });
});

describe('toInt()', function (): void {
    it('returns the default for invalid values', function (mixed $value): void {
        expect(SafeCast::toInt($value, 7))->toBe(7);
    })->with([
        'null' => [null],
        'empty string' => [''],
        'non-numeric string' => ['abc'],
        'array' => [[1]],
        'bool' => [false],
    ]);

    it('passes through valid values', function (mixed $value, int $expected): void {
        expect(SafeCast::toInt($value))->toBe($expected);
    })->with([
        'int' => [5, 5],
        'float' => [2.9, 2],
        'integer string' => ['42', 42],
        'numeric string with decimals' => ['3.14', 3],
        'negative numeric string' => ['-10', -10],
    ]);

    it('uses the default value of 0 when omitted', function (): void {
        expect(SafeCast::toInt(null))->toBe(0);
    });
});

describe('toString()', function (): void {
    it('returns the default for invalid values', function (mixed $value): void {
        expect(SafeCast::toString($value, 'fallback'))->toBe('fallback');
    })->with([
        'null' => [null],
        'array' => [['a', 'b']],
        'true' => [true],
        'false' => [false],
    ]);

    it('passes through valid values', function (mixed $value, string $expected): void {
        expect(SafeCast::toString($value))->toBe($expected);
    })->with([
        'string' => ['hello', 'hello'],
        'empty string' => ['', ''],
        'int' => [42, '42'],
        'float' => [3.14, '3.14'],
    ]);

    it('accepts Stringable objects', function (): void {
        $stringable = new class implements Stringable
        {
            public function __toString(): string
            {
                return 'stringable value';
            }
        };

        expect(SafeCast::toString($stringable))->toBe('stringable value');
    });

    it('uses the default value of an empty string when omitted', function (): void {
        expect(SafeCast::toString(null))->toBe('');
    });
});

describe('toBool()', function (): void {
    it('returns the default for invalid values', function (mixed $value): void {
        expect(SafeCast::toBool($value, true))->toBeTrue();
    })->with([
        'null' => [null],
        'empty string' => [''],
        'non-numeric string' => ['abc'],
        'array' => [[]],
        'other int' => [2],
    ]);

    it('passes through valid values', function (mixed $value, bool $expected): void {
        expect(SafeCast::toBool($value))->toBe($expected);
    })->with([
        'true' => [true, true],
        'false' => [false, false],
        'int 1' => [1, true],
        'int 0' => [0, false],
        'string 1' => ['1', true],
        'string 0' => ['0', false],
        'string true' => ['true', true],
        'string false' => ['false', false],
    ]);

    it('uses the default value of false when omitted', function (): void {
        expect(SafeCast::toBool(null))->toBeFalse();
    });
});

describe('toArray()', function (): void {
    it('returns the default for invalid values', function (mixed $value): void {
        expect(SafeCast::toArray($value, ['fallback']))->toBe(['fallback']);
    })->with([
        'null' => [null],
        'empty string' => [''],
        'string' => ['not an array'],
        'int' => [42],
        'bool' => [true],
    ]);

    it('passes through arrays', function (): void {
        expect(SafeCast::toArray(['a' => 1, 'b' => 2]))->toBe(['a' => 1, 'b' => 2]);
    });

    it('passes through an empty array', function (): void {
        expect(SafeCast::toArray([]))->toBe([]);
    });

    it('uses the default value of an empty array when omitted', function (): void {
        expect(SafeCast::toArray(null))->toBe([]);
    });
});
