<?php

declare(strict_types=1);

use App\Enums\BuyerQuoteStatus;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Models\BuyerQuote;
use App\Models\BuyerQuoteItem;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\SupplierQuote;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\User;
use App\Services\Erp\Financial\MarginConvention;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->recycle($this->team)->create();
    $this->buyer = Company::factory()->buyer()->recycle($this->team)->create();
    $this->supplier = Company::factory()->supplier()->recycle($this->team)->create();
    $this->currency = Currency::factory()->usd()->create();
    $this->request = Request::factory()->recycle($this->team)->recycle($this->buyer)->create();

    SupplierQuote::factory()
        ->recycle($this->team)
        ->recycle($this->request)
        ->forSupplier($this->supplier)
        ->withCurrency($this->currency)
        ->selected()
        ->create(['obtained' => true]);

    TaxCode::factory()->recycle($this->team)->create([
        'rate' => 11,
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->artisan('db:seed', ['--class' => 'ErpPermissionSeeder']);
    $this->user->assignRole('admin');
    $this->team->users()->attach($this->user, ['role' => 'admin']);
    $this->user->markEmailAsVerified();
    $this->user->update(['current_team_id' => $this->team->getKey()]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team);
});

describe('buyer quote edit margin persistence', function (): void {
    it('persists the on-selling margin when selling price is raised after a zero-margin price', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->buyer)
            ->recycle($this->currency)
            ->recycle($this->user)
            ->create(['status' => BuyerQuoteStatus::DRAFT]);

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->forRequestItem($requestItem)
            ->withPricing(costPrice: 6890, unitPrice: 6890, quantity: 1)
            ->create([
                'margin_percent' => '0.0000',
                'is_tax_inclusive' => true,
                'tax_rate' => '11',
            ]);

        $component = livewire(BuyerQuotesRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->mountAction(TestAction::make('edit')->table($quote));

        /** @var array<string, array<string, mixed>> $items */
        $items = $component->get('mountedActions.0.data.items');
        $rowKey = array_key_first($items);

        $items[$rowKey]['unit_price'] = '7200';
        $items[$rowKey]['unit_price_exc_tax'] = '7200';
        $items[$rowKey]['margin_percent'] = (string) round(
            MarginConvention::marginPercent(6890.0, 7200.0),
            4,
        );

        $component
            ->set('mountedActions.0.data.items', $items)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $item->refresh();

        expect((float) $item->unit_price_exc_tax)->toBe(7200.0)
            ->and((int) round((float) $item->margin_percent))->toBe(
                (int) round(MarginConvention::marginPercent(6890.0, 7200.0)),
            );
    });

    it('persists zero margin when selling price equals cost', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->buyer)
            ->recycle($this->currency)
            ->recycle($this->user)
            ->create(['status' => BuyerQuoteStatus::DRAFT]);

        $item = BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->forRequestItem($requestItem)
            ->withPricing(costPrice: 6890, unitPrice: 7103, quantity: 1)
            ->create([
                'margin_percent' => (string) round(MarginConvention::marginPercent(6890.0, 7103.0), 4),
                'is_tax_inclusive' => true,
                'tax_rate' => '11',
            ]);

        $component = livewire(BuyerQuotesRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->mountAction(TestAction::make('edit')->table($quote));

        /** @var array<string, array<string, mixed>> $items */
        $items = $component->get('mountedActions.0.data.items');
        $rowKey = array_key_first($items);

        $items[$rowKey]['unit_price'] = '6890';
        $items[$rowKey]['unit_price_exc_tax'] = '6890';
        $items[$rowKey]['margin_percent'] = '0.0000';

        $component
            ->set('mountedActions.0.data.items', $items)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $item->refresh();

        expect((float) $item->unit_price_exc_tax)->toBe(6890.0)
            ->and((float) $item->margin_percent)->toBe(0.0);
    });

    it('keeps the default margin visible when a selling price is entered on a zero-cost line', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->buyer)
            ->recycle($this->currency)
            ->recycle($this->user)
            ->create(['status' => BuyerQuoteStatus::DRAFT]);

        BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->forRequestItem($requestItem)
            ->withPricing(costPrice: 0, unitPrice: 0, quantity: 1)
            ->create([
                'margin_percent' => '0.0000',
                'is_tax_inclusive' => true,
                'tax_rate' => '11',
            ]);

        $component = livewire(BuyerQuotesRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->mountAction(TestAction::make('edit')->table($quote));

        /** @var array<string, array<string, mixed>> $items */
        $items = $component->get('mountedActions.0.data.items');
        $rowKey = array_key_first($items);

        $component->set("mountedActions.0.data.items.{$rowKey}.unit_price", '5000');

        expect((int) round((float) $component->get("mountedActions.0.data.items.{$rowKey}.margin_percent_input")))
            ->toBe(3);
    });

    it('keeps a typed margin on a low-cost line instead of snapping it to the rounded-price margin', function (): void {
        $requestItem = RequestItem::factory()->recycle($this->request)->create();
        $quote = BuyerQuote::factory()
            ->recycle($this->team)
            ->recycle($this->request)
            ->recycle($this->buyer)
            ->recycle($this->currency)
            ->recycle($this->user)
            ->create(['status' => BuyerQuoteStatus::DRAFT]);

        BuyerQuoteItem::factory()
            ->forBuyerQuote($quote)
            ->forRequestItem($requestItem)
            ->withPricing(costPrice: 10, unitPrice: 10, quantity: 1)
            ->create([
                'margin_percent' => '0.0000',
                'is_tax_inclusive' => true,
                'tax_rate' => '11',
            ]);

        $component = livewire(BuyerQuotesRelationManager::class, [
            'ownerRecord' => $this->request,
            'pageClass' => ViewRequest::class,
        ])->mountAction(TestAction::make('edit')->table($quote));

        /** @var array<string, array<string, mixed>> $items */
        $items = $component->get('mountedActions.0.data.items');
        $rowKey = array_key_first($items);

        $component->set("mountedActions.0.data.items.{$rowKey}.margin_percent_input", '3');

        expect((int) round((float) $component->get("mountedActions.0.data.items.{$rowKey}.margin_percent_input")))
            ->toBe(3)
            ->and((float) $component->get("mountedActions.0.data.items.{$rowKey}.unit_price"))
            ->toBe(10.0);
    });
});
