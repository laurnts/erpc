<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\PeopleResource;
use App\Filament\Resources\TagResource;
use App\Models\Article;
use App\Models\Currency;
use App\Models\People;
use App\Models\Tag;
use App\Models\Team;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Facades\CustomFields;

final class CompanyForm
{
    /**
     * Shared company form fields used by inline create modals (People, relation managers).
     *
     * @param  bool  $excludePeopleField  Exclude People field to prevent circular references
     * @param  bool  $requireRole  Require at least one of the buyer/supplier roles to be selected
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(bool $excludePeopleField = false, bool $requireRole = false): array
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

            self::roleSection($requireRole),

            Select::make('tags')
                ->label('Categories')
                ->relationship('tags', 'name')
                ->multiple()
                ->preload()
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
                        ->options(self::countryOptions())
                        ->default('Indonesia'),
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
                            modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                        )
                        ->getOptionLabelFromRecordUsing(fn (?Currency $record): string => $record instanceof \App\Models\Currency ? "{$record->code} - {$record->name}" : '')
                        ->default(function (): ?int {
                            /** @var \App\Models\Team|null $team */
                            $team = Filament::getTenant();
                            $defaultCode = $team?->getErpSettings()->default_currency ?? 'USD';

                            return Currency::query()->where('code', $defaultCode)->where('is_active', true)->value('id');
                        })
                        ->nullable()
                        ->preload()
                        ->createOptionForm(CurrencyResource::getFormSchema(excludeDefaultField: true))
                        ->createOptionUsing(function (array $data): int {
                            /** @var \App\Models\Currency $currency */
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
                        ->relationship(
                            'accountOwner',
                            'name',
                            function (Builder $query): Builder {
                                $team = Filament::getTenant();

                                return $query->whereKey($team instanceof Team ? $team->allUsers()->pluck('id')->all() : []);
                            }
                        )
                        ->label('Account Owner')
                        ->nullable()
                        ->preload(),
                ])
                ->columns(3),

            Section::make('Buyer Settings')
                ->schema([
                    TextInput::make('credit_limit')
                        ->label('Credit Limit')
                        ->numeric()
                        ->default(0)
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('This is the approved credit limit. It only changes when a credit limit request is approved.')
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
                        ->visible(fn (Get $get): bool => (bool) $get('is_on_hold')),
                ])
                ->columns(2)
                ->visible(fn (Get $get): bool => (bool) $get('is_buyer'))
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
                        ->helperText('Articles this supplier provides. Manage pricing details in the Articles tab.')
                        ->createOptionForm(ArticleResource::getFormSchema(forModal: true, excludeSuppliersField: true))
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
                ->visible(fn (Get $get): bool => (bool) $get('is_supplier'))
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
            // Insert after Categories (index 3)
            array_splice($fields, 4, 0, [
                Select::make('people')
                    ->label('People / Contacts')
                    ->relationship('people', 'name')
                    ->multiple()
                    ->preload()
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

    /**
     * @return array<string, string>
     */
    public static function countryOptions(): array
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

    /**
     * The Company Type section. Every company must be a buyer, a supplier, or both;
     * paths that do not force a role via mutateFormDataUsing must pass $requireRole.
     */
    private static function roleSection(bool $requireRole): Section
    {
        $atLeastOneRole = fn (string $other): Closure => (fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $other): void {
            if (! $value && ! $get($other)) {
                $fail('The company must be a Buyer, a Supplier, or both.');
            }
        });

        $isBuyer = Checkbox::make('is_buyer')
            ->label('This company is a Buyer (Customer)')
            ->inline()
            ->helperText('Enable to track buyer-specific fields like credit limits');

        $isSupplier = Checkbox::make('is_supplier')
            ->label('This company is a Supplier (Vendor)')
            ->inline()
            ->helperText('Enable to track supplier-specific fields like lead times');

        if ($requireRole) {
            $isBuyer->live()->rules([$atLeastOneRole('is_supplier')]);
            $isSupplier->live()->rules([$atLeastOneRole('is_buyer')]);
        } else {
            $isBuyer->live();
            $isSupplier->live();
        }

        return Section::make('Company Type')
            ->schema([$isBuyer, $isSupplier])
            ->columns(2);
    }
}
