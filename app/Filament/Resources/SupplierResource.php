<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreationSource;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use App\Models\Company;
use App\Models\Currency;
use App\Models\People;
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
use Filament\Forms\Components\ColorPicker;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('is_supplier')->default(true),

                TextInput::make('name')
                    ->label('Company Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Code')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Auto-generated (e.g., CMP-0001)'),

                Select::make('tags')
                    ->label('Categories')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('What products/services they supply')
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

                Select::make('people')
                    ->label('People / Contacts')
                    ->relationship('people', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Add people associated with this supplier')
                    ->createOptionForm(PeopleResource::getFormSchema(excludeCompaniesField: true))
                    ->createOptionUsing(function (array $data): int {
                        /** @var People $person */
                        $person = People::create([
                            ...$data,
                            'team_id' => auth()->user()->currentTeam->id,
                            'creator_id' => auth()->id(),
                        ]);

                        return $person->id;
                    }),

                Section::make('Location')
                    ->schema([
                        TextInput::make('country')
                            ->maxLength(100),
                        Textarea::make('address')
                            ->label('Address')
                            ->rows(2),
                    ])
                    ->columns(2),

                Section::make('Financial Settings')
                    ->schema([
                        Select::make('default_currency_id')
                            ->label('Default Currency')
                            ->options(fn () => Currency::query()
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Currency $currency) => [
                                    $currency->id => $currency->code.' - '.$currency->name,
                                ])
                                ->all())
                            ->default(function (): ?int {
                                $defaultCode = auth()->user()->currentTeam?->getErpSettings()->default_currency ?? 'USD';

                                return Currency::query()->where('code', $defaultCode)->where('is_active', true)->value('id');
                            })
                            ->nullable()
                            ->searchable(),
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
                    ])
                    ->columns(3),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                CustomFields::form()->forSchema($schema)->build()->columns(1),
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
            'index' => ListSuppliers::route('/'),
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
}
