<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources;

use App\Enums\RequestStage;
use App\Filament\Customer\Resources\CustomerRequestResource\Pages\CreateCustomerRequest;
use App\Filament\Customer\Resources\CustomerRequestResource\Pages\EditCustomerRequest;
use App\Filament\Customer\Resources\CustomerRequestResource\Pages\ListCustomerRequests;
use App\Filament\Customer\Resources\CustomerRequestResource\Pages\ViewCustomerRequest;
use App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers\InvoicesRelationManager;
use App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Customer\Resources\CustomerRequestResource\Schemas\CustomerRequestForm;
use App\Models\Request;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use App\Services\Portal\CustomerPortalContext;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CustomerRequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static ?string $modelLabel = 'Request';

    protected static ?string $pluralModelLabel = 'Requests';

    protected static ?string $slug = 'requests';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(CustomerRequestForm::components());
    }

    public static function table(Table $table): Table
    {
        $presenter = app(CustomerRequestStagePresenter::class);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withExists(Request::itemPresenceExistsConstraints()))
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request No.')
                    ->searchable()
                    ->sortable()
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
                    ->color(fn (Request $record): string => $presenter->color($record->stage)),
                TextColumn::make('item_type_summary')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Goods' => 'primary',
                        'Services' => 'success',
                        'Mixed' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('required_by')
                    ->label('Required by')
                    ->date()
                    ->sortable(),
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
            ->defaultSort('submitted_at', 'desc')
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
            'index' => ListCustomerRequests::route('/'),
            'create' => CreateCustomerRequest::route('/create'),
            'view' => ViewCustomerRequest::route('/{record}'),
            'edit' => EditCustomerRequest::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Request>
     */
    public static function getEloquentQuery(): Builder
    {
        $companyId = app(CustomerPortalContext::class)->companyId();

        /** @var Builder<Request> $query */
        $query = parent::getEloquentQuery();

        return $query->forBuyer($companyId);
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
