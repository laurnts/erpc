<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\RequestResource;
use App\Models\BuyerInvoice;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class AwaitingPaymentWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Awaiting Payment';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('request.title')
                    ->label('Request')
                    ->limit(25)
                    
                    ->sortable(),

                TextColumn::make('request.buyer.name')
                    ->label('Buyer')
                    
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->prefix(fn (BuyerInvoice $record): string => $record->currency->symbol ?? ''),

                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->prefix(fn (BuyerInvoice $record): string => $record->currency->symbol ?? ''),

                TextColumn::make('amount_outstanding')
                    ->label('Outstanding')
                    ->getStateUsing(fn (BuyerInvoice $record): float => $record->amount_outstanding)
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (BuyerInvoice $record): string => $record->currency->symbol ?? '')
                    ->color(fn (BuyerInvoice $record): string => $record->status === InvoiceStatus::OVERDUE ? 'danger' : 'warning')
                    ->weight('bold'),

                TextColumn::make('due_at')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn (BuyerInvoice $record): string => $this->getDueDateColor($record)),

                TextColumn::make('days_overdue')
                    ->label('Overdue')
                    ->getStateUsing(fn (BuyerInvoice $record): string => $this->getOverdueDisplay($record))
                    ->badge()
                    ->color(fn (BuyerInvoice $record): string => $record->days_overdue > 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('due_at', 'asc')
            ->recordActions([
                Action::make('view')
                    ->label('View Request')
                    ->icon('heroicon-o-eye')
                    ->url(fn (BuyerInvoice $record): string => RequestResource::getUrl('view', ['record' => $record->request_id])),
            ])
            ->emptyStateHeading('No Pending Payments')
            ->emptyStateDescription('All invoices have been paid.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * @return Builder<BuyerInvoice>
     */
    protected function getTableQuery(): Builder
    {
        $teamId = Filament::getTenant()?->getKey();

        if ($teamId === null) {
            return BuyerInvoice::query()->whereRaw('1 = 0');
        }

        return BuyerInvoice::query()
            ->with(['request', 'request.buyer', 'currency'])
            ->where('team_id', $teamId)
            ->whereIn('status', [
                InvoiceStatus::SENT,
                InvoiceStatus::PARTIAL,
                InvoiceStatus::OVERDUE,
            ]);
    }

    /**
     * Get the color based on due date.
     */
    private function getDueDateColor(BuyerInvoice $record): string
    {
        if ($record->due_at === null) {
            return 'gray';
        }

        if ($record->due_at->isPast()) {
            return 'danger';
        }

        if ($record->due_at->isToday() || $record->due_at->diffInDays(now()) <= 3) {
            return 'warning';
        }

        return 'gray';
    }

    /**
     * Get the overdue display string.
     */
    private function getOverdueDisplay(BuyerInvoice $record): string
    {
        $daysOverdue = $record->days_overdue;

        if ($daysOverdue <= 0) {
            return '-';
        }

        if ($daysOverdue === 1) {
            return '1 day';
        }

        return $daysOverdue.' days';
    }
}
