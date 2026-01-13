<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreationSource;
use App\Filament\Resources\BuyerResource\Pages\ListBuyers;
use App\Filament\Resources\BuyerResource\Pages\ViewBuyer;
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

final class BuyerResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $modelLabel = 'Buyer';

    protected static ?string $pluralModelLabel = 'Buyers';

    protected static ?string $slug = 'buyers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    /**
     * Get the base form fields for creating/editing a buyer.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $excludePeopleField  Exclude People field to prevent circular references
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $excludePeopleField = false): array
    {
        $fields = [
            Hidden::make('is_buyer')->default(true),

            TextInput::make('name')
                ->label('Company Name')
                ->required()
                ->maxLength(255),

            Select::make('tags')
                ->label('Categories')
                ->relationship('tags', 'name')
                ->multiple()
                ->preload()
                ->searchable()
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
                ->helperText('Add people associated with this buyer')
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

        $fields = array_merge($fields, [
            Section::make('Location')
                ->schema([
                    TextInput::make('country')
                        ->maxLength(100),
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
                        ->getOptionLabelFromRecordUsing(fn (Currency $record): string => "{$record->code} - {$record->name}")
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
                    Select::make('account_owner_id')
                        ->relationship('accountOwner', 'name')
                        ->label('Account Owner')
                        ->nullable()
                        ->preload()
                        ->searchable(),
                ])
                ->columns(1),
            Section::make('Credit Settings')
                ->schema([
                    TextInput::make('credit_limit')
                        ->label('Credit Limit')
                        ->numeric()
                        ->default(0)
                        ->prefix('$'),
                    Toggle::make('is_on_hold')
                        ->label('On Hold')
                        ->helperText('Prevent new orders for this buyer'),
                    Textarea::make('on_hold_reason')
                        ->label('Hold Reason')
                        ->rows(2)
                        ->visible(fn ($get): bool => (bool) $get('is_on_hold')),
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

        return $fields;
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
                TextColumn::make('credit_limit')
                    ->label('Credit Limit')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('availableCredit')
                    ->label('Available Credit')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_on_hold')
                    ->label('On Hold')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success')
                    ->sortable(),
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
                SelectFilter::make('is_on_hold')
                    ->label('On Hold')
                    ->options([
                        '1' => 'On Hold',
                        '0' => 'Not On Hold',
                    ]),
                SelectFilter::make('country')
                    ->label('Country')
                    ->searchable()
                    ->preload()
                    ->options(fn () => Company::query()
                        ->where('is_buyer', true)
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
            'index' => ListBuyers::route('/'),
            'view' => ViewBuyer::route('/{record}'),
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
            ->where('is_buyer', true)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
