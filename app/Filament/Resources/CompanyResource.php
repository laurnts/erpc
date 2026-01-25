<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreationSource;
use App\Filament\Exports\CompanyExporter;
use App\Filament\Resources\CompanyResource\Pages\CreateCompany;
use App\Filament\Resources\CompanyResource\Pages\ListCompanies;
use App\Filament\Resources\CompanyResource\Pages\ViewCompany;
use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\People;
use App\Models\Tag;
use App\Models\Team;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Relaticle\CustomFields\Facades\CustomFields;

final class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    /**
     * Get the base form fields for creating/editing a company.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $excludePeopleField  Exclude People field to prevent circular references
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $excludePeopleField = false): array
    {
        $fields = [
            TextInput::make('name')
                ->label('Company Name')
                ->required()
                ->maxLength(255),

            TextInput::make('domain')
                ->label('Domain')
                ->placeholder('example.com')
                ->url(false)
                ->maxLength(255)
                ->helperText('Company website domain'),

            Section::make('Company Type')
                ->schema([
                    Checkbox::make('is_buyer')
                        ->label('This company is a Buyer (Customer)')
                        ->inline()
                        ->helperText('Enable to track buyer-specific fields like credit limits'),
                    Checkbox::make('is_supplier')
                        ->label('This company is a Supplier (Vendor)')
                        ->inline()
                        ->helperText('Enable to track supplier-specific fields like lead times'),
                ])
                ->columns(2),

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
                ->columns(2),

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
                            /** @var \App\Models\Currency $currency */
                            $currency = \App\Models\Currency::create($data);

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
                ->columns(3),

            Section::make('Buyer Settings')
                ->schema([
                    TextInput::make('credit_limit')
                        ->label('Credit Limit')
                        ->numeric()
                        ->default(0)
                        ->prefix(function (): string {
                            /** @var Team|null $team */
                            $team = Filament::getTenant();
                            $currency = $team?->getBaseCurrency();

                            return $currency?->symbol_position === 'before' ? ($currency->symbol ?? '$') : '';
                        })
                        ->suffix(function (): string {
                            /** @var Team|null $team */
                            $team = Filament::getTenant();
                            $currency = $team?->getBaseCurrency();

                            return $currency?->symbol_position === 'after' ? ($currency->symbol ?? '') : '';
                        }),
                    Toggle::make('is_on_hold')
                        ->label('On Hold')
                        ->helperText('Prevent new orders for this buyer'),
                    Textarea::make('on_hold_reason')
                        ->label('Hold Reason')
                        ->rows(2)
                        ->visible(fn ($get): bool => (bool) $get('is_on_hold')),
                ])
                ->columns(2)
                ->visible(fn ($get): bool => (bool) $get('is_buyer'))
                ->collapsed(),

            Section::make('Supplier Settings')
                ->schema([
                    TextInput::make('lead_time_days')
                        ->label('Default Lead Time')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('days'),
                    Select::make('articles')
                        ->label('Articles / Products')
                        ->relationship('articles', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->helperText('Articles this supplier provides. Manage pricing details in the Articles tab.')
                        ->createOptionForm(ArticleResource::getFormSchema(forModal: true))
                        ->createOptionUsing(function (array $data): int {
                            /** @var \App\Models\Team $team */
                            $team = Filament::getTenant();

                            /** @var Article $article */
                            $article = Article::create([
                                ...$data,
                                'team_id' => $team->id,
                                'creator_id' => auth()->id(),
                            ]);

                            return $article->id;
                        }),
                ])
                ->visible(fn ($get): bool => (bool) $get('is_supplier'))
                ->collapsed(),

            Textarea::make('internal_notes')
                ->label('Notes')
                ->rows(3),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];

        // Add People field unless excluded (to prevent circular references)
        if (! $excludePeopleField) {
            // Insert after Categories (index 2)
            array_splice($fields, 3, 0, [
                Select::make('people')
                    ->label('People / Contacts')
                    ->relationship('people', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Add people associated with this company')
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
                    }),
            ]);
        }

        // Always include custom fields
        $fields[] = CustomFields::form()->build()->columns(1);

        return $fields;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Code field only in main form (auto-generated)
                TextInput::make('code')
                    ->label('Code')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Auto-generated'),

                // Use shared form schema (includes all fields + custom fields)
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
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->url(fn (?string $state): ?string => $state ? "https://{$state}" : null)
                    ->openUrlInNewTab(),
                TextColumn::make('people_count')
                    ->label('Contacts')
                    ->counts('people')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('country')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_buyer')
                    ->label('Buyer')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_supplier')
                    ->label('Supplier')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('defaultCurrency.code')
                    ->label('Currency')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('payment_terms_days')
                    ->label('Payment Terms')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('accountOwner.name')
                    ->label('Account Owner')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(fn (Company $record): string => $record->created_by)
                    ->color(fn (Company $record): string => $record->isSystemCreated() ? 'secondary' : 'primary'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('updated_at')
                    ->label('Last Update')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_buyer')
                    ->label('Buyers'),
                TernaryFilter::make('is_supplier')
                    ->label('Suppliers'),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('country')
                    ->label('Country')
                    ->searchable()
                    ->preload()
                    ->options(fn () => Company::query()->whereNotNull('country')->distinct()->pluck('country', 'country')->toArray()),
                SelectFilter::make('default_currency_id')
                    ->label('Currency')
                    ->options(fn () => Currency::query()
                        ->where('is_active', true)
                        ->pluck('code', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('creation_source')
                    ->label('Creation Source')
                    ->options(CreationSource::class)
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(CompanyExporter::class),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'view' => ViewCompany::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'domain', 'country'];
    }

    /**
     * @return Builder<Company>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
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
        return [
            'Indonesia' => 'Indonesia',
            'Afghanistan' => 'Afghanistan',
            'Albania' => 'Albania',
            'Algeria' => 'Algeria',
            'Argentina' => 'Argentina',
            'Australia' => 'Australia',
            'Austria' => 'Austria',
            'Bangladesh' => 'Bangladesh',
            'Belgium' => 'Belgium',
            'Brazil' => 'Brazil',
            'Brunei' => 'Brunei',
            'Cambodia' => 'Cambodia',
            'Canada' => 'Canada',
            'Chile' => 'Chile',
            'China' => 'China',
            'Colombia' => 'Colombia',
            'Czech Republic' => 'Czech Republic',
            'Denmark' => 'Denmark',
            'Egypt' => 'Egypt',
            'Finland' => 'Finland',
            'France' => 'France',
            'Germany' => 'Germany',
            'Greece' => 'Greece',
            'Hong Kong' => 'Hong Kong',
            'Hungary' => 'Hungary',
            'India' => 'India',
            'Ireland' => 'Ireland',
            'Israel' => 'Israel',
            'Italy' => 'Italy',
            'Japan' => 'Japan',
            'Kenya' => 'Kenya',
            'Laos' => 'Laos',
            'Malaysia' => 'Malaysia',
            'Mexico' => 'Mexico',
            'Myanmar' => 'Myanmar',
            'Netherlands' => 'Netherlands',
            'New Zealand' => 'New Zealand',
            'Nigeria' => 'Nigeria',
            'Norway' => 'Norway',
            'Pakistan' => 'Pakistan',
            'Peru' => 'Peru',
            'Philippines' => 'Philippines',
            'Poland' => 'Poland',
            'Portugal' => 'Portugal',
            'Qatar' => 'Qatar',
            'Romania' => 'Romania',
            'Russia' => 'Russia',
            'Saudi Arabia' => 'Saudi Arabia',
            'Singapore' => 'Singapore',
            'South Africa' => 'South Africa',
            'South Korea' => 'South Korea',
            'Spain' => 'Spain',
            'Sri Lanka' => 'Sri Lanka',
            'Sweden' => 'Sweden',
            'Switzerland' => 'Switzerland',
            'Taiwan' => 'Taiwan',
            'Thailand' => 'Thailand',
            'Turkey' => 'Turkey',
            'Ukraine' => 'Ukraine',
            'United Arab Emirates' => 'United Arab Emirates',
            'United Kingdom' => 'United Kingdom',
            'United States' => 'United States',
            'Vietnam' => 'Vietnam',
        ];
    }
}
