<?php

declare(strict_types=1);

use App\Support\DocumentUpload;
use Illuminate\Support\HtmlString;

it('renders standardized bullets with default formats and MB size', function () {
    $html = DocumentUpload::helperText(10240)->toHtml();

    expect($html)
        ->toContain('<ul class="list-disc list-inside space-y-0.5">')
        ->toContain('<li>Accepted formats: PDF, Excel, Word, or images</li>')
        ->toContain('<li>Maximum size: 10MB per file</li>');
});

it('returns an HtmlString so Filament renders markup rather than escaping it', function () {
    expect(DocumentUpload::helperText(2048))->toBeInstanceOf(HtmlString::class);
});

it('formats small sizes in KB and large sizes in MB', function (int $kb, string $expected) {
    expect(DocumentUpload::helperText($kb)->toHtml())->toContain("Maximum size: {$expected} per file");
})->with([
    '2MB' => [2048, '2MB'],
    '5MB' => [5120, '5MB'],
    'sub-MB in KB' => [512, '512KB'],
    'fractional MB trimmed' => [1536, '1.5MB'],
]);

it('accepts a custom formats label', function () {
    expect(DocumentUpload::helperText(5120, formats: 'PDF, Word, or images')->toHtml())
        ->toContain('<li>Accepted formats: PDF, Word, or images</li>')
        ->not->toContain('Excel');
});

it('exposes the standard document MIME types as a constant', function () {
    expect(DocumentUpload::ACCEPTED_MIME_TYPES)
        ->toBeArray()
        ->toContain('application/pdf')
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->toContain('application/msword')
        ->toContain('image/png');
});

it('builds a max-size validation message whose limit matches the KB value', function (int $kb, string $expected) {
    expect(DocumentUpload::maxSizeMessage($kb))
        ->toBe("Each file must not exceed {$expected}. Please compress or resize your file before uploading.");
})->with([
    '2MB' => [2048, '2MB'],
    '10MB' => [10240, '10MB'],
]);

it('prepends context notes as leading bullets and escapes HTML', function () {
    $html = DocumentUpload::helperText(10240, notes: ["Buyer's email, letter, quote request, or PO"])->toHtml();

    expect($html)->toContain('<li>Buyer&#039;s email, letter, quote request, or PO</li>');

    $noteIndex = strpos($html, 'Buyer');
    $formatsIndex = strpos($html, 'Accepted formats');
    expect($noteIndex)->toBeLessThan($formatsIndex);
});
