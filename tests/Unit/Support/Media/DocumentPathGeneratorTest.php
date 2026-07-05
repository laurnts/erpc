<?php

declare(strict_types=1);

use App\Support\Media\DocumentPathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(Tests\TestCase::class);

/**
 * Build an in-memory Media instance (no persistence) for path resolution.
 *
 * @param  array<string, mixed>  $customProperties
 */
function makeMedia(
    int $id,
    string $modelType,
    string $collection,
    string $disk,
    array $customProperties = [],
): Media {
    $media = new Media;
    $media->forceFill([
        'id' => $id,
        'model_type' => $modelType,
        'collection_name' => $collection,
        'disk' => $disk,
        'custom_properties' => $customProperties,
    ]);

    return $media;
}

describe('v1 legacy resolution (pins)', function () {
    it('keeps legacy {id}/ path for public-disk media', function () {
        $media = makeMedia(42, 'supplier_quote', 'quotation', 'public');
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe('42/');
        expect($generator->getPathForConversions($media))->toBe('42/conversions/');
        expect($generator->getPathForResponsiveImages($media))->toBe('42/responsive-images/');
    });

    it('keeps legacy path for local-disk media of a non-always-dedicated model without a version stamp', function () {
        $media = makeMedia(7, 'supplier_quote', 'quotation', 'local');
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe('7/');
        expect($generator->getPathForConversions($media))->toBe('7/conversions/');
        expect($generator->getPathForResponsiveImages($media))->toBe('7/responsive-images/');
    });
});

describe('v2 dedicated resolution (pins)', function () {
    it('uses the dedicated folder for a v2-stamped SupplierQuote', function () {
        $media = makeMedia(42, 'supplier_quote', 'quotation', 'local', [
            DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2,
        ]);
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe('supplierquote/42/uploaded_document_files/');
        expect($generator->getPathForConversions($media))->toBe('supplierquote/42/uploaded_document_files/conversions/');
        expect($generator->getPathForResponsiveImages($media))->toBe('supplierquote/42/uploaded_document_files/responsive-images/');
    });

    it('treats the version stamp as v2 whether stored as int or string', function () {
        $media = makeMedia(9, 'buyer_quote', 'buyer_po', 'local', [
            DocumentPathGenerator::PATH_VERSION_PROPERTY => (string) DocumentPathGenerator::PATH_VERSION_V2,
        ]);
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe('buyerquote/9/uploaded_document_files/');
    });

    it('uses the dedicated folder for always-dedicated models even without a version stamp', function () {
        $media = makeMedia(3, 'request', 'attachments', 'local');
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe('attachments/3/uploaded_document_files/');
        expect($generator->getPathForConversions($media))->toBe('attachments/3/uploaded_document_files/conversions/');
    });

    it('resolves the folder through the morph alias', function () {
        $media = makeMedia(11, 'quotation_evaluation', 'documents', 'local', [
            DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2,
        ]);
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe('quotationevaluation/11/uploaded_document_files/');
    });
});

describe('v3 stamped resolution', function () {
    it('returns the stamped prefix followed by {media_id}/', function () {
        $prefix = 'documents/team-1/2026/REQ-2026-0001/supplier-quotes/SQ-2026-0007';
        $media = makeMedia(88, 'supplier_quote', 'quotation', 'local', [
            DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V3,
            DocumentPathGenerator::PATH_PREFIX_PROPERTY => $prefix,
        ]);
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe($prefix.'/88/');
        expect($generator->getPathForConversions($media))->toBe($prefix.'/88/conversions/');
        expect($generator->getPathForResponsiveImages($media))->toBe($prefix.'/88/responsive-images/');
    });

    it('honours the v3 stamp whether the version is stored as int or string', function () {
        $prefix = 'documents/team-2/2025/REQ-2025-0100/request-attachments';
        $media = makeMedia(5, 'request', 'attachments', 'local', [
            DocumentPathGenerator::PATH_VERSION_PROPERTY => (string) DocumentPathGenerator::PATH_VERSION_V3,
            DocumentPathGenerator::PATH_PREFIX_PROPERTY => $prefix,
        ]);
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe($prefix.'/5/');
    });

    it('applies the v3 branch regardless of disk (prefix is authoritative)', function () {
        $prefix = 'documents/team-3/2026/REQ-2026-0009/acceptance-reports/AR-001';
        $media = makeMedia(6, 'acceptance_report', 'attachments', 'public', [
            DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V3,
            DocumentPathGenerator::PATH_PREFIX_PROPERTY => $prefix,
        ]);
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe($prefix.'/6/');
    });

    it('falls back to legacy logic when the v3 stamp lacks a usable prefix', function () {
        $media = makeMedia(42, 'supplier_quote', 'quotation', 'local', [
            DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V3,
            DocumentPathGenerator::PATH_PREFIX_PROPERTY => '',
        ]);
        $generator = new DocumentPathGenerator;

        expect($generator->getPath($media))->toBe('42/');
    });
});
