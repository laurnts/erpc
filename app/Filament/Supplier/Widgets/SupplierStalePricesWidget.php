<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Widgets;

use App\Filament\Supplier\Resources\SupplierArticleResource;
use App\Models\SupplierArticle;
use App\Services\Portal\SupplierPortalContext;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class SupplierStalePricesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $heading = 'Prices Needing an Update';

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
                SupplierArticle::query()
                    ->forSupplier($companyId)
                    ->where('is_active', true)
                    ->with(['article', 'supplierPriceCurrency'])
                    ->orderByRaw("COALESCE(supplier_price_updated_at, '1970-01-01 00:00:00') ASC")
                    ->orderByRaw("COALESCE(quantity_updated_at, '1970-01-01 00:00:00') ASC")
                    ->limit(10),
            )
            ->columns([
                TextColumn::make('article.name')
                    ->label('Article')
                    ->weight('bold'),
                TextColumn::make('supplier_price')
                    ->label('Your Price')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('Not set'),
                TextColumn::make('supplierPriceCurrency.code')
                    ->label('Currency')
                    ->placeholder('—'),
                TextColumn::make('available_quantity')
                    ->label('Available Qty')
                    ->numeric()
                    ->placeholder('Unknown'),
                TextColumn::make('supplier_price_updated_at')
                    ->label('Price Updated')
                    ->dateTime()
                    ->placeholder('Never'),
                TextColumn::make('quantity_updated_at')
                    ->label('Quantity Updated')
                    ->dateTime()
                    ->placeholder('Never'),
            ])
            ->recordUrl(fn (SupplierArticle $record): string => SupplierArticleResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
