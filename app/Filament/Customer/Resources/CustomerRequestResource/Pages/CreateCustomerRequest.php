<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\Pages;

use App\Actions\CustomerPortal\NotifyTeamOfPortalRequest;
use App\Enums\ItemType;
use App\Enums\RequestStage;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Models\Project;
use App\Models\RequestItem;
use App\Models\UnitOfMeasure;
use App\Services\CustomerPortal\PortalContext;
use App\Support\Media\DocumentPathGenerator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class CreateCustomerRequest extends CreateRecord
{
    protected static string $resource = CustomerRequestResource::class;

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    public static function formComponents(): array
    {
        $portalContext = app(PortalContext::class);

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
                        ->label('Upload RFQ/PR Documents')
                        ->helperText('PDF, Excel, Word, or images (max 10MB per file)')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])
                        ->disk('local')
                        ->directory('requests/portal-attachments')
                        ->visibility('private')
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(10240)
                        ->required(),
                ]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::formComponents())
            ->columns(1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $portalContext = app(PortalContext::class);
        $method = RequestSubmissionMethod::from($data['submission_method_choice'] ?? RequestSubmissionMethod::MANUAL->value);

        unset($data['items'], $data['attachment_files'], $data['submission_method_choice']);

        return [
            ...$data,
            'team_id' => $portalContext->teamId(),
            'buyer_id' => $portalContext->companyId(),
            'stage' => RequestStage::DRAFT,
            'submission_method' => $method,
            'submitted_at' => now(),
            'submitted_by_user_id' => auth()->id(),
            'requested_at' => now()->toDateString(),
            'creator_id' => auth()->id(),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $formState = $this->form->getState();
        $method = RequestSubmissionMethod::from($formState['submission_method_choice'] ?? RequestSubmissionMethod::MANUAL->value);

        $record = parent::handleRecordCreation($data);

        if ($method === RequestSubmissionMethod::MANUAL) {
            $items = $formState['items'] ?? [];

            foreach ($items as $index => $item) {
                $uom = UnitOfMeasure::query()->find($item['unit_of_measure_id']);

                RequestItem::query()->create([
                    'request_id' => $record->getKey(),
                    'description' => $item['description'],
                    'item_type' => $item['item_type'] ?? ItemType::GOODS,
                    'quantity' => $item['quantity'],
                    'unit_of_measure_id' => $item['unit_of_measure_id'],
                    'unit' => $uom?->code ?? 'pcs',
                    'sort_order' => $index,
                ]);
            }
        }

        if ($method === RequestSubmissionMethod::DOCUMENT) {
            $files = $formState['attachment_files'] ?? [];

            if (is_array($files)) {
                foreach ($files as $file) {
                    if (! is_string($file)) {
                        continue;
                    }

                    $filePath = storage_path('app/'.ltrim($file, '/'));

                    if (file_exists($filePath)) {
                        $record->addMedia($filePath)
                            ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
                            ->toMediaCollection('attachments');
                    }
                }
            }
        }

        app(NotifyTeamOfPortalRequest::class)->execute($record);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return CustomerRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
