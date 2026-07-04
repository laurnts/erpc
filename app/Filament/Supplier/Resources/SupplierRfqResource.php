<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources;

use App\Filament\Supplier\Resources\SupplierRfqResource\Pages\ListSupplierRfqs;
use App\Filament\Supplier\Resources\SupplierRfqResource\Pages\ViewSupplierRfq;
use App\Models\SupplierQuote;
use App\Services\Portal\SupplierPortalContext;
use App\Services\SupplierPortal\SupplierRfqStatusPresenter;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier-facing RFQ resource. Confidentiality is enforced in three layers:
 * the query scope (own company + sent gate), the panel-branched policy, and
 * the column projection below — internal notes, notification metadata, the
 * request/buyer linkage, and anything about other suppliers are never
 * selected, let alone rendered.
 */
final class SupplierRfqResource extends Resource
{
    protected static ?string $model = SupplierQuote::class;

    protected static ?string $modelLabel = 'Quote Request';

    protected static ?string $pluralModelLabel = 'Quote Requests';

    protected static ?string $navigationLabel = 'Quote Requests';

    protected static ?string $slug = 'rfqs';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 2;

    /**
     * Whitelist projection: only supplier-safe columns leave the database.
     *
     * @var list<string>
     */
    private const array PORTAL_COLUMNS = [
        'id',
        'team_id',
        'supplier_id',
        'quote_number',
        'status',
        'currency_id',
        'subtotal',
        'tax_total',
        'total',
        'quoted_at',
        'valid_until',
        'notes',
        'submitted_via',
        'submitted_at',
        'submitted_by_user_id',
        'declined_at',
        'sent_to_supplier_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public static function table(Table $table): Table
    {
        $presenter = app(SupplierRfqStatusPresenter::class);

        return $table
            ->columns([
                TextColumn::make('quote_number')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('sent_to_supplier_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Your Total')
                    ->formatStateUsing(fn (SupplierQuote $record): string => $record->submitted_at !== null
                        ? $record->formatted_total
                        : '—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (SupplierQuote $record): string => $presenter->label($record))
                    ->color(fn (SupplierQuote $record): string => $presenter->color($record)),
            ])
            ->defaultSort('sent_to_supplier_at', 'desc')
            ->recordUrl(fn (SupplierQuote $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierRfqs::route('/'),
            'view' => ViewSupplierRfq::route('/{record}'),
        ];
    }

    /**
     * @return Builder<SupplierQuote>
     */
    public static function getEloquentQuery(): Builder
    {
        $companyId = app(SupplierPortalContext::class)->companyId();

        /** @var Builder<SupplierQuote> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->forSupplierPortal($companyId)
            ->select(array_map(
                fn (string $column): string => 'supplier_quotes.'.$column,
                self::PORTAL_COLUMNS,
            ))
            ->with(['currency', 'items.requestItem']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
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
