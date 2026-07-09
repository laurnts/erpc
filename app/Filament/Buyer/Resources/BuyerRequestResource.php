<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Resources;

use App\Enums\RequestStage;
use App\Filament\Buyer\Resources\BuyerRequestResource\Pages\CreateBuyerRequest;
use App\Filament\Buyer\Resources\BuyerRequestResource\Pages\EditBuyerRequest;
use App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ListBuyerRequests;
use App\Filament\Buyer\Resources\BuyerRequestResource\Pages\ViewBuyerRequest;
use App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers\InvoicesRelationManager;
use App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Buyer\Resources\BuyerRequestResource\Schemas\BuyerRequestForm;
use App\Models\Request;
use App\Services\BuyerPortal\BuyerRequestStagePresenter;
use App\Services\Portal\BuyerPortalContext;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class BuyerRequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static ?string $modelLabel = 'Request';

    protected static ?string $pluralModelLabel = 'Requests';

    protected static ?string $slug = 'requests';

    protected static ?string $navigationLabel = 'My Requests';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(BuyerRequestForm::components());
    }

    public static function table(Table $table): Table
    {
        $presenter = app(BuyerRequestStagePresenter::class);

        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('stage')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Request $record): string => $presenter->label($record))
                    ->color(fn (Request $record): string => $presenter->color($presenter->effectiveStage($record))),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('required_by')
                    ->label('Required By')
                    ->date()
                    ->sortable()
                    ->color(fn (Request $record): string => $record->required_by !== null && $record->required_by->isPast() ? 'danger' : 'gray'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('status_group')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'awaiting_confirmation' => 'Awaiting Confirmation',
                        'in_fulfillment' => 'In Fulfillment',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return match ($value) {
                            'active' => $query->whereNotIn('stage', [
                                RequestStage::COMPLETED,
                                RequestStage::CANCELLED,
                            ]),
                            'awaiting_confirmation' => $query->where(
                                'stage',
                                RequestStage::AWAITING_BUYER_CONFIRMATION,
                            ),
                            'in_fulfillment' => $query->whereIn('stage', [
                                RequestStage::PREPARING_SUPPLIER_ORDER,
                                RequestStage::GOODS_RECEIVE,
                                RequestStage::AWAITING_SHIPMENT,
                                RequestStage::SHIPPED,
                                RequestStage::DELIVERED,
                            ]),
                            'completed' => $query->whereIn('stage', [
                                RequestStage::COMPLETED,
                                RequestStage::PAID,
                                RequestStage::INVOICED,
                            ]),
                            'cancelled' => $query->where('stage', RequestStage::CANCELLED),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Request $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            BuyerQuotesRelationManager::class,
            ShipmentsRelationManager::class,
            InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuyerRequests::route('/'),
            'create' => CreateBuyerRequest::route('/create'),
            'view' => ViewBuyerRequest::route('/{record}'),
            'edit' => EditBuyerRequest::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Request>
     */
    public static function getEloquentQuery(): Builder
    {
        $companyId = app(BuyerPortalContext::class)->companyId();

        /** @var Builder<Request> $query */
        $query = parent::getEloquentQuery();

        return $query->forBuyer($companyId);
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
