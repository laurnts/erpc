<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Resources\PeopleResource;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\People;
use App\Models\QuotationEvaluation;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
        
        // Fill the form with initial data
        $this->form->fill([
            'qeDate' => $this->qeDate,
            'description' => $this->description,
        ]);
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
                            ->default(now())
                            ->native(false),
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
     * Get key account (People with is_key_account = true) options for select fields.
     * Only shows key accounts that are assigned to handle the request's buyer.
     *
     * @return array<int, string>
     */
    private function getKeyAccountOptions(): array
    {
        $query = People::query()
            ->where('team_id', Filament::getTenant()?->getKey())
            ->where('is_key_account', true);

        // Filter to only show key accounts assigned to handle this request's buyer
        if ($this->request->buyer_id) {
            $query->whereHas('buyers', function ($q): void {
                $q->where('companies.id', $this->request->buyer_id);
            });
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (People $person): array => [$person->getKey() => $person->name])
            ->toArray();
    }

    /**
     * Create a new key account person from inline form.
     *
     * @param  array<string, mixed>  $data
     */
    public function createKeyAccount(array $data): int
    {
        // Check authorization
        Gate::authorize('create', People::class);

        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();

        /** @var People $person */
        $person = People::create([
            'name' => $data['name'],
            'is_key_account' => true,
            'team_id' => $team->id,
            'creator_id' => auth()->id(),
        ]);

        // Note: Email and phone are custom fields and should be set through the People form
        // The user can edit the person after creation to add email/phone

        return $person->id;
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
            Gate::authorize('create', People::class);
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

        try {
            // Validate Filament form first
            $formData = $this->form->getState();

            /** @var \App\Models\Team|null $team */
            $team = Filament::getTenant();

            if ($team === null) {
                Notification::make()
                    ->title('Error')
                    ->body('Unable to determine team context. Please refresh the page and try again.')
                    ->danger()
                    ->send();

                return;
            }

            // Use form data if available, otherwise fall back to properties
            $description = $formData['description'] ?? $this->description ?? $this->request->title;
            $qeDate = $formData['qeDate'] ?? $this->qeDate ?? now()->format('Y-m-d');

            // Generate QE number
            $qeNumber = QuotationEvaluation::generateQeNumber($team->id);

            // Build snapshot data
            $snapshotData = $this->buildSnapshotData();

            // Create the QE record
            $qe = QuotationEvaluation::create([
                'team_id' => $team->id,
                'request_id' => $this->request->getKey(),
                'qe_number' => $qeNumber,
                'description' => $description,
                'qe_date' => $qeDate,
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors are handled automatically by Livewire/Filament
            throw $e;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error creating Quotation Evaluation')
                ->body('An error occurred while creating the quotation evaluation. Please try again.')
                ->danger()
                ->send();
        }
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
                'is_taxable' => $quote->supplier?->is_taxable ?? false,
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
