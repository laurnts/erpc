<?php

declare(strict_types=1);

use App\Models\Request;
use App\Models\SupplierQuote;
use App\Support\Media\DocumentPathGenerator;
use Illuminate\Support\Facades\Storage;

function makeOrphanFixtureSource(string $name): string
{
    $dir = sys_get_temp_dir().'/scan-orphans-fixtures';
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

it('reports a planted orphan while preserving referenced files', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-9101',
        'created_at' => '2026-02-01 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-9101',
    ]);

    $media = $quote->addMedia(makeOrphanFixtureSource('referenced.pdf'))
        ->withCustomProperties([
            DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V3,
            DocumentPathGenerator::PATH_PREFIX_PROPERTY => 'documents/team-'.$request->team_id.'/2026/REQ-2026-9101/supplier-quotes/SQ-2026-9101',
        ])
        ->toMediaCollection('quotation');

    $referencedPath = $media->getPathRelativeToRoot();
    Storage::disk('local')->put('orphaned-directory/stray.pdf', 'stray-contents');

    $this->artisan('documents:scan-orphans')
        ->expectsOutputToContain('orphaned-directory/stray.pdf')
        ->assertExitCode(0);

    expect(Storage::disk('local')->exists($referencedPath))->toBeTrue()
        ->and(Storage::disk('local')->exists('orphaned-directory/stray.pdf'))->toBeTrue();
});

it('deletes orphaned files with --delete while preserving referenced files', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-9102',
        'created_at' => '2026-02-02 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-9102',
    ]);

    $media = $quote->addMedia(makeOrphanFixtureSource('referenced2.pdf'))
        ->withCustomProperties([
            DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V3,
            DocumentPathGenerator::PATH_PREFIX_PROPERTY => 'documents/team-'.$request->team_id.'/2026/REQ-2026-9102/supplier-quotes/SQ-2026-9102',
        ])
        ->toMediaCollection('quotation');

    $referencedPath = $media->getPathRelativeToRoot();
    Storage::disk('local')->put('orphaned-directory-2/stray.pdf', 'stray-contents');

    $this->artisan('documents:scan-orphans', ['--delete' => true])
        ->expectsOutputToContain('orphaned-directory-2/stray.pdf')
        ->assertExitCode(0);

    expect(Storage::disk('local')->exists($referencedPath))->toBeTrue()
        ->and(Storage::disk('local')->exists('orphaned-directory-2/stray.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists('orphaned-directory-2'))->toBeFalse();
});

it('does not flag a public-disk orphan sharing an id with an unrelated local media row', function () {
    // The exact collision from the live store: local media #1 exists (e.g. a
    // supplier-quote document), and an unrelated orphan file sits at
    // public/1/favicon.svg. The id match must not make it look referenced.
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-9103',
        'created_at' => '2026-02-03 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-9103',
    ]);

    $media = $quote->addMedia(makeOrphanFixtureSource('referenced3.pdf'))
        ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
        ->toMediaCollection('quotation');

    expect($media->disk)->toBe('local');

    // Plant an orphan on the PUBLIC disk using the SAME id as the local media row.
    Storage::disk('public')->put($media->getKey().'/favicon.svg', '<svg></svg>');

    $this->artisan('documents:scan-orphans')
        ->expectsOutputToContain($media->getKey().'/favicon.svg')
        ->assertExitCode(0);

    // And the local media itself must still be considered referenced.
    expect(Storage::disk('local')->exists($media->getPathRelativeToRoot()))->toBeTrue();
});

it('never flags files under uploads-tmp or livewire-tmp as orphans', function () {
    Storage::disk('local')->put('uploads-tmp/supplier-quotes/scratch.pdf', 'scratch');
    Storage::disk('local')->put('livewire-tmp/scratch.pdf', 'scratch');

    $this->artisan('documents:scan-orphans')
        ->doesntExpectOutputToContain('uploads-tmp/supplier-quotes/scratch.pdf')
        ->doesntExpectOutputToContain('livewire-tmp/scratch.pdf')
        ->assertExitCode(0);

    expect(Storage::disk('local')->exists('uploads-tmp/supplier-quotes/scratch.pdf'))->toBeTrue()
        ->and(Storage::disk('local')->exists('livewire-tmp/scratch.pdf'))->toBeTrue();
});

it('does not treat the nested public disk root under local as orphaned content', function () {
    // storage/app/public physically sits inside the local disk's root; the
    // 'public' subtree must be attributed only to the public-disk scan, never
    // reported (or deleted) as a local-disk orphan.
    Storage::disk('public')->put('5/image.jpg', 'image-bytes');

    $this->artisan('documents:scan-orphans')
        ->doesntExpectOutputToContain('public/5/image.jpg')
        ->assertExitCode(0);
});
