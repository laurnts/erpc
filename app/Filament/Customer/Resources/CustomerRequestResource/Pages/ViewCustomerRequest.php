<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\Pages;

use App\Enums\RequestSubmissionMethod;
use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Models\Request;
use App\Models\RequestItem;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ViewCustomerRequest extends ViewRecord
{
    protected static string $resource = CustomerRequestResource::class;

    public function infolist(Schema $schema): Schema
    {
        $presenter = app(CustomerRequestStagePresenter::class);

        return $schema
            ->components([
                Section::make('Summary')
                    ->schema([
                        TextEntry::make('request_number')
                            ->label('Request No.'),
                        TextEntry::make('title')
                            ->label('Title'),
                        TextEntry::make('request_type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('stage')
                            ->label('Status')
                            ->formatStateUsing(fn (Request $record): string => $presenter->label($record))
                            ->badge()
                            ->color(fn (Request $record): string => $presenter->color($record->stage)),
                        TextEntry::make('submitted_at')
                            ->label('Submitted At')
                            ->dateTime(),
                        TextEntry::make('required_by')
                            ->label('Required Date')
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('submission_method')
                            ->label('Submission Method')
                            ->badge()
                            ->visible(fn (Request $record): bool => $record->isPortalSubmission()),
                        TextEntry::make('description')
                            ->label('Notes')
                            ->visible(fn (Request $record): bool => $record->submission_method === RequestSubmissionMethod::DOCUMENT)
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('project.name')
                            ->label('Project')
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Request Items')
                    ->visible(fn (Request $record): bool => $record->items()->exists())
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Description'),
                                TextEntry::make('quantity')
                                    ->label('Quantity'),
                                TextEntry::make('unitOfMeasure.label')
                                    ->label('Unit')
                                    ->placeholder(fn (RequestItem $record): string => $record->unit?->value ?? 'pcs'),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Attached Documents')
                    ->visible(fn (Request $record): bool => $record->submission_method === RequestSubmissionMethod::DOCUMENT)
                    ->schema([
                        TextEntry::make('attachments_list')
                            ->label('')
                            ->state(fn (Request $record): string => $record->getMedia('attachments')
                                ->map(fn ($media): string => '- '.$media->file_name)
                                ->implode("\n") ?: 'No documents yet')
                            ->markdown(),
                    ]),
                Section::make('Request Progress')
                    ->schema([
                        TextEntry::make('stage_timeline')
                            ->label('')
                            ->state(function (Request $record) use ($presenter): string {
                                return collect($presenter->timeline($record))
                                    ->map(fn (array $step): string => sprintf(
                                        '%s %s',
                                        $step['current'] ? '▶' : ($step['completed'] ? '✓' : '○'),
                                        $step['label'],
                                    ))
                                    ->implode("\n");
                            })
                            ->markdown(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (Request $record): bool => $record->isEditableByCustomer()),
        ];
    }
}
