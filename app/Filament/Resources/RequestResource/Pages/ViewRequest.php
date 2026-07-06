<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\PrepaymentType;
use App\Enums\QEStatus;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Resources\BuyerResource;
use App\Filament\Resources\ProfitAndLossResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\QuotationEvaluationResource;
use App\Filament\Resources\RequestResource;
use App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\CompletionReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\GoodsReceiveRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\Currency;
use App\Models\PaymentDocumentApproval;
use App\Models\Request;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Relaticle\CustomFields\Facades\CustomFields;

final class ViewRequest extends ViewRecord
{
    protected static string $resource = RequestResource::class;

    /**
     * Map relation manager keys to their index in the relation managers array.
     *
     * @var array<string, int>
     */
    private const array RELATION_MANAGER_MAP = [
        'items' => 0,
        'supplierQuotes' => 1,
        'buyerQuotes' => 2,
        'supplierOrders' => 3,
        'goodsReceive' => 4,
        'buyerOrders' => 5,
        'shipments' => 6,
        'completionReports' => 7,
    ];

    public function getMaxWidth(): \Filament\Support\Enums\Width
    {
        return Width::Full;
    }

    /**
     * Called when the active relation manager changes (tab switch).
     * Auto-advances the stage if appropriate.
     */
    public function updatedActiveRelationManager(int|string|null $value): void
    {
        if ($value === null) {
            return;
        }

        // Convert numeric index to relation manager key
        $relationKey = $this->getRelationManagerKeyFromIndex($value);

        if ($relationKey === null) {
            return;
        }

        // Get the target stage for this relation manager
        $targetStage = RequestStage::fromRelationManagerKey($relationKey);

        if (! $targetStage instanceof \App\Enums\RequestStage) {
            return;
        }

        $this->tryAdvanceToStage($targetStage, $relationKey);
    }

    /**
     * Convert a relation manager index to its key.
     */
    private function getRelationManagerKeyFromIndex(int|string $index): ?string
    {
        // If it's already a string key, return it
        if (is_string($index) && ! is_numeric($index)) {
            return $index;
        }

        $index = (int) $index;
        $keys = array_keys(self::RELATION_MANAGER_MAP);

        return $keys[$index] ?? null;
    }

    /**
     * Try to advance the request to the target stage.
     * Only advances if the current stage is before the target stage.
     * When advancing, redirects to the view page with the target tab active so the page
     * reloads and all tab check icons (e.g. Goods Receive ✓) render correctly.
     *
     * @param  string|null  $activeRelationKey  Relation manager key for the tab that was clicked (e.g. buyerOrders)
     */
    private function tryAdvanceToStage(RequestStage $targetStage, ?string $activeRelationKey = null): void
    {
        /** @var Request $record */
        $record = $this->getRecord();
        $currentStage = $record->stage;

        // Only advance forward, never backward.
        // In the tab bar, Invoices (AWAITING_BUYER_CONFIRMATION) appears after Supplier Orders and Goods Receive,
        // so allow advancing to Invoices when current stage is PREPARING_SUPPLIER_ORDER or GOODS_RECEIVE.
        $canAdvanceToTarget = $currentStage->isBefore($targetStage)
            || ($targetStage === RequestStage::AWAITING_BUYER_CONFIRMATION
                && in_array($currentStage, [RequestStage::PREPARING_SUPPLIER_ORDER, RequestStage::GOODS_RECEIVE], true));
        if (! $canAdvanceToTarget) {
            return;
        }

        // Check if we can transition (respects business rules)
        if (! $currentStage->canTransitionTo($targetStage)) {
            return;
        }

        // Goods Receive requires every supplier order to be created, approved,
        // and sent; the tab is disabled in that case, but the stage must not
        // advance through any other path either (e.g. a stale or tampered
        // Livewire update).
        if ($targetStage === RequestStage::GOODS_RECEIVE && ! $record->isReadyForGoodsReceive()) {
            Notification::make()
                ->title('Cannot advance stage')
                ->body('Complete the Supplier Orders steps first: create the order, get it approved, and send it to the supplier.')
                ->warning()
                ->send();

            return;
        }

        // Check if items need to be matched for this stage
        if ($targetStage->requiresMatchedItems() && ! $record->all_items_matched) {
            Notification::make()
                ->title('Cannot advance stage')
                ->body('All items must be matched to articles before advancing to '.$targetStage->getLabel())
                ->warning()
                ->send();

            return;
        }

        // Perform the transition
        $record->stage = $targetStage;
        $record->save();

        // Redirect to the view page with the clicked tab active so the page reloads with the new stage.
        // This ensures tab badges (e.g. check icon on Goods Receive) render correctly without a manual refresh.
        if ($activeRelationKey !== null) {
            $url = RequestResource::getUrl('view', ['record' => $record->id, 'activeRelationManager' => $activeRelationKey]);
            $this->redirect($url, navigate: true);

            Notification::make()
                ->title('Stage updated')
                ->body('Request moved to: '.$targetStage->getLabelWithStep())
                ->success()
                ->send();

            return;
        }

        // Fallback: no redirect (e.g. when called without a tab context)
        $this->record->refresh();
        $this->refreshFormData(['stage']);
        Notification::make()
            ->title('Stage updated')
            ->body('Request moved to: '.$targetStage->getLabelWithStep())
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                RestoreAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            // Request Header Section
            Section::make()
                ->schema([
                    TextEntry::make('request_number')
                        ->label('Request number')
                        ->weight('bold')
                        ->size('md')
                        ->copyable(),
                    TextEntry::make('title')
                        ->label('Title')
                        ->weight('bold')
                        ->size('md')
                        ->columnSpan(3),
                    TextEntry::make('stage')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (RequestStage $state): string => $state->getLabelWithStep())
                        ->color(fn (RequestStage $state): string => $state->getColor())
                        ->icon(fn (RequestStage $state): string => $state->getIcon()),
                    TextEntry::make('priority')
                        ->badge(),
                    TextEntry::make('project.name')
                        ->label('Project')
                        ->icon('heroicon-o-folder')
                        ->color('primary')
                        ->placeholder('-')
                        ->url(fn (Request $record): ?string => $record->project ? ProjectResource::getUrl('index') : null),
                    TextEntry::make('buyer.name')
                        ->label('Buyer')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->url(fn (Request $record): ?string => $record->buyer ? BuyerResource::getUrl('index') : null),
                    TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime(),
                    TextEntry::make('updated_at')
                        ->label('Last Updated')
                        ->since(),
                    TextEntry::make('submission_method')
                        ->label('Source')
                        ->badge()
                        ->state(fn (Request $record): string => match ($record->submission_method) {
                            RequestSubmissionMethod::MANUAL => 'Buyer Manual Request',
                            RequestSubmissionMethod::DOCUMENT => 'Buyer Document Upload',
                            RequestSubmissionMethod::CATALOG => 'Buyer Catalog Order',
                            default => 'Staff Entry',
                        })
                        ->color(fn (Request $record): string => $record->submission_method?->getColor() ?? 'gray'),
                    TextEntry::make('submittedBy.name')
                        ->label('Submitted By')
                        ->placeholder('—')
                        ->visible(fn (Request $record): bool => $record->isPortalSubmission()),
                ])
                ->columns(4)
                ->columnSpanFull(),
            // Internal Notes
            Section::make('Internal Notes')
                ->icon('heroicon-o-document-text')
                ->schema([
                    TextEntry::make('internal_notes')
                        ->label('')
                        ->placeholder('No internal notes')
                        ->markdown()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Request $record): bool => $record->internal_notes !== null && $record->internal_notes !== '')
                ->columnSpanFull(),

            // Proof of Request (buyer uploads + staff proof documents)
            Section::make('Proof of Request')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    TextEntry::make('proof_of_request')
                        ->label('')
                        ->state(fn (Request $record): HtmlString => $this->getProofOfRequestList($record))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Request $record): bool => $record->getMedia('attachments')->isNotEmpty())
                ->columnSpanFull(),

            // Financial Summary, Payment Terms & Shipment (three columns)
            Grid::make(3)
                ->schema([
                    Section::make('Financials')
                        ->icon('heroicon-o-banknotes')
                        ->extraAttributes(['class' => 'three-column-section'])
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('buyer_total_display')
                                        ->label('Buyer Total')
                                        ->state(fn (Request $record): string => $this->getDisplayBuyerTotal($record))
                                        ->color(fn (Request $record): string => $this->getBuyerTotalColor($record)),
                                    TextEntry::make('supplier_cost_display')
                                        ->label('Supplier Costs')
                                        ->state(fn (Request $record): string => $this->getDisplaySupplierCost($record))
                                        ->color(fn (Request $record): string => $this->getSupplierCostColor($record)),
                                    TextEntry::make('gross_margin_display')
                                        ->label('Gross Margin')
                                        ->state(fn (Request $record): string => $this->getDisplayGrossMargin($record))
                                        ->color(fn (Request $record): string => $this->getGrossMarginColor($record)),
                                    TextEntry::make('margin_percent_display')
                                        ->label('Margin %')
                                        ->state(fn (Request $record): string => $this->getDisplayMarginPercent($record))
                                        ->badge()
                                        ->color(fn (Request $record): string => $this->getMarginPercentColor($record)),
                                ]),
                        ]),
                    Section::make('Payments')
                        ->icon('heroicon-o-credit-card')
                        ->extraAttributes(['class' => 'three-column-section'])
                        ->schema([
                            TextEntry::make('prepayment_display')
                                ->label('Prepayment')
                                ->state(fn (Request $record): string => $this->getPrepaymentDisplay($record))
                                ->placeholder('No prepayment')
                                ->columnSpanFull(),
                            TextEntry::make('payment_terms_list')
                                ->label('Payment Terms')
                                ->state(fn (Request $record): HtmlString => $this->getPaymentTermsList($record))
                                ->placeholder('No payment terms')
                                ->columnSpanFull(),
                        ]),
                    Section::make('Shipments')
                        ->icon('heroicon-o-truck')
                        ->extraAttributes(['class' => 'three-column-section'])
                        ->schema([
                            TextEntry::make('shipments_list')
                                ->state(fn (Request $record): HtmlString => $this->getShipmentsList($record))
                                ->placeholder('No shipments')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),

            // Approvals Information Section
            Section::make('Approvals Information')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (Request $record): bool => $record->quotationEvaluations()->exists() ||
                    $record->profitAndLosses()->exists() ||
                    $record->supplierOrders()->exists()
                )
                ->collapsible()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            // Quotation Evaluation
                            TextEntry::make('qe_approval_info')
                                ->label('Quotation Evaluation')
                                ->state(fn (Request $record): HtmlString => $this->getQuotationEvaluationApprovalInfo($record))
                                ->placeholder('No quotation evaluation'),
                            // Profit and Loss
                            TextEntry::make('pnl_approval_info')
                                ->label('Profit and Loss')
                                ->state(fn (Request $record): HtmlString => $this->getProfitAndLossApprovalInfo($record))
                                ->placeholder('No profit and loss'),
                            // Supplier Order
                            TextEntry::make('supplier_order_approval_info')
                                ->label('Supplier Order')
                                ->state(fn (Request $record): HtmlString => $this->getSupplierOrderApprovalInfo($record))
                                ->placeholder('No supplier order'),
                        ]),
                ])
                ->columnSpanFull(),

            // Description (if exists)
            Section::make('Description')
                ->schema([
                    TextEntry::make('description')
                        ->label('')
                        ->markdown()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Request $record): bool => $record->description !== null)
                ->columnSpanFull(),

            // Custom Fields
            CustomFields::infolist()->forSchema($schema)->build()->columnSpanFull(),
        ]);
    }

    public function getRelationManagers(): array
    {
        return [
            ItemsRelationManager::class,
            SupplierQuotesRelationManager::class,
            BuyerQuotesRelationManager::class,
            SupplierOrdersRelationManager::class,
            GoodsReceiveRelationManager::class,
            BuyerOrdersRelationManager::class,
            RelationGroup::make('Fulfillment', [
                ShipmentsRelationManager::class,
                AcceptanceReportsRelationManager::class,
            ])->tab(fn (Request $record): Tab => $this->fulfillmentTab($record)),
            CompletionReportsRelationManager::class,
        ];
    }

    /**
     * Build the Fulfillment group tab by reusing an existing channel manager's
     * stage badge + gating. Prefer the goods manager when goods are present so
     * its unapproved-Goods-Receive tab-disable is preserved; otherwise use the
     * clean services tab. Relabelled to "Fulfillment".
     */
    private function fulfillmentTab(Request $record): Tab
    {
        $source = $record->requiresShipments()
            ? ShipmentsRelationManager::class
            : AcceptanceReportsRelationManager::class;

        return $source::getTabComponent($record, self::class)->label('Fulfillment');
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Filament binds the active tab to the ?relation=<index> query param.
        // Redirects and shared links throughout the app use the readable
        // ?activeRelationManager=<key> param instead (e.g. supplierOrders);
        // translate it to the index so those URLs open the intended tab
        // rather than silently falling back to the first tab.
        if ($this->activeRelationManager === null) {
            $index = self::relationManagerIndexForKey(request()->query('activeRelationManager'));

            if ($index !== null) {
                $this->activeRelationManager = $index;
            }
        }
    }

    /**
     * Translate a relation manager key (e.g. "supplierOrders") into the tab
     * index Filament uses, or null when the key is missing or unknown.
     */
    public static function relationManagerIndexForKey(mixed $key): ?string
    {
        if (is_string($key) && array_key_exists($key, self::RELATION_MANAGER_MAP)) {
            return (string) self::RELATION_MANAGER_MAP[$key];
        }

        return null;
    }

    /**
     * Get footer widgets for the page.
     * This adds the information flow guide below the relation managers.
     */
    public function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\RequestInformationFlowWidget::class,
        ];
    }

    /**
     * Format a currency value using the team's base currency.
     */
    private function formatCurrency(float $value): string
    {
        if ($value === 0.0) {
            return '-';
        }

        /** @var \App\Models\Team|null $team */
        $team = filament()->getTenant();

        // Try to get the base currency (active)
        $currency = $team?->getBaseCurrency();

        // If not found (e.g., inactive), try to get it by code anyway
        if ($currency === null && $team !== null) {
            $code = $team->getErpSettings()->default_currency;
            $currency = Currency::query()
                ->where('code', $code)
                ->first();
        }

        if ($currency === null) {
            return number_format($value, 2);
        }

        return $currency->format($value);
    }

    /**
     * Format a percentage value.
     */
    private function formatPercentage(float $value): string
    {
        return number_format($value, 1).'%';
    }

    /**
     * Get the buyer total to display (from orders if confirmed, otherwise from quotes).
     */
    private function getDisplayBuyerTotal(Request $record): string
    {
        // Use confirmed order totals if available
        if ($record->has_buyer_order_confirmed) {
            return $this->formatCurrency($record->buyer_total);
        }

        // Fall back to draft order totals
        if ($record->buyer_total > 0) {
            return $this->formatCurrency($record->buyer_total);
        }

        // Fall back to expected totals from accepted quotes
        if ($record->expected_buyer_total > 0) {
            return $this->formatCurrency($record->expected_buyer_total);
        }

        return '-';
    }

    /**
     * Get the supplier cost to display (from orders if confirmed, otherwise from quotes).
     */
    private function getDisplaySupplierCost(Request $record): string
    {
        // Use confirmed order totals if available
        if ($record->has_supplier_order_confirmed) {
            return $this->formatCurrency($record->supplier_cost);
        }

        // Fall back to draft order totals
        if ($record->supplier_cost > 0) {
            return $this->formatCurrency($record->supplier_cost);
        }

        // Fall back to expected costs from selected quotes
        if ($record->expected_supplier_cost > 0) {
            return $this->formatCurrency($record->expected_supplier_cost);
        }

        return '-';
    }

    /**
     * Get the gross margin to display.
     */
    private function getDisplayGrossMargin(Request $record): string
    {
        $buyerTotal = $this->getEffectiveBuyerTotal($record);
        $supplierCost = $this->getEffectiveSupplierCost($record);

        if ($buyerTotal === 0.0 && $supplierCost === 0.0) {
            return '-';
        }

        return $this->formatCurrency($buyerTotal - $supplierCost);
    }

    /**
     * Get the margin percent to display.
     */
    private function getDisplayMarginPercent(Request $record): string
    {
        $buyerTotal = $this->getEffectiveBuyerTotal($record);
        $supplierCost = $this->getEffectiveSupplierCost($record);

        if ($buyerTotal === 0.0) {
            return '0.0%';
        }

        $margin = ($buyerTotal - $supplierCost) / $buyerTotal * 100;

        return $this->formatPercentage($margin);
    }

    /**
     * Get effective buyer total (confirmed orders > draft orders > expected from quotes).
     */
    private function getEffectiveBuyerTotal(Request $record): float
    {
        if ($record->has_buyer_order_confirmed || $record->buyer_total > 0) {
            return $record->buyer_total;
        }

        return $record->expected_buyer_total;
    }

    /**
     * Get effective supplier cost (confirmed orders > draft orders > expected from quotes).
     */
    private function getEffectiveSupplierCost(Request $record): float
    {
        if ($record->has_supplier_order_confirmed || $record->supplier_cost > 0) {
            return $record->supplier_cost;
        }

        return $record->expected_supplier_cost;
    }

    /**
     * Get the color for buyer total based on confirmation status.
     */
    private function getBuyerTotalColor(Request $record): string
    {
        if ($record->has_buyer_order_confirmed) {
            return 'success';
        }

        if ($record->buyer_total > 0 || $record->expected_buyer_total > 0) {
            return 'info';
        }

        return 'gray';
    }

    /**
     * Get the color for supplier cost based on confirmation status.
     */
    private function getSupplierCostColor(Request $record): string
    {
        if ($record->has_supplier_order_confirmed) {
            return 'success';
        }

        if ($record->supplier_cost > 0 || $record->expected_supplier_cost > 0) {
            return 'info';
        }

        return 'gray';
    }

    /**
     * Get the color for gross margin based on confirmation status and value.
     */
    private function getGrossMarginColor(Request $record): string
    {
        $buyerTotal = $this->getEffectiveBuyerTotal($record);
        $supplierCost = $this->getEffectiveSupplierCost($record);
        $margin = $buyerTotal - $supplierCost;

        // Check if both are confirmed
        $isConfirmed = $record->has_buyer_order_confirmed && $record->has_supplier_order_confirmed;

        if ($margin < 0) {
            return 'danger';
        }

        if ($isConfirmed) {
            return 'success';
        }

        if ($buyerTotal > 0 || $supplierCost > 0) {
            return 'info';
        }

        return 'gray';
    }

    /**
     * Get the color for margin percent based on confirmation status and value.
     */
    private function getMarginPercentColor(Request $record): string
    {
        $buyerTotal = $this->getEffectiveBuyerTotal($record);
        $supplierCost = $this->getEffectiveSupplierCost($record);

        if ($buyerTotal === 0.0) {
            return 'gray';
        }

        $marginPercent = ($buyerTotal - $supplierCost) / $buyerTotal * 100;

        // Check if both are confirmed
        $isConfirmed = $record->has_buyer_order_confirmed && $record->has_supplier_order_confirmed;

        if ($marginPercent < 5) {
            return 'danger';
        }

        if ($marginPercent < 10) {
            return 'warning';
        }

        if ($isConfirmed) {
            return 'success';
        }

        return 'info';
    }

    /**
     * Get the primary buyer order for payment terms display.
     * Prefers confirmed orders, then most recent order.
     */
    private function getPrimaryBuyerOrder(Request $record): ?BuyerOrder
    {
        // Try to get confirmed order first
        $confirmedOrder = $record->buyerOrders()
            ->whereNotIn('status', [\App\Enums\OrderStatus::DRAFT, \App\Enums\OrderStatus::CANCELLED])
            ->with('buyerQuote')
            ->orderByDesc('confirmed_at')
            ->first();

        if ($confirmedOrder !== null) {
            return $confirmedOrder;
        }

        // Fall back to most recent order
        return $record->buyerOrders()
            ->with('buyerQuote')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get prepayment display value from buyer quote.
     */
    private function getPrepaymentDisplay(Request $record): string
    {
        $buyerOrder = $this->getPrimaryBuyerOrder($record);

        if ($buyerOrder === null || $buyerOrder->buyerQuote === null) {
            return '';
        }

        $quote = $buyerOrder->buyerQuote;

        if ($quote->prepayment_type === PrepaymentType::PERCENT) {
            return $quote->prepayment_percent.'%';
        }

        if ($quote->prepayment_type === PrepaymentType::FIXED) {
            return $this->formatCurrency((float) $quote->prepayment_amount);
        }

        return '';
    }

    /**
     * Get payment terms list as HTML.
     */
    private function getPaymentTermsList(Request $record): HtmlString
    {
        $buyerOrder = $this->getPrimaryBuyerOrder($record);

        if ($buyerOrder === null || $buyerOrder->buyerQuote === null) {
            return new HtmlString('<span class="text-gray-400">No payment terms</span>');
        }

        $quote = $buyerOrder->buyerQuote;
        $paymentTerms = $quote->paymentTerms;

        if ($paymentTerms->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">No payment terms</span>');
        }

        $rows = [];
        foreach ($paymentTerms as $term) {
            $status = $this->getPaymentTermStatus($record, $term->due_days, $term->percentage);
            $statusColor = $status === 'Paid' ? 'success' : 'warning';
            $statusBadge = sprintf(
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-%s-100 text-%s-800">%s</span>',
                $statusColor,
                $statusColor,
                htmlspecialchars($status)
            );

            $rows[] = sprintf(
                '<tr><td class="pr-4">%d days</td><td class="pr-4 text-left">%d%%</td><td>%s</td></tr>',
                $term->due_days,
                $term->percentage,
                $statusBadge
            );
        }

        $html = '<table class="text-sm w-full"><thead><tr class="text-gray-500"><th class="text-left pr-4">Due Days</th><th class="text-left pr-4">Percentage</th><th class="text-left">Status</th></tr></thead><tbody>'.implode('', $rows).'</tbody></table>';

        return new HtmlString($html);
    }

    /**
     * Get payment term status (Paid/Not Paid) based on invoice payments or approved acceptance report.
     */
    private function getPaymentTermStatus(Request $record, int $dueDays, int $percentage): string
    {
        // If an acceptance report payment document for this term is approved, consider Paid
        $paymentTermsKey = "{$dueDays}-{$percentage}";
        $paymentMedia = $record->getMedia('completion_reports')
            ->filter(fn ($media) => (bool) $media->getCustomProperty('is_payment_document', false)
                && $media->getCustomProperty('payment_terms') === $paymentTermsKey);
        $paymentMediaIds = $paymentMedia->pluck('id')->toArray();
        if ($paymentMediaIds !== [] && $record->team_id !== null) {
            $hasApprovedDoc = PaymentDocumentApproval::query()
                ->whereIn('media_id', $paymentMediaIds)
                ->where('team_id', $record->team_id)
                ->exists();
            if ($hasApprovedDoc) {
                return 'Paid';
            }
        }

        // Get invoices for this request with matching net_days
        $invoices = BuyerInvoice::query()
            ->where('request_id', $record->getKey())
            ->where('net_days', $dueDays)
            ->whereNotIn('status', [InvoiceStatus::CANCELLED, InvoiceStatus::DRAFT])
            ->get();

        if ($invoices->isEmpty()) {
            return 'Not Paid';
        }

        // Calculate expected payment amount based on percentage
        $buyerTotal = $this->getEffectiveBuyerTotal($record);
        $expectedAmount = ($buyerTotal * $percentage) / 100;

        // Sum up paid amounts from invoices
        $paidAmount = 0.0;
        foreach ($invoices as $invoice) {
            $paidAmount += (float) $invoice->amount_paid;
        }

        // Consider paid if paid amount equals or exceeds expected amount (with small tolerance)
        if ($paidAmount >= ($expectedAmount - 0.01)) {
            return 'Paid';
        }

        return 'Not Paid';
    }

    /**
     * Get shipments list as HTML.
     */
    private function getShipmentsList(Request $record): HtmlString
    {
        $shipments = $record->shipments()->orderByDesc('created_at')->get();

        if ($shipments->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">No shipments</span>');
        }

        $rows = [];
        foreach ($shipments as $shipment) {
            $statusBadge = sprintf(
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-%s-100 text-%s-800">%s</span>',
                $shipment->status->getColor(),
                $shipment->status->getColor(),
                htmlspecialchars($shipment->status->getLabel())
            );

            $rows[] = sprintf(
                '<tr><td class="pr-4 font-medium">%s</td><td class="pr-4">%s</td><td class="pr-4">%s</td><td>%s</td></tr>',
                htmlspecialchars($shipment->shipment_number),
                $statusBadge,
                htmlspecialchars($shipment->carrier_name ?? '-'),
                htmlspecialchars($shipment->tracking_number ?? '-')
            );
        }

        $html = '<table class="text-sm w-full"><thead><tr class="text-gray-500"><th class="text-left pr-4">Shipment #</th><th class="text-left pr-4">Status</th><th class="text-left pr-4">Carrier</th><th class="text-left">Tracking</th></tr></thead><tbody>'.implode('', $rows).'</tbody></table>';

        return new HtmlString($html);
    }

    /**
     * Get the proof of request document list as HTML.
     *
     * Lists every media item in the request's `attachments` collection (both
     * buyer-uploaded documents and staff proof uploads) as a private, team-scoped
     * download link served by the `documents.download` route.
     */
    private function getProofOfRequestList(Request $record): HtmlString
    {
        $media = $record->getMedia('attachments');

        if ($media->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">No proof of request</span>');
        }

        $rows = [];
        foreach ($media as $item) {
            $rows[] = sprintf(
                '<li><a href="%s" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 hover:underline">%s %s</a></li>',
                htmlspecialchars(route('documents.download', $item)),
                $this->getIconSvg('heroicon-o-paper-clip'),
                htmlspecialchars($item->file_name)
            );
        }

        return new HtmlString('<ul class="space-y-1 text-sm">'.implode('', $rows).'</ul>');
    }

    /**
     * Get Quotation Evaluation approval information.
     */
    private function getQuotationEvaluationApprovalInfo(Request $record): HtmlString
    {
        $qe = $record->quotationEvaluations()->latest()->first();

        if ($qe === null) {
            return new HtmlString('<span class="text-gray-400">No quotation evaluation</span>');
        }

        $approvalCount = $qe->approvalCount();
        $totalApprovers = $qe->totalApproversCount();
        $status = $qe->status;
        $isApproved = $status === QEStatus::APPROVED;
        $statusIcon = $status->getIcon();
        $statusLabel = htmlspecialchars($status->getLabel());

        // Build icon SVG based on heroicon name
        $iconSvg = $this->getIconSvg($statusIcon);

        // Set colors based on status using inline styles
        if ($isApproved) {
            $style = 'background-color: rgb(220 252 231); color: rgb(22 101 52); border-color: rgb(187 247 208);'; // green
        } else {
            // Pending - orange/cream color scheme
            $style = 'background-color: rgb(255 237 213); color: rgb(154 52 18); border-color: rgb(253 186 116);'; // orange
        }

        $qeUrl = QuotationEvaluationResource::getUrl('view', ['record' => $qe]);
        $qeNumber = htmlspecialchars($qe->qe_number);

        $html = sprintf(
            '<div class="space-y-1"><div class="font-medium"><a href="%s" class="text-primary-600 hover:text-primary-700 hover:underline">%s</a></div><div class="flex items-center gap-2"><span class="text-sm text-gray-600">Approval: %d/%d</span><span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border" style="%s">%s %s</span></div></div>',
            htmlspecialchars($qeUrl),
            $qeNumber,
            $approvalCount,
            $totalApprovers,
            $style,
            $iconSvg,
            $statusLabel
        );

        return new HtmlString($html);
    }

    /**
     * Get Profit and Loss approval information.
     */
    private function getProfitAndLossApprovalInfo(Request $record): HtmlString
    {
        $pnl = $record->profitAndLosses()->latest()->first();

        if ($pnl === null) {
            return new HtmlString('<span class="text-gray-400">No profit and loss</span>');
        }

        $approvalCount = $pnl->approvalCount();
        $totalApprovers = $pnl->totalApproversCount();
        $status = $pnl->status;
        $isApproved = $status === \App\Enums\PNLStatus::APPROVED;
        $isPending = $status === \App\Enums\PNLStatus::NEED_APPROVAL || $status === \App\Enums\PNLStatus::PENDING;
        $statusIcon = $status->getIcon();
        $statusLabel = htmlspecialchars($status->getLabel());

        // Build icon SVG based on heroicon name
        $iconSvg = $this->getIconSvg($statusIcon);

        // Set colors based on status using inline styles
        if ($isApproved) {
            $style = 'background-color: rgb(220 252 231); color: rgb(22 101 52); border-color: rgb(187 247 208);'; // green
        } elseif ($isPending) {
            // Pending - orange/cream color scheme
            $style = 'background-color: rgb(255 237 213); color: rgb(154 52 18); border-color: rgb(253 186 116);'; // orange
        } else {
            // Other statuses - gray
            $style = 'background-color: rgb(243 244 246); color: rgb(31 41 55); border-color: rgb(209 213 219);'; // gray
        }

        $pnlUrl = ProfitAndLossResource::getUrl('view', ['record' => $pnl]);
        $pnlNumber = htmlspecialchars($pnl->pnl_number);

        $html = sprintf(
            '<div class="space-y-1"><div class="font-medium"><a href="%s" class="text-primary-600 hover:text-primary-700 hover:underline">%s</a></div><div class="flex items-center gap-2"><span class="text-sm text-gray-600">Approval: %d/%d</span><span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border" style="%s">%s %s</span></div></div>',
            htmlspecialchars($pnlUrl),
            $pnlNumber,
            $approvalCount,
            $totalApprovers,
            $style,
            $iconSvg,
            $statusLabel
        );

        return new HtmlString($html);
    }

    /**
     * Get Supplier Order approval information.
     */
    private function getSupplierOrderApprovalInfo(Request $record): HtmlString
    {
        $supplierOrders = $record->supplierOrders()->get();

        if ($supplierOrders->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">No supplier order</span>');
        }

        $supplierOrdersUrl = RequestResource::getUrl('view', ['record' => $record]).'?activeRelationManager=supplierOrders';

        $cards = [];

        foreach ($supplierOrders as $order) {
            // Calculate approval count (0, 1, or 2)
            $approvalCount = 0;
            if ($order->approver_1_id !== null) {
                $approvalCount++;
            }
            if ($order->approver_2_id !== null) {
                $approvalCount++;
            }

            $totalApprovers = 2; // Supplier orders always require 2 approvers
            $bothApproved = $order->approver_1_id !== null && $order->approver_2_id !== null;

            // Match the Quotation Evaluation / Profit and Loss card styling:
            // green + check badge when approved, orange + clock while pending.
            if ($bothApproved) {
                $style = 'background-color: rgb(220 252 231); color: rgb(22 101 52); border-color: rgb(187 247 208);'; // green
                $statusIcon = 'heroicon-o-check-badge';
                $statusLabel = 'Approved';
            } else {
                $style = 'background-color: rgb(255 237 213); color: rgb(154 52 18); border-color: rgb(253 186 116);'; // orange
                $statusIcon = 'heroicon-o-clock';
                $statusLabel = 'Pending';
            }

            $iconSvg = $this->getIconSvg($statusIcon);
            $poNumber = htmlspecialchars($order->po_number ?? 'N/A');

            $cards[] = sprintf(
                '<div class="space-y-1"><div class="font-medium"><a href="%s" class="text-primary-600 hover:text-primary-700 hover:underline">%s</a></div><div class="flex items-center gap-2"><span class="text-sm text-gray-600">Approval: %d/%d</span><span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border" style="%s">%s %s</span></div></div>',
                htmlspecialchars($supplierOrdersUrl),
                $poNumber,
                $approvalCount,
                $totalApprovers,
                $style,
                $iconSvg,
                htmlspecialchars($statusLabel)
            );
        }

        return new HtmlString('<div class="space-y-3">'.implode('', $cards).'</div>');
    }

    /**
     * Get icon SVG markup for heroicon names.
     */
    private function getIconSvg(string $heroiconName): string
    {
        // Map heroicon names to their SVG paths (using heroicon v1 outline style)
        $icons = [
            'heroicon-o-check-badge' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            'heroicon-o-clock' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            'heroicon-s-check-circle' => '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
            'heroicon-o-paper-clip' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>',
        ];

        return $icons[$heroiconName] ?? '';
    }
}
