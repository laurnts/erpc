<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\KeyAccount\CreateKeyAccount;
use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\KeyAccount;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Livewire component for creating Quotation Evaluation documents.
 */
final class QuotationEvaluationForm extends BaseLivewireComponent
{
    use AuthorizesLivewireActions;

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
        // Verify request belongs to current team
        $this->ensureTeamOwnership($request);

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
     * Create a new key account from inline form.
     *
     * @param  array{name: string, email?: string|null, phone?: string|null, is_active?: bool}  $data
     */
    public function createKeyAccount(array $data): int
    {
        // Check authorization
        Gate::authorize('create', KeyAccount::class);

        $keyAccount = app(CreateKeyAccount::class)->execute($data);

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
        // Check authorization first
        try {
            Gate::authorize('create', KeyAccount::class);
        } catch (AuthorizationException) {
            Notification::make()
                ->title('Permission Denied')
                ->body('You do not have permission to create key accounts.')
                ->danger()
                ->send();

            return;
        }

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
        // Check authorization first
        try {
            Gate::authorize('create', QuotationEvaluation::class);
        } catch (AuthorizationException) {
            Notification::make()
                ->title('Permission Denied')
                ->body('You do not have permission to create quotation evaluations.')
                ->danger()
                ->send();

            return;
        }

        $this->validate([
            'qeDate' => 'required|date',
        ]);

        // Build snapshot data
        $snapshotData = $this->buildSnapshotData();

        // Create the QE record (team_id, creator_id, and qe_number are auto-set by observer)
        $qe = QuotationEvaluation::create([
            'request_id' => $this->request->getKey(),
            'description' => $this->description,
            'qe_date' => $this->qeDate,
            'prepared_by_id' => $this->preparedById,
            'dept_head_sales_name' => $this->deptHeadSalesName,
            'deputy_director_name' => $this->deputyDirectorName,
            'approved_by_name' => $this->approvedByName,
            'data' => $snapshotData,
        ]);

        Notification::make()
            ->title('Quotation Evaluation created')
            ->body("QE {$qe->qe_number} has been created successfully.")
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
