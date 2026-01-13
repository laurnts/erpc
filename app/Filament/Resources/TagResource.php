<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Models\Tag;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    /**
     * Get the base form fields for creating/editing a tag/category.
     * Used both in main form and inline create modals.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Category Name')
                ->required()
                ->maxLength(255),
            ColorPicker::make('color')
                ->required(),
            Textarea::make('description')
                ->maxLength(500)
                ->rows(2),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::getFormSchema(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Categories are shared between Articles and Companies for classification.')
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('articles_count')
                    ->label('Articles')
                    ->counts('articles')
                    ->sortable(),
                TextColumn::make('companies_count')
                    ->label('Companies')
                    ->counts('companies')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
