<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->create();
    $this->quote = SupplierQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->forSupplier($this->supplier)
        ->withCurrency($this->currency)
        ->create();

    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);

    $this->quote
        ->addMediaFromString("%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n")
        ->usingFileName('quotation.pdf')
        ->toMediaCollection('quotation');
});

it('saves an edited quote whose service main item has no child items', function (): void {
    $requestItem = RequestItem::factory()
        ->recycle($this->team)
        ->for($this->request)
        ->create(['item_type' => ItemType::SERVICE]);

    $quoteItem = SupplierQuoteItem::factory()
        ->recycle($this->team)
        ->forSupplierQuote($this->quote)
        ->forRequestItem($requestItem)
        ->withPricing(1, 100.0)
        ->create();

    livewire(SupplierQuotesRelationManager::class, [
        'ownerRecord' => $this->request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('edit')->table($this->quote))
        ->assertHasNoActionErrors();

    expect((float) $quoteItem->refresh()->unit_price)->toBe(100.0);
});
