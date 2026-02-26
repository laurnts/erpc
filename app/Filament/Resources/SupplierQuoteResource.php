<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Exports\SupplierQuoteExporter;
use App\Filament\Resources\SupplierQuoteResource\Pages\ListSupplierQuotes;
use App\Models\SupplierQuote;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;

final class SupplierQuoteResource extends Resource
{
    protected static ?string $model = SupplierQuote::class;

    protected static ?string $recordTitleAttribute = 'quote_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Supplier Quotes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    
                    ->sortable()
                    ->url(fn (SupplierQuote $record): string => RequestResource::getUrl('view', ['record' => $record->request_id])),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    
                    ->sortable(),
                TextColumn::make('supplier_reference')
                    ->label('Supplier Ref')
                    
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->description(fn (SupplierQuote $record): string => $record->currency->code ?? ''),
                TextColumn::make('total_base')
                    ->label('Total (Base)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable()
                    ->color(fn (SupplierQuote $record): string => $record->is_expired ? 'danger' : 'success'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('quoted_at')
                    ->label('Quoted')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(SupplierQuoteStatus::class)
                    ->multiple(),
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name', fn ($query) => $query->where('is_supplier', true))
                    ->label('Supplier')
                    ->preload()
                    ,
                SelectFilter::make('request_id')
                    ->relationship('request', 'request_number')
                    ->label('Request')
                    ->preload()
                    ,
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (SupplierQuote $record): string => RequestResource::getUrl('view', ['record' => $record->request_id]))
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->exporter(SupplierQuoteExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSupplierQuotes::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['quote_number', 'supplier.name', 'supplier_reference'];
    }

    /**
     * @return Builder<SupplierQuote>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
