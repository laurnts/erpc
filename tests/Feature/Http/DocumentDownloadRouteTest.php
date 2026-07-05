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
