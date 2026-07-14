<?php

declare(strict_types=1);

use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('allows a same-team user to download a document via the generic route', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->actingAs($owner)
        ->get(route('documents.download', $media));

    $response->assertOk();
});

it('rejects a cross-team user downloading a document via the generic route with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $intruder = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($intruder)
        ->get(route('documents.download', $media));

    $response->assertNotFound();
});

it('redirects an unauthenticated request to download a document via the generic route', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->get(route('documents.download', $media));

    $response->assertRedirect(route('login'));
});

it('rejects a document whose owning model carries no team with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();
    $media = $team->addMedia(UploadedFile::fake()->createWithContent('logo.png', str_repeat('0', 200)))
        ->toMediaCollection('company_logo');

    $response = $this->actingAs($owner)
        ->get(route('documents.download', $media));

    $response->assertNotFound();
});

it('serves an allowlisted PDF inline with its stored mime type', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->actingAs($owner)
        ->get(route('documents.download', $media));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
});

it('serves a non-allowlisted SVG as an attachment with a generic content type', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('evil.svg', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');
    $media->update(['mime_type' => 'image/svg+xml']);

    $response = $this->actingAs($owner)
        ->get(route('documents.download', $media));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('forces an attachment disposition for a render-safe PDF when the download flag is set', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->actingAs($owner)
        ->get(route('documents.download', ['media' => $media, 'download' => 1]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('keeps the generic content type for a non-allowlisted file when the download flag is set', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('evil.svg', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');
    $media->update(['mime_type' => 'image/svg+xml']);

    $response = $this->actingAs($owner)
        ->get(route('documents.download', ['media' => $media, 'download' => 1]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('strips quotes and newlines from the download file name header', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quo"te.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    $response = $this->actingAs($owner)
        ->get(route('documents.download', $media));

    $response->assertOk();
    expect($media->refresh()->file_name)->toContain('"')
        ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename="quote.pdf"');
});

it('rejects a document whose file is missing on disk with 404', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $supplierQuote = SupplierQuote::factory()->for($owner->personalTeam())->create();
    $media = $supplierQuote->addMedia(UploadedFile::fake()->createWithContent('quotation.pdf', '%PDF-1.4'.str_repeat('0', 200)))
        ->toMediaCollection('quotation');

    Storage::disk('local')->delete($media->getPathRelativeToRoot());

    $response = $this->actingAs($owner)
        ->get(route('documents.download', $media));

    $response->assertNotFound();
});
