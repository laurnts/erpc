<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BuyerCreditLimitOverviewResource\Pages\ListBuyerCreditLimits;
use App\Filament\Resources\BuyerCreditLimitOverviewResource\Pages\ViewBuyerCreditLimit;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class BuyerCreditLimitOverviewResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 21;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Credit Limits';

    protected static ?string $pluralModelLabel = 'Credit Limits';

    protected static ?string $modelLabel = 'Credit Limit';

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Company $record): string => BuyerCreditLimitOverviewResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('credit_limit')
                    ->label('Max Credit Limit')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->sortable(),
                TextColumn::make('available_credit')
                    ->label('Available Credit')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->sortable(),
                TextColumn::make('credit_used')
                    ->label('Credit Used')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->sortable(),
                TextColumn::make('requested_credit_limit')
                    ->label('Requested Limit')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->placeholder('—')
                    ->color('warning')
                    ->visible(fn (?Company $record): bool => $record instanceof \App\Models\Company && $record->requested_credit_limit !== null),
                IconColumn::make('credit_status')
                    ->label('Credit Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('credit_status')
                    ->label('Credit Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('has_pending_request')
                    ->label('Has Pending Request')
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === '1') {
                            return $query->whereHas('creditLimitRequests', function ($q): void {
                                $q->where('status', \App\Enums\CreditLimitRequestStatus::PENDING);
                            });
                        }

                        return $query;
                    })
                    ->options([
                        '1' => 'Has Pending Request',
                    ]),
            ])
            ->defaultSort('available_credit', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuyerCreditLimits::route('/'),
            'view' => ViewBuyerCreditLimit::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }

    /**
     * @return Builder<Company>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('team_id', $team?->getKey())
            ->where('is_buyer', true)
            ->with(['creditLimitRequests', 'creditUsageHistory']);
    }
}
