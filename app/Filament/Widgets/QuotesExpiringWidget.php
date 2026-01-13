<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\BuyerQuoteStatus;
use App\Filament\Resources\RequestResource;
use App\Models\BuyerQuote;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class QuotesExpiringWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Quotes Expiring Soon';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('request.title')
                    ->label('Request')
                    ->limit(30)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->prefix(fn (BuyerQuote $record): string => $record->currency->symbol ?? ''),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (BuyerQuote $record): string => $this->getExpiryColor($record)),

                TextColumn::make('days_until_expiry')
                    ->label('Days Left')
                    ->getStateUsing(fn (BuyerQuote $record): string => $this->getDaysUntilExpiry($record))
                    ->badge()
                    ->color(fn (BuyerQuote $record): string => $this->getExpiryColor($record)),
            ])
            ->defaultSort('valid_until', 'asc')
            ->recordActions([
                Action::make('view')
                    ->label('View Request')
                    ->icon('heroicon-o-eye')
                    ->url(fn (BuyerQuote $record): string => RequestResource::getUrl('view', ['record' => $record->request_id])),
            ])
            ->emptyStateHeading('No Expiring Quotes')
            ->emptyStateDescription('There are no quotes expiring in the next 7 days.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * @return Builder<BuyerQuote>
     */
    protected function getTableQuery(): Builder
    {
        $teamId = Filament::getTenant()?->getKey();

        if ($teamId === null) {
            return BuyerQuote::query()->whereRaw('1 = 0');
        }

        return BuyerQuote::query()
            ->with(['request', 'buyer', 'currency'])
            ->where('team_id', $teamId)
            ->whereIn('status', [BuyerQuoteStatus::DRAFT, BuyerQuoteStatus::SENT])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', now())
            ->whereDate('valid_until', '<=', now()->addDays(7));
    }

    /**
     * Get the number of days until expiry as a display string.
     */
    private function getDaysUntilExpiry(BuyerQuote $record): string
    {
        if ($record->valid_until === null) {
            return 'N/A';
        }

        $days = (int) now()->startOfDay()->diffInDays($record->valid_until->startOfDay(), absolute: false);

        if ($days < 0) {
            return 'Expired';
        }

        if ($days === 0) {
            return 'Today';
        }

        if ($days === 1) {
            return '1 day';
        }

        return $days.' days';
    }

    /**
     * Get the color based on days until expiry.
     */
    private function getExpiryColor(BuyerQuote $record): string
    {
        if ($record->valid_until === null) {
            return 'gray';
        }

        $days = (int) now()->startOfDay()->diffInDays($record->valid_until->startOfDay(), absolute: false);

        if ($days <= 0) {
            return 'danger';
        }

        if ($days <= 3) {
            return 'warning';
        }

        return 'success';
    }
}
