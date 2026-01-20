<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProfitAndLossResource\Pages\EditProfitAndLoss;
use App\Filament\Resources\ProfitAndLossResource\Pages\ListProfitAndLosses;
use App\Filament\Resources\ProfitAndLossResource\Pages\ViewProfitAndLoss;
use App\Models\KeyAccount;
use App\Models\ProfitAndLoss;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ProfitAndLossResource extends Resource
{
    protected static ?string $model = ProfitAndLoss::class;

    protected static ?string $recordTitleAttribute = 'pnl_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 12;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Profit & Loss';

    protected static ?string $pluralModelLabel = 'Profit & Loss';

    protected static ?string $modelLabel = 'Profit & Loss';

    /**
     * Get the key account select options for forms.
     *
     * @return array<int, string>
     */
    public static function getKeyAccountOptions(): array
    {
        return KeyAccount::query()
            ->where('team_id', Filament::getTenant()?->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (KeyAccount $ka): array => [$ka->getKey() => $ka->display_name])
            ->toArray();
    }

    /**
     * Create a new key account from form data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createKeyAccount(array $data): int
    {
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();

        /** @var KeyAccount $keyAccount */
        $keyAccount = KeyAccount::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'team_id' => $team->id,
            'creator_id' => auth()->id(),
        ]);

        return $keyAccount->id;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('PNL Information')
                    ->schema([
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Central Purchasing')
                    ->description('Approval workflow personnel')
                    ->schema([
                        Select::make('prepared_by_id')
                            ->label('Prepared By')
                            ->relationship('preparedBy', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(KeyAccountResource::getFormSchema())
                            ->createOptionUsing(function (array $data): int {
                                return self::createKeyAccount($data);
                            }),
                        TextInput::make('dept_head_sales_name')
                            ->label('Dept Head of Sales')
                            ->maxLength(255),
                        TextInput::make('deputy_director_name')
                            ->label('Deputy Director')
                            ->maxLength(255),
                        TextInput::make('approved_by_name')
                            ->label('Approved By')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pnl_number')
                    ->label('PNL Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(query: fn ($query, $direction) => $query
                        ->withExists(['request as has_buyer_orders' => fn ($q) => $q->whereHas('buyerOrders')])
                        ->orderBy('has_buyer_orders', $direction)),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('pnl_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfitAndLosses::route('/'),
            'view' => ViewProfitAndLoss::route('/{record}'),
            'edit' => EditProfitAndLoss::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['pnl_number', 'description'];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
