<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PeopleResource;
use App\Filament\Resources\QuotationEvaluationResource\Pages\EditQuotationEvaluation;
use App\Filament\Resources\QuotationEvaluationResource\Pages\ListQuotationEvaluations;
use App\Filament\Resources\QuotationEvaluationResource\Pages\ViewQuotationEvaluation;
use App\Models\People;
use App\Models\QuotationEvaluation;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class QuotationEvaluationResource extends Resource
{
    protected static ?string $model = QuotationEvaluation::class;

    protected static ?string $recordTitleAttribute = 'qe_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 11;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Quotation Evaluations';

    protected static ?string $pluralModelLabel = 'Quotation Evaluations';

    protected static ?string $modelLabel = 'Quotation Evaluation';

    /**
     * Get the key account (People with is_key_account = true) select options for forms.
     *
     * @return array<int, string>
     */
    public static function getKeyAccountOptions(): array
    {
        return People::query()
            ->where('team_id', Filament::getTenant()?->getKey())
            ->where('is_key_account', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (People $person): array => [$person->getKey() => $person->name])
            ->toArray();
    }

    /**
     * Create a new key account person from form data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createKeyAccount(array $data): int
    {
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();

        /** @var People $person */
        $person = People::create([
            'name' => $data['name'],
            'is_key_account' => true,
            'team_id' => $team->id,
            'creator_id' => auth()->id(),
        ]);

        return $person->id;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('QE Information')
                    ->schema([
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Central Purchasing')
                    ->description('Approval workflow personnel')
                    ->schema([
                        Select::make('prepared_by_id')
                            ->label('Prepared By')
                            ->relationship(
                                'preparedBy',
                                'name',
                                modifyQueryUsing: function ($query, $livewire) {
                                    $query->where('is_key_account', true);

                                    // Filter to only show key accounts assigned to handle the request's buyer
                                    if (isset($livewire->record) && $livewire->record && $livewire->record->request) {
                                        $request = $livewire->record->request;
                                        if ($request->buyer_id) {
                                            $query->whereHas('buyers', function ($q) use ($request): void {
                                                $q->where('companies.id', $request->buyer_id);
                                            });
                                        }
                                    }

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm(PeopleResource::getFormSchema())
                            ->createOptionUsing(function (array $data): int {
                                $data['is_key_account'] = true;

                                return self::createKeyAccount($data);
                            })
                            ->editOptionForm(PeopleResource::getFormSchema())
                            ->editOptionAction(fn ($action) => $action->modalHeading('Edit Key Account')),
                        TextInput::make('dept_head_sales_name')
                            ->label('Acknowledged By - Dept Head of Sales')
                            ->maxLength(255),
                        TextInput::make('deputy_director_name')
                            ->label('Acknowledged By - Deputy Director')
                            ->maxLength(255),
                        TextInput::make('approved_by_name')
                            ->label('Approved By')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qe_number')
                    ->label('QE Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('qe_date')
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
            'index' => ListQuotationEvaluations::route('/'),
            'view' => ViewQuotationEvaluation::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['qe_number', 'description'];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    /**
     * @return Builder<QuotationEvaluation>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('team_id', $team?->getKey());
    }
}
