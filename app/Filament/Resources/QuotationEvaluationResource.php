<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Forms\Components\ApprovalPersonnelSchema;
use App\Filament\Resources\QuotationEvaluationResource\Pages\ListQuotationEvaluations;
use App\Filament\Resources\QuotationEvaluationResource\Pages\ViewQuotationEvaluation;
use App\Models\QuotationEvaluation;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

    protected static ?int $navigationSort = 22;

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?string $navigationLabel = 'Quotation Evaluations';

    protected static ?string $pluralModelLabel = 'Quotation Evaluations';

    protected static ?string $modelLabel = 'Quotation Evaluation';

    /**
     * Get the key account (team members with Central Purchasing Key Account role) select options for forms.
     *
     * @return array<int, string>
     */
    public static function getKeyAccountOptions(): array
    {
        return \App\Services\TeamMemberService::getTeamMemberOptionsByRole(
            Filament::getTenant(),
            \App\Enums\CentralPurchasingRole::KEY_ACCOUNT
        );
    }

    /**
     * Create a new key account team member from form data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createKeyAccount(array $data): int
    {
        /** @var \App\Models\Team $team */
        $team = Filament::getTenant();

        /** @var \App\Models\User $user */
        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? $data['name'] . '@' . $team->name . '.local',
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)), // Temporary password
        ]);

        // Add user to team with Central Purchasing Key Account role
        $team->users()->attach($user->id, [
            'role' => 'central_purchasing',
            'central_purchasing_role' => \App\Enums\CentralPurchasingRole::KEY_ACCOUNT->value,
        ]);

        return $user->id;
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
                ...ApprovalPersonnelSchema::make(
                    buyerId: fn ($livewire) => isset($livewire->record) && $livewire->record && $livewire->record->request
                        ? $livewire->record->request->buyer_id
                        : null,
                    sectionTitle: 'Central Purchasing',
                    columns: 2
                ),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qe_number')
                    ->label('QE Number')
                    
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(40)
                    
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
            ->where('team_id', $team?->getKey())
            ->with(['preparedBy', 'deptHeadSales', 'deputyDirector', 'approvedBy']);
    }
}
