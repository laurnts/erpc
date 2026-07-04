<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources;

use App\Filament\Supplier\Resources\SupplierArticleResource\Pages\EditSupplierArticle;
use App\Filament\Supplier\Resources\SupplierArticleResource\Pages\ListSupplierArticles;
use App\Filament\Supplier\Resources\SupplierArticleResource\Schemas\SupplierArticleForm;
use App\Models\SupplierArticle;
use App\Services\Portal\SupplierPortalContext;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SupplierArticleResource extends Resource
{
    protected static ?string $model = SupplierArticle::class;

    protected static ?string $modelLabel = 'Article';

    protected static ?string $pluralModelLabel = 'My Articles';

    protected static ?string $navigationLabel = 'My Articles';

    protected static ?string $slug = 'my-articles';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(SupplierArticleForm::components());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('article.name')
                    ->label('Article')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('article.unit')
                    ->label('Unit'),
                TextColumn::make('supplier_sku')
                    ->label('Your SKU')
                    ->placeholder('—'),
                TextColumn::make('supplier_price')
                    ->label('Your Price')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('supplierPriceCurrency.code')
                    ->label('Currency')
                    ->placeholder('—'),
                TextColumn::make('available_quantity')
                    ->label('Available Qty')
                    ->numeric()
                    ->placeholder('Unknown')
                    ->sortable(),
                TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('supplier_price_updated_at')
                    ->label('Price Updated')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('quantity_updated_at')
                    ->label('Quantity Updated')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->defaultSort('id')
            ->recordUrl(fn (SupplierArticle $record): string => self::getUrl('edit', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierArticles::route('/'),
            'edit' => EditSupplierArticle::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<SupplierArticle>
     */
    public static function getEloquentQuery(): Builder
    {
        $companyId = app(SupplierPortalContext::class)->companyId();

        /** @var Builder<SupplierArticle> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->forSupplier($companyId)
            ->with(['article', 'supplierPriceCurrency']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
