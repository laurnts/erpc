<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreditLimitRequestStatus;
use App\Filament\Resources\CreditLimitAcceptanceReportResource\Pages\ListAcceptanceReports;
use App\Models\BuyerCreditLimitRequestApproval;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CreditLimitAcceptanceReportResource extends Resource
{
    protected static ?string $model = BuyerCreditLimitRequestApproval::class;

    protected static ?string $tenantOwnershipRelationshipName = 'team';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 21;

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?string $navigationLabel = 'Acceptance Report';

    protected static ?string $pluralModelLabel = 'Credit Limit Acceptances';

    protected static ?string $modelLabel = 'Credit Limit Acceptance';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('buyerCreditLimitRequest.buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('buyerCreditLimitRequest.buyer.code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyerCreditLimitRequest.current_limit')
                    ->label('Max Credit Limit')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->sortable(),
                TextColumn::make('buyerCreditLimitRequest.requested_limit')
                    ->label('Requested Limit')
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                TextColumn::make('increase_amount')
                    ->label('Changes')
                    ->getStateUsing(function (BuyerCreditLimitRequestApproval $record): float {
                        $request = $record->buyerCreditLimitRequest;
                        return (float) $request->requested_limit - (float) $request->current_limit;
                    })
                    ->money(fn (): string => Filament::getTenant() instanceof \App\Models\Team ? Filament::getTenant()->getBaseCurrencyCode() : 'USD')
                    ->color('success'),
                TextColumn::make('buyerCreditLimitRequest.status')
                    ->label('Request Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Approved By')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->wrap()
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('buyer_id')
                    ->label('Buyer')
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['value'])) {
                            return $query->whereHas('buyerCreditLimitRequest', function ($q) use ($data): void {
                                $q->where('buyer_id', $data['value']);
                            });
                        }

                        return $query;
                    })
                    ->options(function (): array {
                        /** @var \App\Models\Team|null $team */
                        $team = Filament::getTenant();
                        
                        if ($team === null) {
                            return [];
                        }

                        return \App\Models\Company::query()
                            ->where('team_id', $team->id)
                            ->where('is_buyer', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user_id')
                    ->label('Approved By')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Request Status')
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['value'])) {
                            return $query->whereHas('buyerCreditLimitRequest', function ($q) use ($data): void {
                                $q->whereIn('status', (array) $data['value']);
                            });
                        }

                        return $query;
                    })
                    ->options(CreditLimitRequestStatus::class)
                    ->multiple(),
            ])
            ->defaultSort('approved_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcceptanceReports::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'buyerCreditLimitRequest.buyer.name',
            'buyerCreditLimitRequest.buyer.code',
            'user.name',
        ];
    }

    /**
     * @return Builder<BuyerCreditLimitRequestApproval>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->whereHas('buyerCreditLimitRequest', function ($query) use ($team): void {
                $query->where('team_id', $team?->getKey());
            })
            ->with(['buyerCreditLimitRequest.buyer', 'buyerCreditLimitRequest.team', 'user'])
            ->orderBy('approved_at', 'desc');
    }
}
