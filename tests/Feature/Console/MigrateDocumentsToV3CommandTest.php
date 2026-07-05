<?php

declare(strict_types=1);

use App\Models\Request;
use App\Models\SupplierQuote;
use App\Support\Media\DocumentPathGenerator;
use Illuminate\Support\Facades\Storage;

function makeMigrateFixtureSource(string $name): string
{
    $dir = sys_get_temp_dir().'/migrate-v3-fixtures';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = $dir.'/'.$name;
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");

    return $path;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('moves a v2 media file to its v3 path and stamps custom properties', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-9001',
        'created_at' => '2026-01-10 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-9001',
    ]);

    $media = $quote->addMedia(makeMigrateFixtureSource('quote.pdf'))
        ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
        ->toMediaCollection('quotation');

    $oldRelativePath = $media->getPathRelativeToRoot();
    expect(Storage::disk('local')->exists($oldRelativePath))->toBeTrue();

    // Plant a fake conversion file alongside the main upload to prove the whole
    // directory (not just the primary file) is moved.
    $oldDir = rtrim(dirname($oldRelativePath), '/');
    Storage::disk('local')->put($oldDir.'/conversions/thumb.jpg', 'thumb-contents');

    $this->artisan('documents:migrate-v3')->assertExitCode(0);

    $media->refresh();
    $expectedPrefix = 'documents/team-'.$request->team_id.'/2026/REQ-2026-9001/supplier-quotes/SQ-2026-9001';

    expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toBe($expectedPrefix);

    $newRelativePath = $media->getPathRelativeToRoot();

    expect($newRelativePath)->toContain($expectedPrefix)
        ->and(Storage::disk('local')->exists($oldRelativePath))->toBeFalse()
        ->and(Storage::disk('local')->exists($oldDir))->toBeFalse()
        ->and(Storage::disk('local')->exists($newRelativePath))->toBeTrue()
        ->and(Storage::disk('local')->exists($expectedPrefix.'/'.$media->getKey().'/conversions/thumb.jpg'))->toBeTrue();
});

it('is idempotent: a second run finds nothing left to migrate', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-9002',
        'created_at' => '2026-01-11 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-9002',
    ]);

    $quote->addMedia(makeMigrateFixtureSource('quote2.pdf'))
        ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
        ->toMediaCollection('quotation');

    $this->artisan('documents:migrate-v3')
        ->expectsOutputToContain('Migrated 1 media item(s), skipped 0.')
        ->assertExitCode(0);

    $media = $quote->refresh()->getFirstMedia('quotation');
    $pathAfterFirstRun = $media->getPathRelativeToRoot();

    $this->artisan('documents:migrate-v3')
        ->expectsOutputToContain('Migrated 0 media item(s), skipped 0.')
        ->assertExitCode(0);

    expect($media->refresh()->getPathRelativeToRoot())->toBe($pathAfterFirstRun)
        ->and(Storage::disk('local')->exists($pathAfterFirstRun))->toBeTrue();
});

it('leaves the original tree and stamp intact when a mid-copy failure occurs, then migrates fully on rerun', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-9005',
        'created_at' => '2026-01-14 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-9005',
    ]);

    $media = $quote->addMedia(makeMigrateFixtureSource('quote.pdf'))
        ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
        ->toMediaCollection('quotation');

    $oldRelativePath = $media->getPathRelativeToRoot();
    $oldDir = rtrim(dirname($oldRelativePath), '/');
    Storage::disk('local')->put($oldDir.'/conversions/thumb.jpg', 'thumb-contents');

    // Sabotage the copy of the main file (copied AFTER conversions/, which
    // sorts first in allFiles()): a directory squatting on its exact target
    // path makes that one copy fail while the conversions copy has already
    // landed — a genuine mid-copy partial failure.
    $expectedPrefix = 'documents/team-'.$request->team_id.'/2026/REQ-2026-9005/supplier-quotes/SQ-2026-9005';
    $newDir = $expectedPrefix.'/'.$media->getKey();
    Storage::disk('local')->makeDirectory($newDir.'/quote.pdf');

    $this->artisan('documents:migrate-v3')
        ->expectsOutputToContain('SKIP')
        ->expectsOutputToContain('Migrated 0 media item(s), skipped 1.')
        ->assertExitCode(0);

    $media->refresh();

    // Copy-first guarantee: DB stamp still v2, the ENTIRE old tree untouched —
    // even though one file (the conversion) did get copied before the failure.
    expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V2)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toBeNull()
        ->and(Storage::disk('local')->exists($oldRelativePath))->toBeTrue()
        ->and(Storage::disk('local')->exists($oldDir.'/conversions/thumb.jpg'))->toBeTrue()
        ->and(Storage::disk('local')->exists($newDir.'/conversions/thumb.jpg'))->toBeTrue();

    // Clear the sabotage; a clean rerun must migrate fully, overwriting the
    // stray copy left behind by the failed attempt.
    Storage::disk('local')->deleteDirectory($newDir.'/quote.pdf');

    $this->artisan('documents:migrate-v3')
        ->expectsOutputToContain('Migrated 1 media item(s), skipped 0.')
        ->assertExitCode(0);

    $media->refresh();

    expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and(Storage::disk('local')->exists($newDir.'/quote.pdf'))->toBeTrue()
        ->and(Storage::disk('local')->exists($newDir.'/conversions/thumb.jpg'))->toBeTrue()
        ->and(Storage::disk('local')->exists($oldDir))->toBeFalse();
});

it('skips media whose v3 path cannot be resolved and leaves it untouched', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-9003',
        'created_at' => '2026-01-12 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-9003',
    ]);
    // Blank the number via update (bypasses the "creating" observer hook that
    // auto-generates a number) to force DocumentPathResolver's numbered-segment
    // fallback: an incomplete anchoring chain, not a hardcoded model type.
    $quote->update(['quote_number' => '']);

    $media = $quote->addMedia(makeMigrateFixtureSource('quote3.pdf'))
        ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
        ->toMediaCollection('quotation');

    $oldRelativePath = $media->getPathRelativeToRoot();

    $this->artisan('documents:migrate-v3')
        ->expectsOutputToContain('SKIP')
        ->expectsOutputToContain('Migrated 0 media item(s), skipped 1.')
        ->assertExitCode(0);

    $media->refresh();

    expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V2)
        ->and($media->getPathRelativeToRoot())->toBe($oldRelativePath)
        ->and(Storage::disk('local')->exists($oldRelativePath))->toBeTrue();
});
