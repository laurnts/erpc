<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Media\AttachUploadedFiles;
use App\Filament\Resources\AcceptanceReportResource\Pages\CreateAcceptanceReport;
use App\Filament\Resources\AcceptanceReportResource\Pages\ListAcceptanceReports;
use App\Filament\Resources\AcceptanceReportResource\Pages\ViewAcceptanceReport;
use App\Models\AcceptanceReport;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class AcceptanceReportResource extends Resource
{
    protected static ?string $model = AcceptanceReport::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'report_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 12;

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Acceptance Reports';

    protected static ?string $pluralModelLabel = 'Acceptance Reports';

    protected static ?string $modelLabel = 'Acceptance Report';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormComponents())
            ->columns(1);
    }

    /**
     * Form components, optionally scoped to a fixed request (relation-manager context).
     *
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    public static function getFormComponents(?\App\Models\Request $request = null): array
    {
        $resolveRequestId = fn (\Filament\Schemas\Components\Utilities\Get $get): mixed => $request?->getKey() ?? $get('request_id');

        return [
            Select::make('request_id')
                ->label('Request')
                ->relationship('request', 'request_number', modifyQueryUsing: fn ($query) => $query->whereHas('items', fn ($itemQuery) => $itemQuery->where('item_type', \App\Enums\ItemType::SERVICE)))
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->default($request?->getKey())
                ->disabled(fn ($record): bool => $record !== null || $request !== null)
                ->dehydrated()
                ->hidden($request !== null)
                ->helperText('Only requests with services items are available'),
            Select::make('items')
                ->label('Covered Items')
                ->multiple()
                ->relationship(
                    'items',
                    'description',
                    modifyQueryUsing: fn ($query, \Filament\Schemas\Components\Utilities\Get $get) => $query
                        ->where('request_id', $resolveRequestId($get))
                        ->whereNull('parent_id')
                        ->where('item_type', \App\Enums\ItemType::SERVICE),
                )
                ->preload()
                ->rule(fn (\Filament\Schemas\Components\Utilities\Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $resolveRequestId): void {
                    if (! is_array($value) || $value === []) {
                        return;
                    }

                    $foreign = \App\Models\RequestItem::query()
                        ->whereIn('id', $value)
                        ->where(fn ($query) => $query
                            ->where('request_id', '!=', $resolveRequestId($get))
                            ->orWhereNotNull('parent_id')
                            ->orWhere('item_type', '!=', \App\Enums\ItemType::SERVICE))
                        ->exists();

                    if ($foreign) {
                        $fail('Covered items must be services main items of this report\'s request.');
                    }
                })
                ->helperText('Services main items covered by this report; child items are covered with their parent'),
            Section::make('Report Details')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('report_number')
                        ->label('Report Number')
                        ->maxLength(50)
                        ->placeholder('Auto-generated')
                        ->helperText('Leave empty to auto-generate')
                        ->disabled(fn ($record) => $record !== null),
                    DatePicker::make('reported_at')
                        ->label('Reported Date')
                        ->required()
                        ->default(now()),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Attachments')
                ->schema([
                    FileUpload::make('attachments')
                        ->label('Files')
                        ->helperText('Upload PDF, Word documents, or images')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ])
                        ->disk('local')
                        ->directory(AcceptanceReport::ATTACHMENTS_UPLOAD_DIRECTORY)
                        ->visibility('private')
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(5120) // 5MB in KB
                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, $record, $set): void {
                            // Process uploaded files immediately when they're uploaded
                            if ($record && $record->exists && is_array($state) && $state !== []) {
                                app(AttachUploadedFiles::class)->execute($record, $state, 'attachments', AcceptanceReport::ATTACHMENTS_UPLOAD_DIRECTORY);

                                // Refresh the record to load new media
                                $record->refresh();
                            }
                        })
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('report_number')
                    ->label('Report Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('request.request_number')
                    ->label('Request')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reported_at')
                    ->label('Reported Date')
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
            ->filters([
                SelectFilter::make('request_id')
                    ->relationship('request', 'request_number')
                    ->label('Request')
                    ->preload()
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcceptanceReports::route('/'),
            'create' => CreateAcceptanceReport::route('/create'),
            'view' => ViewAcceptanceReport::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['report_number', 'notes'];
    }

    /**
     * @return Builder<AcceptanceReport>
     */
    public static function getEloquentQuery(): Builder
    {
        $team = Filament::getTenant();

        return parent::getEloquentQuery()
            ->whereHas('request', fn ($query) => $query->where('team_id', $team?->getKey()))
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
