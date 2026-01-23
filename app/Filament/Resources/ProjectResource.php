<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use Relaticle\CustomFields\Facades\CustomFields;

final class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    /**
     * Get the base form fields for creating/editing a project.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $excludeBuyerField  Exclude Buyer field to prevent circular references
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $excludeBuyerField = false): array
    {
        $fields = [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->rows(2),
        ];

        // Add Buyer field unless excluded
        if (! $excludeBuyerField) {
            $fields[] = Select::make('buyer_id')
                ->relationship(
                    'buyer',
                    'name',
                    modifyQueryUsing: fn ($query) => $query->where('is_buyer', true)
                )
                ->label('Associated Buyer')
                ->nullable()
                ->preload()
                ->searchable()
                ->helperText('Optional: Link this project to a buyer')
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

        return array_merge($fields, [
            Section::make('Timeline')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Start Date'),
                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->afterOrEqual('start_date'),
                ])
                ->columns(2),
            Section::make('Status')
                ->schema([
                    Select::make('status')
                        ->options(ProjectStatus::class)
                        ->default(ProjectStatus::DRAFT)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive projects will not appear in selection lists'),
                ])
                ->columns(1),
            Section::make('Additional Information')
                ->schema([
                    Textarea::make('notes')
                        ->rows(2),
                    CustomFields::form()->build(),
                ])
                ->columns(1),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('project_number')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $record) => $rule->where('team_id', $record->team_id ?? Filament::getTenant()?->id))
                    ->placeholder('Auto-generated (e.g., PRJ-2026-0001)')
                    ->helperText('Leave empty to auto-generate'),
                ...self::getFormSchema(),
                Placeholder::make('created_at')
                    ->label('Created')
                    ->content(fn (?Project $record): string => $record?->created_at?->diffForHumans() ?? '-')
                    ->hiddenOn('create'),
                Placeholder::make('updated_at')
                    ->label('Last Modified')
                    ->content(fn (?Project $record): string => $record?->updated_at?->diffForHumans() ?? '-')
                    ->hiddenOn('create'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project_number')
                    ->label('Project #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
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
            ->defaultSort('project_number', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ProjectStatus::class),
                SelectFilter::make('is_active')
                    ->label('Active Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('buyer')
                    ->relationship('buyer', 'name', fn ($query) => $query->where('is_buyer', true))
                    ->searchable()
                    ->preload(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['project_number', 'name', 'description'];
    }

    /**
     * @return Builder<Project>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
