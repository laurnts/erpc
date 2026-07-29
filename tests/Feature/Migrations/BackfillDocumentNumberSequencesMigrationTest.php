<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * database/migrations/2026_07_29_140000_backfill_document_number_sequences.php
 * calls erp:backfill-document-sequences and must not swallow a failed run —
 * a discarded non-zero exit code would still let `migrate` mark this
 * migration applied, leaving counters unseeded with no signal that anything
 * went wrong.
 */
it('throws when the backfill command exits non-zero', function (): void {
    Artisan::shouldReceive('call')
        ->once()
        ->with('erp:backfill-document-sequences')
        ->andReturn(1);

    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('something went wrong');

    $migration = require database_path('migrations/2026_07_29_140000_backfill_document_number_sequences.php');

    expect(fn (): mixed => $migration->up())->toThrow(RuntimeException::class);
});

it('does not throw when the backfill command succeeds', function (): void {
    Artisan::shouldReceive('call')
        ->once()
        ->with('erp:backfill-document-sequences')
        ->andReturn(0);

    $migration = require database_path('migrations/2026_07_29_140000_backfill_document_number_sequences.php');

    expect(fn (): mixed => $migration->up())->not->toThrow(RuntimeException::class);
});
