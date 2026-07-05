<?php

declare(strict_types=1);

use App\Enums\BuyerQuoteStatus;
use App\Enums\OrderStatus;
use App\Enums\PNLStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\CompletionReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\GoodsReceiveRelationManager;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\BuyerQuotePaymentTerm;
use App\Models\Company;
use App\Models\Currency;
use App\Models\GoodsReceiveBatch;
use App\Models\ProfitAndLoss;
use App\Models\Request;
use App\Models\SupplierOrder;
use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use App\Support\Media\DocumentPathGenerator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

function goodsReceivePdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
    );
}

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

    // Satisfy the HasRequestStageTab mount guards for the Goods Receive and
    // Completion Report tabs: obtained+selected supplier quote (QE gate),
    // approved PNL, an accepted buyer quote with no sent ones, and only
    // approved supplier orders.
    SupplierQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->forSupplier($this->supplier)
        ->withCurrency($this->currency)
        ->selected()
        ->create(['obtained' => true]);

    ProfitAndLoss::factory()
        ->recycle($this->user)
        ->forRequest($this->request)
        ->create(['status' => PNLStatus::APPROVED]);

    $this->buyerQuote = BuyerQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->recycle($this->buyer)
        ->recycle($this->currency)
        ->recycle($this->user)
        ->create(['status' => BuyerQuoteStatus::ACCEPTED, 'issued_at' => now()]);

    BuyerQuotePaymentTerm::factory()->for($this->buyerQuote)->create([
        'due_days' => 30,
        'percentage' => 100,
    ]);

    BuyerOrder::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->recycle($this->buyer)
        ->recycle($this->user)
        ->confirmed()
        ->create(['buyer_quote_id' => $this->buyerQuote->getKey(), 'confirmed_at' => now()]);

    $this->supplierOrder = SupplierOrder::factory()
        ->recycle($this->team)
        ->forRequest($this->request)
        ->create(['supplier_id' => $this->supplier->getKey(), 'status' => OrderStatus::SENT]);

    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

it('uploads a goods receive document with v3 stamps and its custom properties intact', function (): void {
    livewire(GoodsReceiveRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('create')->table(), [
            'supplier_order_id' => $this->supplierOrder->getKey(),
            'document' => [goodsReceivePdf('gr-doc.pdf')],
            'name' => 'GR Document',
        ])
        ->assertHasNoActionErrors();

    $batch = GoodsReceiveBatch::query()->where('request_id', $this->request->getKey())->first();

    expect($batch)->not->toBeNull()
        ->and($batch->media_ids)->toHaveCount(1);

    $media = $this->request->refresh()->getMedia('goods_receive')->firstWhere('id', $batch->media_ids[0]);

    expect($media)->not->toBeNull()
        ->and($media->name)->toBe('GR Document')
        ->and($media->getCustomProperty('uploaded_by'))->toBe($this->user->getKey())
        ->and($media->getCustomProperty('supplier_order_id'))->toBe($this->supplierOrder->getKey())
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
});

it('uploads a completion report document with v3 stamps and the payment-document flag intact', function (): void {
    livewire(CompletionReportsRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('create')->table(), [
            'document' => [goodsReceivePdf('cr-doc.pdf')],
            'name' => 'CR Document',
            'is_payment_document' => true,
            'payment_terms' => '30-100',
        ])
        ->assertHasNoActionErrors();

    $media = $this->request->refresh()->getFirstMedia('completion_reports');

    expect($media)->not->toBeNull()
        ->and($media->name)->toBe('CR Document')
        ->and($media->getCustomProperty('is_payment_document'))->toBeTrue()
        ->and($media->getCustomProperty('payment_terms'))->toBe('30-100')
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY))->toBe(DocumentPathGenerator::PATH_VERSION_V3)
        ->and($media->getCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY))->toStartWith('documents/team-'.$this->team->getKey().'/');
});
