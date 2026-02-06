<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use App\Filament\Resources\CompanyResource;
use App\Models\Company;
use App\Models\Currency;
use Filament\Actions\ActionGroup;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SuppliersRelationManager extends RelationManager
{
    protected static string $relationship = 'suppliers';

    protected static ?string $modelLabel = 'supplier';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-building-storefront';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->maxLength(255)
                    ->helperText('The SKU/code this supplier uses for this article'),
                TextInput::make('last_quoted_price')
                    ->label('Last Quoted Price')
                    ->numeric()
                    ->prefix('$'),
                Select::make('last_quoted_currency_id')
                    ->label('Currency')
                    ->options(fn () => Currency::query()->where('is_active', true)->pluck('code', 'id')->all())
                    
                    ->preload(),
                DateTimePicker::make('last_quoted_at')
                    ->label('Last Quoted At'),
                TextInput::make('lead_time_days')
                    ->label('Lead Time')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('days'),
                Toggle::make('is_preferred')
                    ->label('Preferred Supplier')
                    ->helperText('Mark as the preferred supplier for this article'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Supplier Name')
                    
                    ->sortable(),
                TextColumn::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->sortable(),
                TextColumn::make('last_quoted_price')
                    ->label('Last Price')
                    ->money(fn ($record): string => Currency::find($record->last_quoted_currency_id)->code ?? 'USD')
                    ->sortable(),
                TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days')
                    ->sortable(),
                IconColumn::make('is_preferred')
                    ->label('Preferred')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('is_preferred', 'desc')
            ->headerActions([
                AttachAction::make()
                    ->label('Add Supplier')
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->where('is_supplier', true))
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...$this->getPivotFormSchema(),
                    ]),
                CreateAction::make()
                    ->label('Create Supplier')
                    ->icon('heroicon-o-building-storefront')
                    ->size(Size::Small)
                    ->form([
                        ...CompanyResource::getFormSchema(excludePeopleField: true),
                        ...$this->getPivotFormSchema(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['is_supplier'] = true;

                        return $data;
                    })
                    ->using(function (array $data, RelationManager $livewire): Company {
                        /** @var \App\Models\Team $team */
                        $team = Filament::getTenant();

                        $pivotData = [
                            'supplier_sku' => $data['supplier_sku'] ?? null,
                            'last_quoted_price' => $data['last_quoted_price'] ?? null,
                            'last_quoted_currency_id' => $data['last_quoted_currency_id'] ?? null,
                            'last_quoted_at' => $data['last_quoted_at'] ?? null,
                            'lead_time_days' => $data['lead_time_days'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'is_preferred' => $data['is_preferred'] ?? false,
                            'is_active' => $data['is_active'] ?? true,
                        ];

                        unset(
                            $data['supplier_sku'],
                            $data['last_quoted_price'],
                            $data['last_quoted_currency_id'],
                            $data['last_quoted_at'],
                            $data['lead_time_days'],
                            $data['notes'],
                            $data['is_preferred'],
                            $data['is_active'],
                        );

                        /** @var Company $supplier */
                        $supplier = Company::create([
                            ...$data,
                            'team_id' => $team->id,
                            'creator_id' => auth()->id(),
                        ]);

                        /** @var \App\Models\Article $article */
                        $article = $livewire->getOwnerRecord();
                        $article->suppliers()->attach($supplier->id, $pivotData);

                        return $supplier;
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

    /**
     * Get pivot form fields for attaching/creating suppliers.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getPivotFormSchema(): array
    {
        return [
            TextInput::make('supplier_sku')
                ->label('Supplier SKU')
                ->maxLength(255),
            TextInput::make('last_quoted_price')
                ->label('Last Quoted Price')
                ->numeric(),
            Select::make('last_quoted_currency_id')
                ->label('Currency')
                ->options(fn () => Currency::query()->where('is_active', true)->pluck('code', 'id')->all())
                
                ->preload(),
            DateTimePicker::make('last_quoted_at')
                ->label('Last Quoted At'),
            TextInput::make('lead_time_days')
                ->label('Lead Time (days)')
                ->numeric()
                ->minValue(0),
            Toggle::make('is_preferred')
                ->label('Preferred Supplier')
                ->default(false),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            Textarea::make('notes')
                ->label('Notes')
                ->rows(2),
        ];
    }
}
