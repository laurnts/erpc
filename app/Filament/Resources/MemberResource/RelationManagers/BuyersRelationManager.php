<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberResource\RelationManagers;

use App\Filament\Resources\CompanyResource;
use App\Models\Company;
use Filament\Actions\ActionGroup;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class BuyersRelationManager extends RelationManager
{
    protected static string $relationship = 'buyers';

    protected static ?string $modelLabel = 'buyer';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-building-office';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Buyer Name')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->headerActions([
                Action::make('attach')
                    ->label('Add Buyer')
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->form([
                        Select::make('buyer_id')
                            ->label('Buyer')
                            ->options(fn (): array => Company::query()
                                ->where('is_buyer', true)
                                ->where('team_id', Filament::getTenant()->id)
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        /** @var \App\Models\Membership $membership */
                        $membership = $livewire->getOwnerRecord();
                        
                        // Manually insert into pivot table since relationship uses user_id instead of id
                        \Illuminate\Support\Facades\DB::table('key_account_buyers')->insertOrIgnore([
                            'key_account_id' => $membership->user_id,
                            'buyer_id' => $data['buyer_id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        $livewire->dispatch('refresh');
                    }),
                CreateAction::make()
                    ->label('Create Buyer')
                    ->icon('heroicon-o-building-office')
                    ->size(Size::Small)
                    ->form([
                        ...CompanyResource::getFormSchema(excludePeopleField: true),
                    ])
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

                        /** @var \App\Models\Membership $membership */
                        $membership = $livewire->getOwnerRecord();
                        
                        // Manually insert into pivot table since relationship uses user_id instead of id
                        \Illuminate\Support\Facades\DB::table('key_account_buyers')->insertOrIgnore([
                            'key_account_id' => $membership->user_id,
                            'buyer_id' => $buyer->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        return $buyer;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('detach')
                        ->label('Detach')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($record, RelationManager $livewire): void {
                            /** @var \App\Models\Membership $membership */
                            $membership = $livewire->getOwnerRecord();
                            
                            // Manually delete from pivot table
                            \Illuminate\Support\Facades\DB::table('key_account_buyers')
                                ->where('key_account_id', $membership->user_id)
                                ->where('buyer_id', $record->id)
                                ->delete();
                            
                            $livewire->dispatch('refresh');
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('detach')
                        ->label('Detach Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, RelationManager $livewire): void {
                            /** @var \App\Models\Membership $membership */
                            $membership = $livewire->getOwnerRecord();
                            $buyerIds = $records->pluck('id')->toArray();
                            
                            // Manually delete from pivot table
                            \Illuminate\Support\Facades\DB::table('key_account_buyers')
                                ->where('key_account_id', $membership->user_id)
                                ->whereIn('buyer_id', $buyerIds)
                                ->delete();
                            
                            $livewire->dispatch('refresh');
                        }),
                ]),
            ]);
    }
}
