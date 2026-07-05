<?php

declare(strict_types=1);

use App\Actions\Media\AttachUploadedFiles;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Support\Media\DocumentPathGenerator;
use Illuminate\Support\Facades\DB;

function makeUploadFixture(string $directory, string $name): string
{
    $absoluteDir = storage_path('app/'.$directory);
    if (! is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0777, true);
    }
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    file_put_contents($absoluteDir.'/'.$name, $pdf);

    return $directory.'/'.$name;
}

afterEach(function () {
    $dir = storage_path('app/doc-stamp-fixtures');
    if (is_dir($dir)) {
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }
});

it('stamps a v3 prefix and resolves the media path under it', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-0500',
        'created_at' => '2026-03-01 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-0500',
    ]);

    $file = makeUploadFixture('doc-stamp-fixtures', 'quote.pdf');

    (new AttachUploadedFiles)->execute($quote, [$file], 'quotation', 'doc-stamp-fixtures');

    $media = $quote->refresh()->getFirstMedia('quotation');
    expect($media)->not->toBeNull();

    $expectedPrefix = 'documents/team-'.$request->team_id.'/2026/REQ-2026-0500/supplier-quotes/SQ-2026-0500';
    expect($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3);
    expect($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toBe($expectedPrefix);

    $generator = new DocumentPathGenerator;
    expect($generator->getPath($media))->toBe($expectedPrefix.'/'.$media->getKey().'/');

    $quote->clearMediaCollection('quotation');
});

it('keeps the stamped path stable and query-free after the parent is renumbered', function () {
    $request = Request::factory()->create([
        'request_number' => 'REQ-2026-0600',
        'created_at' => '2026-04-01 09:00:00',
    ]);
    $quote = SupplierQuote::factory()->create([
        'team_id' => $request->team_id,
        'request_id' => $request->getKey(),
        'quote_number' => 'SQ-2026-0600',
    ]);

    $file = makeUploadFixture('doc-stamp-fixtures', 'quote2.pdf');
    (new AttachUploadedFiles)->execute($quote, [$file], 'quotation', 'doc-stamp-fixtures');

    $mediaId = $quote->refresh()->getFirstMedia('quotation')->getKey();
    $expectedPrefix = 'documents/team-'.$request->team_id.'/2026/REQ-2026-0600/supplier-quotes/SQ-2026-0600';

    $request->update(['request_number' => 'REQ-9999-9999']);
    $quote->update(['quote_number' => 'SQ-RENUMBERED']);

    $media = SupplierQuote::find($quote->getKey())->getFirstMedia('quotation');
    $generator = new DocumentPathGenerator;

    DB::enableQueryLog();
    $path = $generator->getPath($media);
    $conversions = $generator->getPathForConversions($media);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($path)->toBe($expectedPrefix.'/'.$mediaId.'/');
    expect($conversions)->toBe($expectedPrefix.'/'.$mediaId.'/conversions/');
    expect($queryCount)->toBe(0);

    $quote->clearMediaCollection('quotation');
});
