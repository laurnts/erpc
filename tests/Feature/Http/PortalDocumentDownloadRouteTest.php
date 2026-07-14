<?php

declare(strict_types=1);

use App\Enums\ActorType;
use App\Models\Company;
use App\Models\CompanyPortalUser;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->withPersonalTeam()->create();
    $this->team->users()->attach($this->admin, ['role' => 'admin']);
    $this->admin->switchTeam($this->team);

    $this->buyer = Company::factory()->buyer()->for($this->team)->create();
    $this->buyerUser = User::factory()->create();

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $this->buyer->getKey(),
        'user_id' => $this->buyerUser->getKey(),
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    $this->request = Request::factory()->for($this->team)->for($this->buyer, 'buyer')->create();
});

function buyerStampedAttachment(Request $request, User $uploader): \Spatie\MediaLibrary\MediaCollections\Models\Media
{
    return $request->addMediaFromString('%PDF-1.4 dummy')
        ->usingFileName('PO-scan.pdf')
        ->withCustomProperties([
            'uploader_id' => $uploader->getKey(),
            'uploader_actor_type' => ActorType::Buyer->value,
        ])
        ->toMediaCollection('attachments');
}

it('serves a buyer-visible attachment to the buyer as a download', function (): void {
    $media = buyerStampedAttachment($this->request, $this->buyerUser);

    $response = $this->actingAs($this->buyerUser, 'buyer')
        ->get(route('buyer.documents.download', ['media' => $media]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('denies the buyer media its timeline hides (staff-uploaded supplier quotation)', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();
    $supplierQuote = SupplierQuote::factory()->for($this->team)->for($this->request)->create([
        'supplier_id' => $supplier->getKey(),
    ]);

    $media = $supplierQuote->addMediaFromString('%PDF-1.4 dummy')
        ->usingFileName('supplier-quotation.pdf')
        ->withCustomProperties([
            'uploader_id' => $this->admin->getKey(),
            'uploader_actor_type' => ActorType::Staff->value,
        ])
        ->toMediaCollection('quotation');

    $this->actingAs($this->buyerUser, 'buyer')
        ->get(route('buyer.documents.download', ['media' => $media]))
        ->assertNotFound();
});

it('denies the buyer unstamped media (fail-closed)', function (): void {
    $media = $this->request->addMediaFromString('%PDF-1.4 dummy')
        ->usingFileName('legacy-scan.pdf')
        ->toMediaCollection('attachments');

    $this->actingAs($this->buyerUser, 'buyer')
        ->get(route('buyer.documents.download', ['media' => $media]))
        ->assertNotFound();
});

it('denies a buyer portal user of another team (cross-tenant)', function (): void {
    $media = buyerStampedAttachment($this->request, $this->buyerUser);

    $otherTeam = Team::factory()->create();
    $otherAdmin = User::factory()->withPersonalTeam()->create();
    $otherTeam->users()->attach($otherAdmin, ['role' => 'admin']);
    $otherBuyer = Company::factory()->buyer()->for($otherTeam)->create();
    $intruder = User::factory()->create();

    CompanyPortalUser::query()->create([
        'team_id' => $otherTeam->getKey(),
        'company_id' => $otherBuyer->getKey(),
        'user_id' => $intruder->getKey(),
        'invited_by' => $otherAdmin->getKey(),
        'is_active' => true,
    ]);

    $this->actingAs($intruder, 'buyer')
        ->get(route('buyer.documents.download', ['media' => $media]))
        ->assertNotFound();
});

it('redirects an unauthenticated visitor on the buyer document route', function (): void {
    $media = buyerStampedAttachment($this->request, $this->buyerUser);

    $this->get(route('buyer.documents.download', ['media' => $media]))
        ->assertRedirect();
});

it('serves a supplier its own stamped attachment and denies the buyer route for it', function (): void {
    $supplier = Company::factory()->supplier()->for($this->team)->create();
    $supplierUser = User::factory()->create();

    CompanyPortalUser::query()->create([
        'team_id' => $this->team->getKey(),
        'company_id' => $supplier->getKey(),
        'user_id' => $supplierUser->getKey(),
        'portal' => \App\Enums\PortalType::Supplier,
        'invited_by' => $this->admin->getKey(),
        'is_active' => true,
    ]);

    $supplierQuote = SupplierQuote::factory()->for($this->team)->for($this->request)->create([
        'supplier_id' => $supplier->getKey(),
    ]);

    $media = $supplierQuote->addMediaFromString('%PDF-1.4 dummy')
        ->usingFileName('supplier-notes.pdf')
        ->withCustomProperties([
            'uploader_id' => $supplierUser->getKey(),
            'uploader_actor_type' => ActorType::Supplier->value,
            'uploader_company_id' => $supplier->getKey(),
        ])
        ->toMediaCollection('attachments');

    $response = $this->actingAs($supplierUser, 'supplier')
        ->get(route('supplier.documents.download', ['media' => $media]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');

    $this->actingAs($this->buyerUser, 'buyer')
        ->get(route('buyer.documents.download', ['media' => $media]))
        ->assertNotFound();
});
