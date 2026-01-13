<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RequestPriority;
use App\Enums\RequestStage;
use App\Filament\Resources\RequestResource\Pages\ListRequests;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\NotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\TasksRelationManager;
use App\Models\Request;
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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;

final class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static ?string $recordTitleAttribute = 'request_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Trading';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('request_number')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $record) => $rule->where('team_id', $record?->team_id ?? auth()->user()->currentTeam->id))
                    ->placeholder('Auto-generated (e.g., REQ-2026-0001)')
                    ->helperText('Leave empty to auto-generate'),
                Select::make('buyer_id')
                    ->relationship(
                        'buyer',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where('is_buyer', true)
                    )
                    ->required()
                    ->preload()
                    ->searchable()
                    ->createOptionForm(BuyerResource::getFormSchema(excludePeopleField: true))
                    ->createOptionUsing(function (array $data): int {
                        /** @var \App\Models\Company $company */
                        $company = \App\Models\Company::create([
                            ...$data,
                            'is_buyer' => true,
                            'team_id' => auth()->user()->currentTeam->id,
                            'creator_id' => auth()->id(),
                        ]);

                        return $company->id;
                    }),
                Section::make()
                    ->schema([
                        Select::make('stage')
                            ->options(RequestStage::class)
                            ->default(RequestStage::DRAFT)
                            ->required()
                            ->native(false),
                        Select::make('priority')
                            ->options(RequestPriority::class)
                            ->default(RequestPriority::NORMAL)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
                Section::make()
                    ->schema([
                        DatePicker::make('requested_at')
                            ->label('Requested Date')
                            ->nullable(),
                        DatePicker::make('required_by')
                            ->label('Required By')
                            ->nullable()
                            ->helperText('When the buyer needs this order'),
                    ])
                    ->columns(2),
                Textarea::make('description')
                    ->rows(2),
                Textarea::make('internal_notes')
                    ->rows(2)
                    ->helperText('Notes visible only to your team'),
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable()
                    ->preload()
                    ->searchable()
                    ->helperText('Optional: Group this request under a project')
                    ->createOptionForm(ProjectResource::getFormSchema())
                    ->createOptionUsing(function (array $data): int {
                        /** @var \App\Models\Project $project */
                        $project = \App\Models\Project::create([
                            ...$data,
                            'team_id' => auth()->user()->currentTeam->id,
                            'creator_id' => auth()->id(),
                        ]);

                        return $project->id;
                    }),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('stage')
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('required_by')
                    ->label('Required By')
                    ->date()
                    ->sortable()
                    ->color(fn (Request $record): string => $record->required_by !== null && $record->required_by->isPast() ? 'danger' : 'gray'),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable()
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
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
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('stage')
                    ->options(RequestStage::class)
                    ->multiple(),
                SelectFilter::make('priority')
                    ->options(RequestPriority::class)
                    ->multiple(),
                SelectFilter::make('buyer_id')
                    ->relationship('buyer', 'name', fn ($query) => $query->where('is_buyer', true))
                    ->label('Buyer')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->label('Project')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
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

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            SupplierQuotesRelationManager::class,
            SupplierOrdersRelationManager::class,
            BuyerQuotesRelationManager::class,
            BuyerOrdersRelationManager::class,
            ShipmentsRelationManager::class,
            TasksRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'),
            'view' => ViewRequest::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['request_number', 'description'];
    }

    /**
     * @return Builder<Request>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
