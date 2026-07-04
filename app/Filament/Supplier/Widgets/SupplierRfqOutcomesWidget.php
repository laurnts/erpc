<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Widgets;

use App\Filament\Supplier\Resources\SupplierRfqResource;
use App\Models\SupplierQuote;
use App\Services\Portal\SupplierPortalContext;
use App\Services\SupplierPortal\SupplierRfqStatusPresenter;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Recent announced outcomes for the supplier's own quotes. Only quotes whose
 * round has been announced surface here — pre-announcement evaluation state
 * never leaks to the dashboard.
 */
final class SupplierRfqOutcomesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Recent Quote Outcomes';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'supplier';
    }

    public function table(Table $table): Table
    {
        $companyId = app(SupplierPortalContext::class)->companyId();
        $presenter = app(SupplierRfqStatusPresenter::class);

        return $table
            ->query(
                SupplierQuote::query()
                    ->forSupplierPortal($companyId)
                    ->whereNotNull('outcomes_announced_at')
                    ->orderByDesc('outcomes_announced_at')
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Reference')
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Outcome')
                    ->badge()
                    ->formatStateUsing(fn (SupplierQuote $record): string => $presenter->label($record))
                    ->color(fn (SupplierQuote $record): string => $presenter->color($record)),
                TextColumn::make('outcomes_announced_at')
                    ->label('Announced')
                    ->dateTime(),
            ])
            ->recordUrl(fn (SupplierQuote $record): string => SupplierRfqResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
