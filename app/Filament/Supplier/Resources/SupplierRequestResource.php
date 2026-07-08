<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources;

use App\Enums\SupplierQuoteStatus;
use App\Filament\Supplier\Resources\SupplierRequestResource\Pages\ListSupplierRequests;
use App\Filament\Supplier\Resources\SupplierRequestResource\Pages\ViewSupplierRequest;
use App\Models\SupplierQuote;
use App\Services\Portal\SupplierPortalContext;
use App\Services\SupplierPortal\SupplierRequestStatusPresenter;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier-facing request resource. Confidentiality is enforced in three layers:
 * the query scope (own company + sent gate), the panel-branched policy, and
 * the column projection below — internal notes, notification metadata, the
 * request/buyer linkage, and anything about other suppliers are never
 * selected, let alone rendered.
 */
final class SupplierRequestResource extends Resource
{
    protected static ?string $model = SupplierQuote::class;

    protected static ?string $modelLabel = 'Request';

    protected static ?string $pluralModelLabel = 'Requests';

    protected static ?string $navigationLabel = 'Requests';

    protected static ?string $slug = 'requests';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 1;

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
        'outcomes_announced_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public static function table(Table $table): Table
    {
        $presenter = app(SupplierRequestStatusPresenter::class);

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
            ->filters([
                SelectFilter::make('status_group')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'submitted' => 'Submitted',
                        'won' => 'Won',
                        'lost' => 'Lost',
                        'declined' => 'Declined',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return match ($value) {
                            'open' => self::openStatusQuery($query),
                            'submitted' => self::submittedStatusQuery($query),
                            'won' => self::wonStatusQuery($query),
                            'lost' => self::lostStatusQuery($query),
                            'declined' => self::declinedStatusQuery($query),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('sent_to_supplier_at', 'desc')
            ->recordUrl(fn (SupplierQuote $record): string => self::getUrl('view', ['record' => $record]));
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function openStatusQuery(Builder $query): Builder
    {
        return $query
            ->where('status', SupplierQuoteStatus::PENDING)
            ->whereNull('declined_at')
            ->where(fn (Builder $inner): Builder => $inner
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>', today()));
    }

    /**
     * Won/Lost populate only from announced outcomes: pre-announcement
     * evaluation churn keeps every submitted quote in "Submitted" regardless
     * of internal SELECTED/RECEIVED/REJECTED state.
     *
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function submittedStatusQuery(Builder $query): Builder
    {
        return $query
            ->whereNull('outcomes_announced_at')
            ->where(fn (Builder $inner): Builder => $inner
                ->whereNotNull('submitted_at')
                ->orWhereIn('status', [
                    SupplierQuoteStatus::RECEIVED,
                    SupplierQuoteStatus::SELECTED,
                    SupplierQuoteStatus::REJECTED,
                ]));
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function wonStatusQuery(Builder $query): Builder
    {
        return $query
            ->whereNotNull('outcomes_announced_at')
            ->whereIn('status', [
                SupplierQuoteStatus::SELECTED,
                SupplierQuoteStatus::RECEIVED,
            ]);
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function lostStatusQuery(Builder $query): Builder
    {
        return $query
            ->whereNotNull('outcomes_announced_at')
            ->where('status', SupplierQuoteStatus::REJECTED);
    }

    /**
     * @param  Builder<SupplierQuote>  $query
     * @return Builder<SupplierQuote>
     */
    private static function declinedStatusQuery(Builder $query): Builder
    {
        return $query->whereNotNull('declined_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierRequests::route('/'),
            'view' => ViewSupplierRequest::route('/{record}'),
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
