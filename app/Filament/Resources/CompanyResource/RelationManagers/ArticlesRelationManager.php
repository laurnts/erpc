<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyResource\RelationManagers;

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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                TextInput::make('last_quoted_price')
                    ->label('Last Quoted Price')
                    ->numeric()
                    ->prefix('$'),
                Select::make('last_quoted_currency_id')
                    ->label('Currency')
                    ->options(fn () => Currency::query()->where('is_active', true)->pluck('code', 'id')->all())
                    ->searchable()
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Article Name')
                    ->searchable()
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
                    ->label('Add Article')
                    ->icon('heroicon-o-plus')
                    ->size(Size::Small)
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...self::getPivotFormSchema(),
                    ]),
                CreateAction::make()
                    ->label('Create Article')
                    ->icon('heroicon-o-cube')
                    ->size(Size::Small)
                    ->form([
                        ...ArticleResource::getFormSchema(forModal: true),
                        ...self::getPivotFormSchema(),
                    ])
                    ->using(function (array $data, RelationManager $livewire): Article {
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

                        /** @var Article $article */
                        $article = Article::create([
                            ...$data,
                            'team_id' => $team->id,
                            'creator_id' => auth()->id(),
                        ]);

                        /** @var \App\Models\Company $company */
                        $company = $livewire->getOwnerRecord();
                        $company->articles()->attach($article->id, $pivotData);

                        return $article;
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
     * Get pivot form fields for attaching/creating articles.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private static function getPivotFormSchema(): array
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
                ->searchable()
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
