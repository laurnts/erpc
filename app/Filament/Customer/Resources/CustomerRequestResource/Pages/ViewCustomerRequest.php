<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\Pages;

use App\Enums\RequestSubmissionMethod;
use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Models\Request;
use App\Models\RequestItem;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use App\Services\Portal\CustomerPortalContext;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineParty;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

final class ViewCustomerRequest extends ViewRecord
{
    protected static string $resource = CustomerRequestResource::class;

    /**
     * Re-render the page (and its activity infolist) after the pinned composer
     * posts a note so the buyer's new note surfaces immediately.
     */
    #[On('note-posted')]
    public function refreshAfterNote(): void {}

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
                        TextEntry::make('item_type_summary')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Goods' => 'primary',
                                'Services' => 'success',
                                'Mixed' => 'warning',
                                default => 'gray',
                            }),
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
                        ViewEntry::make('attachments_list')
                            ->label('')
                            ->view('filament.customer.components.request-attachments-list'),
                    ]),
                Section::make('Request Progress')
                    ->schema([
                        ViewEntry::make('stage_timeline')
                            ->label('')
                            ->state(fn (Request $record): array => $presenter->timeline($record))
                            ->view('filament.customer.components.request-progress-timeline'),
                    ])
                    ->columnSpanFull(),
                Section::make('Activity')
                    ->schema([
                        ViewEntry::make('activity_timeline')
                            ->label('')
                            ->state(fn (Request $record): array => app(PortalTimelineSource::class)->forParty(
                                $record,
                                TimelineParty::buyer(app(CustomerPortalContext::class)->companyId()),
                            ))
                            ->view('filament.customer.components.request-activity-timeline'),
                    ])
                    ->columnSpanFull(),
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
