<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Exports\ProfitAndLossExporter;
use App\Filament\Forms\Components\ApprovalPersonnelSchema;
use App\Filament\Resources\ProfitAndLossResource\Pages\ListProfitAndLosses;
use App\Filament\Resources\ProfitAndLossResource\Pages\ViewProfitAndLoss;
use App\Models\ProfitAndLoss;
use App\Policies\ProfitAndLossPolicy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProfitAndLossResource extends Resource
{
    protected static ?string $model = ProfitAndLoss::class;

    protected static ?string $policy = ProfitAndLossPolicy::class;

    protected static ?string $recordTitleAttribute = 'pnl_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 23;

    protected static string|\UnitEnum|null $navigationGroup = 'Approval';

    protected static ?string $navigationLabel = 'Profit & Loss';

    protected static ?string $pluralModelLabel = 'Profit & Loss';

    protected static ?string $modelLabel = 'Profit & Loss';

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
            'email' => $data['email'] ?? $data['name'].'@'.$team->name.'.local',
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
                Section::make('PNL Information')
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
                TextColumn::make('approval_count')
                    ->label('Approvals')
                    ->getStateUsing(fn (\App\Models\ProfitAndLoss $record): string => $record->approvalCount().'/'.$record->totalApproversCount())
                    ->badge()
                    ->color(fn (\App\Models\ProfitAndLoss $record): string => $record->approvalCount() >= $record->totalApproversCount() ? 'success' : 'warning'),
                TextColumn::make('approvers.name')
                    ->label('Approved By')
                    ->getStateUsing(fn (\App\Models\ProfitAndLoss $record): \Illuminate\Support\Collection => $record->getApprovers()->pluck('name'))
                    ->badge()
                    ->separator(','),
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
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->exporter(ProfitAndLossExporter::class),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfitAndLosses::route('/'),
            'view' => ViewProfitAndLoss::route('/{record}'),
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

    /**
     * Control navigation visibility - show only to users with approval roles.
     * Navigation visibility is controlled by the policy's viewAny method.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return true; // Policy handles authorization
    }

    /**
     * Filter records by current tenant's team to prevent cross-tenant data access.
     *
     * @return Builder<ProfitAndLoss>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('team_id', $team?->getKey())
            ->with(['preparedBy', 'deptHeadSales', 'deputyDirector', 'approvedBy']);
    }
}
