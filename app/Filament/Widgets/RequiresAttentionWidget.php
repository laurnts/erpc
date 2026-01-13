<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\BuyerQuoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Filament\Resources\RequestResource;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerQuote;
use App\Models\Shipment;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class RequiresAttentionWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Requires Attention';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (Model $record): string => $this->getTypeColor($record)),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (Model $record): string => $this->getReference($record))
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->getStateUsing(fn (Model $record): string => $this->getDescription($record))
                    ->limit(50),

                TextColumn::make('request_title')
                    ->label('Request')
                    ->getStateUsing(fn (Model $record): string => $this->getRequestTitle($record))
                    ->limit(30),

                TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (Model $record): string => $this->getStatus($record))
                    ->badge()
                    ->color(fn (Model $record): string => $this->getStatusColor($record)),

                TextColumn::make('urgency')
                    ->label('Urgency')
                    ->getStateUsing(fn (Model $record): string => $this->getUrgency($record))
                    ->badge()
                    ->color(fn (Model $record): string => $this->getUrgencyColor($record)),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Model $record): string => $this->getViewUrl($record)),
            ])
            ->emptyStateHeading('Nothing Requires Attention')
            ->emptyStateDescription('All items are up to date.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * Build a union query of all items requiring attention.
     *
     * @return Builder<BuyerQuote>
     */
    protected function getTableQuery(): Builder
    {
        $teamId = Filament::getTenant()?->getKey();

        if ($teamId === null) {
            return BuyerQuote::query()->whereRaw('1 = 0');
        }

        // Collect all attention items
        $attentionItems = $this->collectAttentionItems($teamId);

        if ($attentionItems->isEmpty()) {
            return BuyerQuote::query()->whereRaw('1 = 0');
        }

        // Return the first model type as base query with all IDs
        $firstItem = $attentionItems->first();
        $modelClass = $firstItem::class;

        $ids = $attentionItems
            ->filter(fn (Model $item): bool => $item instanceof $modelClass)
            ->pluck('id')
            ->toArray();

        return $modelClass::query()->whereIn('id', $ids);
    }

    /**
     * Collect all items requiring attention.
     *
     * @return Collection<int, Model>
     */
    private function collectAttentionItems(int $teamId): Collection
    {
        $items = collect();

        // Quotes pending response (sent status, waiting for buyer response)
        $pendingQuotes = BuyerQuote::query()
            ->where('team_id', $teamId)
            ->where('status', BuyerQuoteStatus::SENT)
            ->where(function (Builder $query): void {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->limit(10)
            ->get();
        $items = $items->merge($pendingQuotes);

        // Orders pending confirmation (draft status)
        $pendingOrders = BuyerOrder::query()
            ->where('team_id', $teamId)
            ->where('status', OrderStatus::DRAFT)
            ->limit(10)
            ->get();
        $items = $items->merge($pendingOrders);

        // Shipments pending delivery (in transit)
        $pendingShipments = Shipment::query()
            ->where('team_id', $teamId)
            ->whereIn('status', [ShipmentStatus::PENDING, ShipmentStatus::IN_TRANSIT])
            ->limit(10)
            ->get();
        $items = $items->merge($pendingShipments);

        // Overdue invoices
        $overdueInvoices = BuyerInvoice::query()
            ->where('team_id', $teamId)
            ->where('status', InvoiceStatus::OVERDUE)
            ->limit(10)
            ->get();
        $items = $items->merge($overdueInvoices);

        return $items->sortByDesc('created_at')->take(20);
    }

    /**
     * Get the type color for a record.
     */
    private function getTypeColor(Model $record): string
    {
        return match (true) {
            $record instanceof BuyerQuote => 'info',
            $record instanceof BuyerOrder => 'primary',
            $record instanceof Shipment => 'warning',
            $record instanceof BuyerInvoice => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the reference number for a record.
     */
    private function getReference(Model $record): string
    {
        return match (true) {
            $record instanceof BuyerQuote => $record->quote_number,
            $record instanceof BuyerOrder => $record->order_number,
            $record instanceof Shipment => $record->shipment_number,
            $record instanceof BuyerInvoice => $record->invoice_number,
            default => '-',
        };
    }

    /**
     * Get the description for a record.
     */
    private function getDescription(Model $record): string
    {
        return match (true) {
            $record instanceof BuyerQuote => 'Awaiting buyer response',
            $record instanceof BuyerOrder => 'Order pending confirmation',
            $record instanceof Shipment => $record->status === ShipmentStatus::PENDING ? 'Shipment not dispatched' : 'Shipment in transit',
            $record instanceof BuyerInvoice => 'Invoice overdue - '.$record->days_overdue.' days',
            default => '-',
        };
    }

    /**
     * Get the request title for a record.
     */
    private function getRequestTitle(Model $record): string
    {
        $request = match (true) {
            $record instanceof BuyerQuote, $record instanceof BuyerOrder, $record instanceof Shipment, $record instanceof BuyerInvoice => $record->request,
            default => null,
        };

        return $request->title ?? '-';
    }

    /**
     * Get the status label for a record.
     */
    private function getStatus(Model $record): string
    {
        return match (true) {
            $record instanceof BuyerQuote => $record->status->getLabel(),
            $record instanceof BuyerOrder => $record->status->getLabel(),
            $record instanceof Shipment => $record->status->getLabel(),
            $record instanceof BuyerInvoice => $record->status->getLabel(),
            default => '-',
        };
    }

    /**
     * Get the status color for a record.
     */
    private function getStatusColor(Model $record): string
    {
        return match (true) {
            $record instanceof BuyerQuote => $record->status->getColor(),
            $record instanceof BuyerOrder => $record->status->getColor(),
            $record instanceof Shipment => $record->status->getColor(),
            $record instanceof BuyerInvoice => $record->status->getColor(),
            default => 'gray',
        };
    }

    /**
     * Get the urgency level for a record.
     */
    private function getUrgency(Model $record): string
    {
        return match (true) {
            $record instanceof BuyerQuote => $this->getQuoteUrgency($record),
            $record instanceof BuyerOrder => 'Medium',
            $record instanceof Shipment => $this->getShipmentUrgency($record),
            $record instanceof BuyerInvoice => $this->getInvoiceUrgency($record),
            default => 'Low',
        };
    }

    /**
     * Get urgency for a quote.
     */
    private function getQuoteUrgency(BuyerQuote $quote): string
    {
        if ($quote->valid_until === null) {
            return 'Low';
        }

        $daysUntilExpiry = (int) now()->startOfDay()->diffInDays($quote->valid_until->startOfDay(), absolute: false);

        if ($daysUntilExpiry <= 0) {
            return 'Critical';
        }

        if ($daysUntilExpiry <= 3) {
            return 'High';
        }

        if ($daysUntilExpiry <= 7) {
            return 'Medium';
        }

        return 'Low';
    }

    /**
     * Get urgency for a shipment.
     */
    private function getShipmentUrgency(Shipment $shipment): string
    {
        if ($shipment->expected_delivery_at === null) {
            return 'Medium';
        }

        if ($shipment->expected_delivery_at->isPast()) {
            return 'Critical';
        }

        if ($shipment->expected_delivery_at->isToday()) {
            return 'High';
        }

        return 'Medium';
    }

    /**
     * Get urgency for an invoice.
     */
    private function getInvoiceUrgency(BuyerInvoice $invoice): string
    {
        $daysOverdue = $invoice->days_overdue;

        if ($daysOverdue > 30) {
            return 'Critical';
        }

        if ($daysOverdue > 14) {
            return 'High';
        }

        return 'Medium';
    }

    /**
     * Get the urgency color for a record.
     */
    private function getUrgencyColor(Model $record): string
    {
        $urgency = $this->getUrgency($record);

        return match ($urgency) {
            'Critical' => 'danger',
            'High' => 'warning',
            'Medium' => 'info',
            default => 'gray',
        };
    }

    /**
     * Get the view URL for a record.
     */
    private function getViewUrl(Model $record): string
    {
        $requestId = match (true) {
            $record instanceof BuyerQuote, $record instanceof BuyerOrder, $record instanceof Shipment, $record instanceof BuyerInvoice => $record->request_id,
            default => null,
        };

        if ($requestId === null) {
            return '#';
        }

        return RequestResource::getUrl('view', ['record' => $requestId]);
    }
}
