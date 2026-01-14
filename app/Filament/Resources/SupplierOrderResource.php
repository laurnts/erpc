<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\SupplierOrderResource\Pages\ListSupplierOrders;
use App\Models\SupplierOrder;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;

final class SupplierOrderResource extends Resource
{
    protected static ?string $model = SupplierOrder::class;

    protected static ?string $recordTitleAttribute = 'po_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Supplier Orders';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    ->searchable()
                    ->sortable()
                    ->url(fn (SupplierOrder $record): string => RequestResource::getUrl('view', ['record' => $record->request_id])),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplierQuote.quote_number')
                    ->label('From Quote')
                    ->sortable()
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
                    ->description(fn (SupplierOrder $record): string => $record->currency->code ?? ''),
                TextColumn::make('base_total')
                    ->label('Total (Base)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('expected_delivery_date')
                    ->label('Expected Delivery')
                    ->date()
                    ->sortable(),
                TextColumn::make('ordered_at')
                    ->label('Ordered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('confirmed_at')
                    ->label('Confirmed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable()
                    ->alignCenter(),
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
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name', fn ($query) => $query->where('is_supplier', true))
                    ->label('Supplier')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('request_id')
                    ->relationship('request', 'request_number')
                    ->label('Request')
                    ->preload()
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (SupplierOrder $record): string => RequestResource::getUrl('view', ['record' => $record->request_id]))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSupplierOrders::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['po_number', 'supplier.name'];
    }

    /**
     * @return Builder<SupplierOrder>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
