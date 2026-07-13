<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Actions\SupplierArticles\SetPreferredSupplier;
use App\Filament\Resources\ArticleResource;
use App\Models\Article;
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
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

final class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $modelLabel = 'article';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-cube';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->maxLength(255)
                    ->helperText('Your SKU/code for this article'),
                TextInput::make('supplier_price')
                    ->label('Supplier Price')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Standing offer price maintained by the supplier'),
                Select::make('supplier_price_currency_id')
                    ->label('Price Currency')
                    ->options(fn () => Currency::query()->where('is_active', true)->pluck('code', 'id')->all())
                    ->preload(),
                TextInput::make('available_quantity')
                    ->label('Available Quantity')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave empty when availability is unknown'),
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
                    ->label('Article Name')

                    ->sortable(),
                TextColumn::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->sortable(),
                TextColumn::make('supplier_price')
                    ->label('Supplier Price')
                    ->money(fn (Article $record): string => Currency::find($record->getAttribute('supplier_price_currency_id'))?->code
                        ?? Filament::getTenant()?->getBaseCurrencyCode()
                        ?? 'USD')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('available_quantity')
                    ->label('Available Qty')
                    ->numeric()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('last_quoted_price')
                    ->label('Last Price')
                    ->money(fn (Article $record): string => Currency::find($record->getAttribute('last_quoted_currency_id'))?->code
                        ?? Filament::getTenant()?->getBaseCurrencyCode()
                        ?? 'USD')
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
                    ->label('Add Article')
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...$this->getPivotFormSchema(),
                    ])
                    ->databaseTransaction()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['is_preferred'] ?? false) === true && isset($data['recordId'])) {
                            app(SetPreferredSupplier::class)->demoteOthers(
                                (int) $data['recordId'],
                                (int) $this->getOwnerRecord()->getKey(),
                            );
                        }

                        return $data;
                    }),
                CreateAction::make()
                    ->label('Create Article')
                    ->icon('heroicon-o-cube')
                    ->size(Size::Small)
                    ->form([
                        ...ArticleResource::getFormSchema(forModal: true, excludeSuppliersField: true),
                        ...$this->getPivotFormSchema(),
                    ])
                    ->using(function (array $data, RelationManager $livewire): Article {
                        /** @var \App\Models\Team $team */
                        $team = Filament::getTenant();

                        $pivotData = [
                            'supplier_sku' => $data['supplier_sku'] ?? null,
                            'supplier_price' => $data['supplier_price'] ?? null,
                            'supplier_price_currency_id' => $data['supplier_price_currency_id'] ?? null,
                            'available_quantity' => $data['available_quantity'] ?? null,
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
                            $data['supplier_price'],
                            $data['supplier_price_currency_id'],
                            $data['available_quantity'],
                            $data['last_quoted_price'],
                            $data['last_quoted_currency_id'],
                            $data['last_quoted_at'],
                            $data['lead_time_days'],
                            $data['notes'],
                            $data['is_preferred'],
                            $data['is_active'],
                        );

                        /** @var Article $article */
                        $article = Article::create([
                            ...$data,
                            'team_id' => $team->id,
                            'creator_id' => auth()->id(),
                        ]);

                        /** @var \App\Models\Company $supplier */
                        $supplier = $livewire->getOwnerRecord();
                        $supplier->articles()->attach($article->id, $pivotData);

                        return $article;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->databaseTransaction()
                        ->mutateFormDataUsing(function (array $data, Article $record): array {
                            if (($data['is_preferred'] ?? false) === true) {
                                app(SetPreferredSupplier::class)->demoteOthers(
                                    (int) $record->getKey(),
                                    (int) $this->getOwnerRecord()->getKey(),
                                );
                            }

                            return $data;
                        }),
                    DetachAction::make()
                        ->before(function (DetachAction $action, Article $record): void {
                            if ($record->suppliers()->count() <= 1) {
                                Notification::make()
                                    ->title('Cannot remove the only supplier')
                                    ->body("\"{$record->name}\" would be left without a supplier. Every article must have at least one supplier.")
                                    ->warning()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->before(function (DetachBulkAction $action, Collection $records): void {
                            $orphaned = $records->filter(fn (Article $article): bool => $article->suppliers()->count() <= 1);

                            if ($orphaned->isNotEmpty()) {
                                Notification::make()
                                    ->title('Cannot detach these articles')
                                    ->body('Every article must have at least one supplier. Affected: '.$orphaned->pluck('name')->implode(', '))
                                    ->warning()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Get pivot form fields for attaching/creating articles.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getPivotFormSchema(): array
    {
        return [
            TextInput::make('supplier_sku')
                ->label('Supplier SKU')
                ->maxLength(255),
            TextInput::make('supplier_price')
                ->label('Supplier Price')
                ->numeric()
                ->minValue(0),
            Select::make('supplier_price_currency_id')
                ->label('Price Currency')
                ->options(fn () => Currency::query()->where('is_active', true)->pluck('code', 'id')->all())
                ->preload(),
            TextInput::make('available_quantity')
                ->label('Available Quantity')
                ->numeric()
                ->minValue(0),
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
