<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Models\Article;
use App\Models\Tag;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    /**
     * Get the base form fields for creating/editing an article.
     * Used both in main form and inline create modals.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Article Name')
                ->required()
                ->maxLength(255),
            TextInput::make('sku')
                ->label('SKU (Optional)')
                ->maxLength(255),
            TextInput::make('unit')
                ->label('Unit of Measure')
                ->required()
                ->maxLength(50)
                ->default('pcs')
                ->helperText('e.g., pcs, kg, ltr, set, box'),
            Select::make('tags')
                ->label('Categories')
                ->relationship('tags', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->createOptionForm(TagResource::getFormSchema())
                ->createOptionUsing(function (array $data): int {
                    /** @var Tag $tag */
                    $tag = Tag::create([
                        'name' => $data['name'],
                        'color' => $data['color'],
                        'description' => $data['description'] ?? null,
                        'team_id' => auth()->user()->currentTeam->id,
                        'creator_id' => auth()->id(),
                    ]);

                    return $tag->id;
                }),
            Textarea::make('description')
                ->maxLength(2000)
                ->rows(3),
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
                TextColumn::make('unit')
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    RestoreAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
