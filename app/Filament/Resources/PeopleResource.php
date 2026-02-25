<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CreationSource;
use App\Filament\Exports\PeopleExporter;
use App\Filament\Resources\PeopleResource\Pages\CreatePeople;
use App\Filament\Resources\PeopleResource\Pages\ListPeople;
use App\Filament\Resources\PeopleResource\Pages\ViewPeople;
use App\Filament\Resources\PeopleResource\RelationManagers\NotesRelationManager;
use App\Filament\Resources\PeopleResource\RelationManagers\TasksRelationManager;
use App\Models\Company;
use App\Models\People;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Relaticle\CustomFields\Facades\CustomFields;

final class PeopleResource extends Resource
{
    protected static ?string $model = People::class;

    protected static ?string $modelLabel = 'person';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    /**
     * Get the base form fields for creating/editing a person.
     * Used both in main form and inline create modals.
     *
     * @param  bool  $excludeCompaniesField  Exclude Companies field to prevent circular references
     * @param  bool  $excludeCustomFields  Exclude custom fields (use when form is used in createOptionForm where context is not People)
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormSchema(bool $excludeCompaniesField = false, bool $excludeCustomFields = false): array
    {
        $fields = [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('phone')
                ->label('Phone Number')
                ->tel()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),
        ];

        // Add Companies field unless excluded (to prevent circular references)
        if (! $excludeCompaniesField) {
            $fields[] = Select::make('companies')
                ->label('Companies')
                ->relationship('companies', 'name')
                ->multiple()
                ->preload()
                
                ->helperText('Assign this person to one or more companies')
                ->createOptionForm(CompanyResource::getFormSchema(excludePeopleField: true))
                ->createOptionUsing(function (array $data): int {
                    /** @var \App\Models\Team $team */
                    $team = Filament::getTenant();

                    /** @var Company $company */
                    $company = Company::create([
                        ...$data,
                        'team_id' => $team->id,
                        'creator_id' => auth()->id(),
                    ]);

                    return $company->id;
                });
        }

        // Include custom fields unless excluded (e.g. when used in createOptionForm where schema context is not People)
        if (! $excludeCustomFields) {
            $fields[] = CustomFields::form()->build()->columnSpanFull();
        }

        return $fields;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::getFormSchema(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')->label('')->size(24)->circular(),
                TextColumn::make('name')
                    ,
                TextColumn::make('companies.name')
                    ->label('Companies')
                    ->badge()
                    
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    
                    ->sortable()
                    ->toggleable()
                    ->getStateUsing(fn (People $record): string => $record->created_by)
                    ->color(fn (People $record): string => $record->isSystemCreated() ? 'secondary' : 'primary'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('creation_source')
                    ->label('Creation Source')
                    ->options(CreationSource::class)
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PeopleExporter::class),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePeople::route('/create'),
            'view' => ViewPeople::route('/{record}'),
        ];
    }

    /**
     * @return Builder<People>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
