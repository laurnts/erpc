<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreationSource;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use App\Models\Company;
use App\Models\Currency;
use App\Models\People;
use App\Models\Tag;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Relaticle\CustomFields\Facades\CustomFields;

final class SupplierResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $modelLabel = 'Supplier';

    protected static ?string $pluralModelLabel = 'Suppliers';

    protected static ?string $slug = 'suppliers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    /**
     * Get the base form fields for creating/editing a supplier.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $excludePeopleField  Exclude People field to prevent circular references
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $excludePeopleField = false): array
    {
        $fields = [
            Hidden::make('is_supplier')->default(true),

            TextInput::make('name')
                ->label('Company Name')
                ->required()
                ->maxLength(255),

            TextInput::make('domain')
                ->label('Domain')
                ->placeholder('example.com')
                ->maxLength(255)
                ->helperText('Company website domain'),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255)
                ->placeholder('supplier@example.com'),

            Select::make('tags')
                ->label('Categories')
                ->relationship('tags', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->helperText('What products/services they supply')
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
                }),
        ];

        // Add People field unless excluded (to prevent circular references)
        if (! $excludePeopleField) {
            $fields[] = Select::make('people')
                ->label('People / Contacts')
                ->relationship('people', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->helperText('Add people associated with this supplier')
                ->createOptionForm(PeopleResource::getFormSchema(excludeCompaniesField: true))
                ->createOptionUsing(function (array $data): int {
                    /** @var \App\Models\Team $team */
                    $team = Filament::getTenant();

                    /** @var People $person */
                    $person = People::create([
                        ...$data,
                        'team_id' => $team->id,
                        'creator_id' => auth()->id(),
                    ]);

                    return $person->id;
                });
        }

        return array_merge($fields, [
            Section::make('Location')
                ->schema([
                    Select::make('country')
                        ->options(self::getCountryOptions())
                        ->default('Indonesia')
                        ->searchable(),
                    Textarea::make('address')
                        ->label('Address')
                        ->rows(2),
                ])
                ->columns(1),
            Section::make('Financial Settings')
                ->schema([
                    Select::make('default_currency_id')
                        ->label('Default Currency')
                        ->relationship(
                            'defaultCurrency',
                            'name',
                            modifyQueryUsing: fn ($query) => $query->where('is_active', true)
                        )
                        ->getOptionLabelFromRecordUsing(fn (?Currency $record): string => $record instanceof \App\Models\Currency ? "{$record->code} - {$record->name}" : '')
                        ->default(function (): ?int {
                            /** @var \App\Models\Team|null $team */
                            $team = Filament::getTenant();
                            $defaultCode = $team?->getErpSettings()->default_currency ?? 'USD';

                            return Currency::query()->where('code', $defaultCode)->where('is_active', true)->value('id');
                        })
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->createOptionForm(CurrencyResource::getFormSchema(excludeDefaultField: true))
                        ->createOptionUsing(function (array $data): int {
                            /** @var Currency $currency */
                            $currency = Currency::create($data);

                            return $currency->id;
                        }),
                    TextInput::make('payment_terms_days')
                        ->label('Default Payment Terms')
                        ->numeric()
                        ->default(30)
                        ->minValue(0)
                        ->suffix('days'),
                    TextInput::make('lead_time_days')
                        ->label('Default Lead Time')
                        ->numeric()
                        ->default(14)
                        ->minValue(0)
                        ->suffix('days'),
                    Select::make('delivery_type')
                        ->label('Delivery Type')
                        ->options([
                            'Franco' => 'Franco',
                            'Loco' => 'Loco',
                        ])
                        ->live()
                        ->nullable(),
                    TextInput::make('delivery_type_details')
                        ->label('Delivery Type Details')
                        ->placeholder('Additional delivery type information')
                        ->maxLength(255)
                        ->visible(fn ($get): bool => $get('delivery_type') !== null),
                    Toggle::make('is_taxable')
                        ->label('Taxable Company')
                        ->default(true)
                        ->helperText('Whether this supplier charges tax'),
                    Textarea::make('delivery_term')
                        ->label('Delivery Term')
                        ->rows(2)
                        ->placeholder('Enter delivery terms and conditions'),
                ])
                ->columns(1),
            Section::make('Additional Information')
                ->schema([
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    CustomFields::form()->build(),
                ])
                ->columns(1),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Auto-generated (e.g., CMP-0001)'),

                ...self::getFormSchema(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->label('')->imageSize(28)->square(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('people_count')
                    ->label('Contacts')
                    ->counts('people')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('country')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tags.name')
                    ->label('Categories')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('defaultCurrency.code')
                    ->label('Currency')
                    ->toggleable(),
                TextColumn::make('payment_terms_days')
                    ->label('Payment Terms')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Update')
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
                SelectFilter::make('tags')
                    ->label('Categories')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                SelectFilter::make('country')
                    ->label('Country')
                    ->searchable()
                    ->preload()
                    ->options(fn () => Company::query()
                        ->where('is_supplier', true)
                        ->whereNotNull('country')
                        ->distinct()
                        ->pluck('country', 'country')
                        ->toArray()),
                SelectFilter::make('creation_source')
                    ->label('Creation Source')
                    ->options(CreationSource::class)
                    ->multiple(),
                TrashedFilter::make(),
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
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'country'];
    }

    /**
     * @return Builder<Company>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_supplier', true)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Get country options for select field.
     *
     * @return array<string, string>
     */
    public static function getCountryOptions(): array
    {
        return CompanyResource::getCountryOptions();
    }
}
