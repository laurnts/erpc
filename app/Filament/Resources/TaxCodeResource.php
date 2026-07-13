<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TaxCodeResource\Pages\CreateTaxCode;
use App\Filament\Resources\TaxCodeResource\Pages\ListTaxCodes;
use App\Models\TaxCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

final class TaxCodeResource extends Resource
{
    protected static ?string $model = TaxCode::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 13;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    /**
     * Get the base form fields for creating/editing a tax code.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(): array
    {
        return [
            TextInput::make('code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, ?TaxCode $record) => $rule->where('team_id', $record?->team_id ?? Filament::getTenant()?->id))
                ->helperText('A unique identifier for this tax code (e.g., PPN11, VAT20)'),
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('Display name for this tax code'),
            TextInput::make('rate')
                ->required()
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(100)
                ->step(0.01)
                ->suffix('%')
                ->helperText('Tax rate as a percentage (e.g., 11 for 11%)'),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('Order in which tax codes appear in lists'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Inactive tax codes will not appear in selection lists'),
            Toggle::make('is_default')
                ->label('Default Tax Code')
                ->default(false)
                ->helperText('Only one tax code can be the default per team'),
            Toggle::make('is_inclusive_default')
                ->label('Tax Inclusive by Default')
                ->default(false)
                ->helperText('When selected, prices using this tax code are assumed to include tax'),
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
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rate')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2).'%')
                    ->alignEnd(),
                IconColumn::make('is_inclusive_default')
                    ->label('Tax Inclusive')
                    ->boolean()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
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
            ->defaultSort('sort_order', 'asc')
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('is_default')
                    ->label('Default')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
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
            'index' => ListTaxCodes::route('/'),
            'create' => CreateTaxCode::route('/create'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name'];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
