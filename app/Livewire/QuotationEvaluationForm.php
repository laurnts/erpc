<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\KeyAccountResource;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Models\KeyAccount;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Livewire component for creating Quotation Evaluation documents.
 */
final class QuotationEvaluationForm extends BaseLivewireComponent
{
    public Request $request;

    public ?string $description = null;

    public ?string $qeDate = null;

    public ?int $preparedById = null;

    public ?string $deptHeadSalesName = null;

    public ?string $deputyDirectorName = null;

    public ?string $approvedByName = null;

    // Modal state management
    public bool $showKeyAccountForm = false;

    public string $newKeyAccountName = '';

    public string $newKeyAccountEmail = '';

    public string $newKeyAccountPhone = '';

    public function mount(Request $request): void
    {
        $this->request = $request;
        $this->description = $request->title;
        $this->qeDate = now()->format('Y-m-d');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('QE Information')
                    ->schema([
                        Placeholder::make('qe_number_placeholder')
                            ->label('QE Number')
                            ->content('Auto-generated after save'),
                        DatePicker::make('qeDate')
                            ->label('Date')
                            ->required()
                            ->default(now()),
                        TextInput::make('request_number')
                            ->label('Request')
                            ->default($this->request->request_number)
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

            ]);
    }

    /**
     * Get key account options for select fields.
     *
     * @return array<int, string>
     */
    private function getKeyAccountOptions(): array
    {
        return KeyAccount::query()
            ->where('team_id', Filament::getTenant()?->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (KeyAccount $ka): array => [$ka->getKey() => $ka->display_name])
            ->toArray();
    }

    /**
     * Create a new key account from inline form.
     */
    public function createKeyAccount(array $data): int
    {
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();

        /** @var KeyAccount $keyAccount */
        $keyAccount = KeyAccount::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'team_id' => $team->id,
            'creator_id' => auth()->id(),
        ]);

        return $keyAccount->id;
    }

    /**
     * Open the key account creation form.
     */
    public function openKeyAccountForm(): void
    {
        $this->showKeyAccountForm = true;
        $this->newKeyAccountName = '';
        $this->newKeyAccountEmail = '';
        $this->newKeyAccountPhone = '';
    }

    /**
     * Cancel and go back to QE form.
     */
    public function cancelKeyAccountForm(): void
    {
        $this->showKeyAccountForm = false;
    }

    /**
     * Save the new key account and go back to QE form.
     */
    public function saveNewKeyAccount(): void
    {
        $this->validate([
            'newKeyAccountName' => 'required|string|max:255',
            'newKeyAccountEmail' => 'nullable|email|max:255',
            'newKeyAccountPhone' => 'nullable|string|max:50',
        ]);

        $id = $this->createKeyAccount([
            'name' => $this->newKeyAccountName,
            'email' => $this->newKeyAccountEmail ?: null,
            'phone' => $this->newKeyAccountPhone ?: null,
        ]);

        $this->preparedById = $id;
        $this->showKeyAccountForm = false;

        Notification::make()
            ->title('Key Account created')
            ->body('The new key account has been selected.')
            ->success()
            ->send();
    }

    /**
     * Save the Quotation Evaluation.
     */
    public function save(): void
    {
        $this->validate([
            'qeDate' => 'required|date',
        ]);

        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();

        // Generate QE number
        $qeNumber = QuotationEvaluation::generateQeNumber($team->id);

        // Build snapshot data
        $snapshotData = $this->buildSnapshotData();

        // Create the QE record
        $qe = QuotationEvaluation::create([
            'team_id' => $team->id,
            'request_id' => $this->request->getKey(),
            'qe_number' => $qeNumber,
            'description' => $this->description,
            'qe_date' => $this->qeDate,
            'prepared_by_id' => $this->preparedById,
            'dept_head_sales_name' => $this->deptHeadSalesName,
            'deputy_director_name' => $this->deputyDirectorName,
            'approved_by_name' => $this->approvedByName,
            'data' => $snapshotData,
            'creator_id' => auth()->id(),
        ]);

        Notification::make()
            ->title('Quotation Evaluation created')
            ->body("QE {$qeNumber} has been created successfully.")
            ->success()
            ->send();

        // Redirect to the QE view page
        $this->redirect(QuotationEvaluationResource::getUrl('view', ['record' => $qe]));
    }

    /**
     * Build the snapshot data from the request's supplier quotes.
     *
     * @return array<string, mixed>
     */
    private function buildSnapshotData(): array
    {
        // Get all active quotes
        $quotes = $this->request->supplierQuotes()
            ->whereIn('status', [SupplierQuoteStatus::PENDING, SupplierQuoteStatus::SELECTED])
            ->with(['supplier', 'currency', 'items.requestItem'])
            ->orderBy('total_base')
            ->get();

        // Get request items
        $requestItems = $this->request->items()->with('article')->orderBy('sort_order')->get();

        // Build price matrix and find best prices
        $bestPrices = $this->findBestPrices($requestItems, $quotes);

        // Build items array
        $items = [];
        foreach ($requestItems as $requestItem) {
            $itemData = [
                'id' => $requestItem->getKey(),
                'description' => $requestItem->article?->name ?? $requestItem->description,
                'quantity' => (float) $requestItem->quantity,
                'unit' => $requestItem->unit ?? 'pcs',
                'prices' => [],
            ];

            foreach ($quotes as $quote) {
                $quoteItem = $quote->items->first(
                    fn (SupplierQuoteItem $item): bool => $item->request_item_id === $requestItem->getKey()
                );

                if ($quoteItem !== null) {
                    $isBestPrice = ($bestPrices[$requestItem->getKey()] ?? null) === $quote->getKey();

                    $itemData['prices'][(string) $quote->getKey()] = [
                        'supplier_id' => $quote->supplier_id,
                        'unit_price' => (float) $quoteItem->unit_price_exc_tax,
                        'line_subtotal' => (float) $quoteItem->line_subtotal,
                        'line_tax' => (float) $quoteItem->line_tax,
                        'line_total' => (float) $quoteItem->line_total,
                        'is_best_price' => $isBestPrice,
                        'is_selected' => $quoteItem->is_selected,
                    ];
                }
            }

            $items[] = $itemData;
        }

        // Build suppliers array
        $suppliers = [];
        foreach ($quotes as $quote) {
            $suppliers[] = [
                'id' => $quote->getKey(),
                'name' => $quote->supplier?->name ?? 'Unknown',
                'currency_code' => $quote->currency?->code ?? 'USD',
                'delivery_type' => $quote->supplier?->delivery_type ?? null,
                'delivery_type_details' => $quote->supplier?->delivery_type_details ?? null,
                'is_taxable' => (float) $quote->tax_total > 0,
                'delivery_term' => $quote->supplier?->delivery_term ?? null,
                'payment_terms_days' => $quote->supplier?->payment_terms ?? null,
                'subtotal' => (float) $quote->subtotal,
                'tax_total' => (float) $quote->tax_total,
                'grand_total' => (float) $quote->total,
            ];
        }

        return [
            'request' => [
                'id' => $this->request->getKey(),
                'request_number' => $this->request->request_number,
                'title' => $this->request->title,
            ],
            'items' => $items,
            'suppliers' => $suppliers,
        ];
    }

    /**
     * Find the best price (lowest unit price in base currency) for each request item.
     *
     * @param  Collection<int, \App\Models\RequestItem>  $requestItems
     * @param  Collection<int, SupplierQuote>  $quotes
     * @return array<int, int|null>
     */
    private function findBestPrices(Collection $requestItems, Collection $quotes): array
    {
        $bestPrices = [];

        foreach ($requestItems as $requestItem) {
            $bestQuoteId = null;
            $bestUnitPriceBase = null;

            foreach ($quotes as $quote) {
                $quoteItem = $quote->items->first(
                    fn (SupplierQuoteItem $item): bool => $item->request_item_id === $requestItem->getKey()
                );

                if ($quoteItem === null) {
                    continue;
                }

                // Compare unit price in base currency
                $unitPriceBase = (float) $quoteItem->unit_price_exc_tax * (float) $quote->exchange_rate;

                if ($bestUnitPriceBase === null || $unitPriceBase < $bestUnitPriceBase) {
                    $bestUnitPriceBase = $unitPriceBase;
                    $bestQuoteId = $quote->getKey();
                }
            }

            $bestPrices[$requestItem->getKey()] = $bestQuoteId;
        }

        return $bestPrices;
    }

    public function render(): View
    {
        return view('livewire.quotation-evaluation-form');
    }
}
