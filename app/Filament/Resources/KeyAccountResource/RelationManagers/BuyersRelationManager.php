<?php

declare(strict_types=1);

namespace App\Filament\Resources\KeyAccountResource\RelationManagers;

use App\Filament\Resources\BuyerResource;
use App\Models\Company;
use Filament\Actions\ActionGroup;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class BuyersRelationManager extends RelationManager
{
    protected static string $relationship = 'buyers';

    protected static ?string $modelLabel = 'buyer';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-group';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Buyer Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('country')
                    ->label('Country')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->headerActions([
                AttachAction::make()
                    ->label('Assign Buyer')
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => BuyerResource::getEloquentQuery()
                        ->where('is_active', true)),
                CreateAction::make()
                    ->label('Create Buyer')
                    ->icon('heroicon-o-building-storefront')
                    ->size(Size::Small)
                    ->form(BuyerResource::getFormSchema(excludePeopleField: true))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['is_buyer'] = true;

                        return $data;
                    })
                    ->using(function (array $data, RelationManager $livewire): Company {
                        /** @var \App\Models\Team $team */
                        $team = Filament::getTenant();

                        /** @var Company $buyer */
                        $buyer = Company::create([
                            ...$data,
                            'team_id' => $team->id,
                            'creator_id' => auth()->id(),
                        ]);

                        /** @var \App\Models\KeyAccount $keyAccount */
                        $keyAccount = $livewire->getOwnerRecord();
                        $keyAccount->buyers()->attach($buyer->id);

                        return $buyer;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DetachAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
