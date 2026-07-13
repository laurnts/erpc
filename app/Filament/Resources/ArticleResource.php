<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Catalog\SuggestArticleListPrice;
use App\Filament\Exports\ArticleExporter;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\ArticleResource\Pages\ViewArticle;
use App\Models\Article;
use App\Models\Company;
use App\Models\Tag;
use App\Models\TaxCode;
use App\Models\Team;
use App\Models\UnitOfMeasure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    /**
     * Get the base form fields for creating/editing an article.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $forModal  When true, uses options() instead of relationship() to avoid model context issues
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $forModal = false): array
    {
        $taxCodeSelect = Select::make('default_tax_code_id')
            ->label('Default Tax Code')
            ->default(fn (): ?int => TaxCode::query()
                ->where('team_id', Filament::getTenant()?->getKey())
                ->where('is_default', true)
                ->where('is_active', true)
                ->value('id'))

            ->preload()
            ->helperText('Tax code to apply when using this article');

        if ($forModal) {
            $taxCodeSelect->options(fn (): array => TaxCode::query()
                ->where('team_id', Filament::getTenant()?->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (TaxCode $taxCode): array => [
                    $taxCode->getKey() => $taxCode->display_name,
                ])
                ->toArray());
        } else {
            $taxCodeSelect->relationship('defaultTaxCode', 'name')
                ->getOptionLabelFromRecordUsing(fn (?TaxCode $record): string => $record?->display_name ?? '');
        }

        $tagsSelect = Select::make('tags')
            ->label('Categories')
            ->multiple()
            ->preload()

            ->createOptionForm(TagResource::getFormSchema())
            ->createOptionUsing(function (array $data): int {
                /** @var \App\Models\Team $team */
                $team = Filament::getTenant();

                /** @var Tag $tag */
                $tag = Tag::create([
                    'name' => $data['name'],
                    'color' => $data['color'],
                    'description' => $data['description'] ?? null,
                    'team_id' => $team->id,
                    'creator_id' => auth()->id(),
                ]);

                return $tag->id;
            });

        if ($forModal) {
            $tagsSelect->options(fn (): array => Tag::query()
                ->where('team_id', Filament::getTenant()?->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Tag $tag): array => [
                    $tag->getKey() => $tag->name,
                ])
                ->toArray());
        } else {
            $tagsSelect->relationship('tags', 'name');
        }

        $suppliersSelect = Select::make('suppliers')
            ->label('Suppliers')
            ->multiple()
            ->preload()

            ->createOptionForm(SupplierResource::getFormSchema(excludePeopleField: true, forModal: true))
            ->createOptionUsing(function (array $data): int {
                /** @var \App\Models\Team $team */
                $team = Filament::getTenant();

                /** @var Company $supplier */
                $supplier = Company::create([
                    ...$data,
                    'is_supplier' => true,
                    'team_id' => $team->id,
                    'creator_id' => auth()->id(),
                ]);

                return $supplier->id;
            });

        if ($forModal) {
            $suppliersSelect->options(fn (): array => Company::query()
                ->where('team_id', Filament::getTenant()?->getKey())
                ->where('is_supplier', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Company $supplier): array => [
                    $supplier->getKey() => $supplier->name,
                ])
                ->toArray());
        } else {
            $suppliersSelect->relationship(
                'suppliers',
                'name',
                modifyQueryUsing: fn ($query) => $query->where('is_supplier', true)
            );
        }

        $fullFormOnlySections = $forModal ? [] : [
            Section::make('Images')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('product_images')
                        ->label('')
                        ->collection('product_images')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->helperText('The first image is used as the primary product image.'),
                ])
                ->collapsible(),
            Section::make('Public Catalog')
                ->schema([
                    Toggle::make('show_in_product_grid')
                        ->label('Show in Catalog')
                        ->default(false)
                        ->helperText('Publish this article on the public catalog (active articles only).'),
                    Toggle::make('show_price')
                        ->label('Show Price')
                        ->default(true)
                        ->helperText('When off, the catalog shows "Price on request" even if a list price is set.'),
                    TextInput::make('list_price')
                        ->label('List Price')
                        ->numeric()
                        ->minValue(0)
                        ->suffix(function (): ?string {
                            $team = Filament::getTenant();

                            return $team instanceof Team ? $team->getBaseCurrencyCode() : null;
                        })
                        ->helperText('Published in the team default currency. Empty shows "Price on request". Saving publishes the price and clears the review flag.')
                        ->suffixAction(
                            Action::make('suggestListPrice')
                                ->label('Suggest price')
                                ->icon('heroicon-m-calculator')
                                ->visible(fn (?Article $record): bool => $record instanceof \App\Models\Article)
                                ->action(function (Set $set, ?Article $record): void {
                                    $team = Filament::getTenant();

                                    if (! $record instanceof \App\Models\Article || ! $team instanceof Team) {
                                        return;
                                    }

                                    $result = app(SuggestArticleListPrice::class)->execute($record, $team);

                                    if ($result['price'] === null) {
                                        Notification::make()
                                            ->title('No price suggestion available')
                                            ->body(implode(' ', $result['notices']))
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $set('list_price', $result['price']);

                                    $notification = Notification::make()
                                        ->title('Suggested price filled — review and save to publish')
                                        ->success();

                                    if ($result['notices'] !== []) {
                                        $notification->body(implode(' ', $result['notices']));
                                    }

                                    $notification->send();
                                })
                        ),
                ])
                ->collapsible(),
        ];

        return [
            TextInput::make('name')
                ->label('Article Name')
                ->required()
                ->maxLength(255),
            TextInput::make('sku')
                ->label('SKU (Optional)')
                ->maxLength(255),
            Select::make('unit_of_measure_id')
                ->label('Unit of Measure')
                ->relationship('unitOfMeasure', 'label')

                ->preload()
                ->required()
                ->default(fn (): ?int => UnitOfMeasure::query()
                    ->where('team_id', Filament::getTenant()?->id)
                    ->where('code', 'pcs')
                    ->where('is_active', true)
                    ->value('id'))
                ->helperText('Select the unit of measure for this article'),
            $taxCodeSelect,
            $tagsSelect,
            $suppliersSelect,
            Textarea::make('description')
                ->maxLength(2000)
                ->rows(3),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            ...$fullFormOnlySections,
            Section::make('Custom Attributes')
                ->schema([
                    KeyValue::make('attributes')
                        ->label('')
                        ->keyLabel('Attribute Name')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Attribute'),
                ])
                ->collapsible()
                ->collapsed(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchema())
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('unitOfMeasure.label')
                    ->label('Unit')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('defaultTaxCode.name')
                    ->label('Tax Code')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                ToggleColumn::make('show_in_product_grid')
                    ->label('In Grid')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('list_price')
                    ->label('List Price')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                IconColumn::make('price_review_needed')
                    ->label('Price Review')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('gray')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('default_tax_code_id')
                    ->label('Tax Code')
                    ->relationship('defaultTaxCode', 'name'),
                TernaryFilter::make('show_in_product_grid')
                    ->label('In product grid'),
                TernaryFilter::make('price_review_needed')
                    ->label('Price review needed'),
                TrashedFilter::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->exporter(ArticleExporter::class),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'view' => ViewArticle::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'sku'];
    }

    /**
     * @return Builder<Article>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
