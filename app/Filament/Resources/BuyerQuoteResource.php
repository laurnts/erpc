<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BuyerQuoteStatus;
use App\Filament\Exports\BuyerQuoteExporter;
use App\Filament\Resources\BuyerQuoteResource\Pages\ListBuyerQuotes;
use App\Models\BuyerQuote;
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

final class BuyerQuoteResource extends Resource
{
    protected static ?string $model = BuyerQuote::class;

    protected static ?string $recordTitleAttribute = 'quote_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Buyer Quotes';

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
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (BuyerQuote $record): string => 'v'.$record->version),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    ->searchable()
                    ->sortable()
                    ->url(fn (BuyerQuote $record): string => RequestResource::getUrl('view', ['record' => $record->request_id])),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('currency.code')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (BuyerQuote $record): string => $record->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
                    ->sortable(),
                TextColumn::make('total_margin_amount')
                    ->label('Margin')
                    ->getStateUsing(fn (BuyerQuote $record): string => sprintf(
                        '%s (%.1f%%)',
                        $record->currency?->formatNumber((float) $record->total_margin_amount) ?? number_format((float) $record->total_margin_amount, 2),
                        $record->total_margin_percent
                    ))
                    ->toggleable(),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->sortable()
                    ->color(fn (BuyerQuote $record): string => $record->is_expired ? 'danger' : 'success'),
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
                    ->options(BuyerQuoteStatus::class)
                    ->multiple(),
                SelectFilter::make('buyer_id')
                    ->relationship('buyer', 'name', fn (Builder $query) => $query->where('is_buyer', true))
                    ->label('Buyer')
                    ->preload(),
                SelectFilter::make('request_id')
                    ->relationship('request', 'request_number')
                    ->label('Request')
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (BuyerQuote $record): string => RequestResource::getUrl('view', ['record' => $record->request_id]))
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->exporter(BuyerQuoteExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBuyerQuotes::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['quote_number', 'buyer.name'];
    }

    /**
     * @return Builder<BuyerQuote>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
