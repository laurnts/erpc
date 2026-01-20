<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\KeyAccountResource\Pages\CreateKeyAccount;
use App\Filament\Resources\KeyAccountResource\Pages\ListKeyAccounts;
use App\Filament\Resources\KeyAccountResource\Pages\ViewKeyAccount;
use App\Models\KeyAccount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class KeyAccountResource extends Resource
{
    protected static ?string $model = KeyAccount::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Key Accounts';

    /**
     * Get the base form fields for creating/editing a key account.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('Full name of the key account personnel'),
            TextInput::make('email')
                ->email()
                ->maxLength(255)
                ->helperText('Email address for contact'),
            TextInput::make('phone')
                ->maxLength(50)
                ->helperText('Phone number for contact'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Inactive key accounts will not appear in selection lists'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchema())
            ->columns(1)
            ->inlineLabel();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('buyers_count')
                    ->label('Assigned Buyers')
                    ->counts('buyers')
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
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
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
            'index' => ListKeyAccounts::route('/'),
            'create' => CreateKeyAccount::route('/create'),
            'view' => ViewKeyAccount::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
