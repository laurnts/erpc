<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Resources\BuyerRequestResource\Schemas;

use App\Enums\ItemType;
use App\Enums\RequestSubmissionMethod;
use App\Models\Project;
use App\Models\UnitOfMeasure;
use App\Services\Portal\BuyerPortalContext;
use App\Support\DocumentUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final readonly class BuyerRequestForm
{
    /**
     * Upload directory for portal request attachments. The FileUpload below
     * and the AttachUploadedFiles call site must reference the same value —
     * drift between them silently drops attachments.
     */
    public const string ATTACHMENTS_UPLOAD_DIRECTORY = 'uploads-tmp/request-attachments';

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    public static function components(): array
    {
        $portalContext = app(BuyerPortalContext::class);

        return [
            Section::make('Request Details')
                ->schema([
                    Select::make('submission_method_choice')
                        ->label('Submission Method')
                        ->options([
                            RequestSubmissionMethod::MANUAL->value => 'Manual Entry',
                            RequestSubmissionMethod::DOCUMENT->value => 'Document Upload',
                        ])
                        ->default(RequestSubmissionMethod::MANUAL->value)
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('title')
                        ->label('Request Title')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Q3 office supplies purchase'),
                    Select::make('project_id')
                        ->label('Project (optional)')
                        ->options(fn (): array => Project::query()
                            ->where('buyer_id', $portalContext->companyId())
                            ->where('team_id', $portalContext->teamId())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable(),
                    DatePicker::make('required_by')
                        ->label('Required Date')
                        ->native(false),
                ]),
            Section::make('Request Items')
                ->visible(fn (Get $get): bool => $get('submission_method_choice') === RequestSubmissionMethod::MANUAL->value)
                ->schema([
                    Repeater::make('items')
                        ->label('Item List')
                        ->schema([
                            TextInput::make('description')
                                ->label('Description')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('item_type')
                                ->label('Item Type')
                                ->options(ItemType::class)
                                ->default(ItemType::GOODS)
                                ->required()
                                ->native(false)
                                ->columnSpanFull(),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(0.0001),
                            Select::make('unit_of_measure_id')
                                ->label('Unit')
                                ->options(fn (): array => UnitOfMeasure::query()
                                    ->where('team_id', $portalContext->teamId())
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('label', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Add Item')
                        ->required(),
                ]),
            Section::make('Request Documents')
                ->visible(fn (Get $get): bool => $get('submission_method_choice') === RequestSubmissionMethod::DOCUMENT->value)
                ->schema([
                    Textarea::make('description')
                        ->label('Notes / Description')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    FileUpload::make('attachment_files')
                        ->label('Upload Request/PR Documents')
                        ->helperText(DocumentUpload::helperText(10240))
                        ->acceptedFileTypes(DocumentUpload::ACCEPTED_MIME_TYPES)
                        ->disk('local')
                        ->directory(self::ATTACHMENTS_UPLOAD_DIRECTORY)
                        ->visibility('private')
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(10240)
                        ->required(),
                ]),
        ];
    }
}
