<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Widgets;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Supplier\Resources\SupplierRfqResource;
use App\Models\SupplierQuote;
use App\Services\Portal\SupplierPortalContext;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class SupplierOpenRfqsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Open Quote Requests';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'supplier';
    }

    public function table(Table $table): Table
    {
        $companyId = app(SupplierPortalContext::class)->companyId();

        return $table
            ->query(
                SupplierQuote::query()
                    ->forSupplierPortal($companyId)
                    ->where('status', SupplierQuoteStatus::PENDING)
                    ->whereNull('declined_at')
                    ->where(fn (Builder $query): Builder => $query
                        ->whereNull('valid_until')
                        ->orWhereDate('valid_until', '>', today()))
                    ->orderByDesc('sent_to_supplier_at')
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Reference')
                    ->weight('bold'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('sent_to_supplier_at')
                    ->label('Received')
                    ->dateTime(),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (SupplierQuote $record): string => SupplierRfqResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
