<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RequestPriority;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Resources\RequestResource\Pages\CreateRequest;
use App\Filament\Resources\RequestResource\Pages\ListRequests;
use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Filament\Resources\RequestResource\RelationManagers\AcceptanceReportsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierOrdersRelationManager;
use App\Filament\Resources\RequestResource\RelationManagers\SupplierQuotesRelationManager;
use App\Models\Request;
use App\Support\DocumentUpload;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;
use Override;

final class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static ?string $recordTitleAttribute = 'request_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    /**
     * Get the base form fields for creating/editing a request.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $excludeBuyerField  Exclude Buyer field when creating from Buyer context
     * @param  bool  $excludeProjectField  Exclude Project field when creating from Project context
     * @param  bool  $isCreate  Whether this is a create form (hides stage, defaults requested_at)
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $excludeBuyerField = false, bool $excludeProjectField = false, bool $isCreate = true): array
    {
        $fields = [];

        // Add Buyer field unless excluded (to prevent circular references)
        if (! $excludeBuyerField) {
            $fields[] = Select::make('buyer_id')
                ->relationship(
                    'buyer',
                    'name',
                    modifyQueryUsing: fn (Builder $query) => $query->where('is_buyer', true)
                )
                ->required()
                ->preload()

                ->selectablePlaceholder(false)
                ->live()
                ->searchable()
                ->afterStateUpdated(fn (Set $set): mixed => $set('project_id', null))
                ->createOptionForm(BuyerResource::getFormSchema(excludePeopleField: true))
                ->createOptionAction(fn (Action $action): \Filament\Actions\Action => $action->slideOver())
                ->createOptionUsing(function (array $data): int {
                    /** @var \App\Models\Team $team */
                    $team = Filament::getTenant();

                    /** @var \App\Models\Company $company */
                    $company = \App\Models\Company::create([
                        ...$data,
                        'is_buyer' => true,
                        'team_id' => $team->id,
                        'creator_id' => auth()->id(),
                    ]);

                    return $company->id;
                });
        }

        $fields[] = TextInput::make('title')
            ->label('Title')
            ->required()
            ->maxLength(255)
            ->placeholder('Brief description of this request');

        // Build Request Details section - stage only shown when editing
        $requestDetailsSchema = [];
        if (! $isCreate) {
            $requestDetailsSchema[] = Select::make('stage')
                ->options(RequestStage::class)
                ->default(RequestStage::DRAFT)
                ->required()
                ->selectablePlaceholder(false)
                ->native(false)
                // Cheap UI hint only: the binding gate is the RequestObserver
                // "updating" check (see completionFulfillmentError()), which
                // rejects the transition regardless of what the form allows.
                ->disableOptionWhen(fn (string $value, ?Request $record): bool => $value === RequestStage::COMPLETED->value
                    && $record instanceof Request
                    && ! $record->isFulfilled());
        }
        $requestDetailsSchema[] = Select::make('priority')
            ->options(RequestPriority::class)
            ->default(RequestPriority::NORMAL)
            ->required()
            ->selectablePlaceholder(false)
            ->native(false);

        $fields = array_merge($fields, [
            Section::make('Request Details')
                ->schema($requestDetailsSchema)
                ->columns($isCreate ? 1 : 2),
            Section::make('Timeline')
                ->schema([
                    DatePicker::make('requested_at')
                        ->label('Requested Date')
                        ->default(now())
                        ->nullable()
                        ->helperText('When the buyer made the inquiry'),
                    DatePicker::make('required_by')
                        ->label('Required By')
                        ->nullable()
                        ->helperText('Only if buyer specified a delivery date'),
                ])
                ->columns(2),
            Section::make('Notes')
                ->schema([
                    Textarea::make('description')
                        ->rows(2),
                    Textarea::make('internal_notes')
                        ->rows(2)
                        ->helperText('Notes visible only to your team'),
                ])
                ->columns(1),
        ]);

        // Add Project field unless excluded (to prevent circular references)
        // Projects are filtered by the selected buyer - 1 project = 1 buyer
        if (! $excludeProjectField) {
            $fields[] = Select::make('project_id')
                ->relationship(
                    'project',
                    'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('buyer_id', $get('buyer_id'))
                )
                ->nullable()
                ->preload()

                ->selectablePlaceholder(false)
                ->helperText('Optional: Group this request under a project (filtered by selected buyer)')
                ->disabled(fn (Get $get): bool => empty($get('buyer_id')))
                ->createOptionForm(ProjectResource::getFormSchema(excludeBuyerField: true))
                ->createOptionAction(fn (Action $action): \Filament\Actions\Action => $action->slideOver())
                ->createOptionUsing(function (array $data, Get $get): int {
                    /** @var \App\Models\Team $team */
                    $team = Filament::getTenant();

                    /** @var \App\Models\Project $project */
                    $project = \App\Models\Project::create([
                        ...$data,
                        'buyer_id' => $get('buyer_id'),
                        'team_id' => $team->id,
                        'creator_id' => auth()->id(),
                    ]);

                    return $project->id;
                });
        }

        // Staff-created requests must carry proof of the buyer's actual request
        // (email/letter/quote request/PO). Only shown on create; the edit form is untouched.
        if ($isCreate) {
            $fields[] = Section::make('Proof of Request')
                ->schema([
                    FileUpload::make('proof_files')
                        ->label('Proof of Request')
                        ->helperText(DocumentUpload::helperText(10240, notes: ["Buyer's email, letter, quote request, or PO"]))
                        ->acceptedFileTypes(DocumentUpload::ACCEPTED_MIME_TYPES)
                        ->disk('local')
                        ->directory(Request::PROOF_UPLOAD_DIRECTORY)
                        ->visibility('private')
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(10240)
                        ->required(),
                ]);
        }

        return $fields;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('request_number')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, ?Request $record) => $rule->where('team_id', $record?->team_id ?? Filament::getTenant()?->id))
                    ->placeholder('Auto-generated (e.g., REQ-2026-0001)')
                    ->helperText('Leave empty to auto-generate'),
                ...self::getFormSchema(isCreate: false),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request #')

                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('submission_method')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?RequestSubmissionMethod $state): string => $state?->getLabel() ?? 'Internal')
                    ->color(fn (?RequestSubmissionMethod $state): string => $state instanceof \App\Enums\RequestSubmissionMethod ? 'info' : 'gray')
                    ->toggleable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')

                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('Project')

                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('stage')
                    ->badge()
                    ->formatStateUsing(fn (RequestStage $state): string => $state->getTabLabelWithStep())
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
                TextColumn::make('fulfillment_status')
                    ->label('Fulfillment')
                    // Derived from per-item shipment/acceptance-report coverage, which cannot
                    // be expressed as a single eager-loadable count/exists per row without
                    // duplicating the completion SQL. Hidden by default so the extra queries
                    // (goodsChannelComplete()/servicesChannelComplete() per visible row) are
                    // only paid when an operator explicitly opts into the column.
                    ->state(fn (Request $record): string => $record->fulfillmentStatusLabel())
                    ->badge()
                    ->color(fn (Request $record): string => $record->isFulfilled() ? 'success' : 'warning')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
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
                SelectFilter::make('submission_method')
                    ->label('Submission Source')
                    ->options([
                        'portal' => 'From Portal',
                        'internal' => 'Internal',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === 'portal') {
                            return $query->whereNotNull('submission_method');
                        }

                        if (($data['value'] ?? null) === 'internal') {
                            return $query->whereNull('submission_method');
                        }

                        return $query;
                    }),
                SelectFilter::make('stage')
                    ->options(RequestStage::class)
                    ->multiple(),
                SelectFilter::make('priority')
                    ->options(RequestPriority::class)
                    ->multiple(),
                SelectFilter::make('buyer_id')
                    ->relationship('buyer', 'name', fn (Builder $query) => $query->where('is_buyer', true))
                    ->label('Buyer')
                    ->preload(),
                SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->label('Project')
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
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

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            SupplierQuotesRelationManager::class,
            BuyerQuotesRelationManager::class,
            BuyerOrdersRelationManager::class,
            SupplierOrdersRelationManager::class,
            ShipmentsRelationManager::class,
            AcceptanceReportsRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'),
            'create' => CreateRequest::route('/create'),
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
