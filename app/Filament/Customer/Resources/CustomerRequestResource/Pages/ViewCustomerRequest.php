<?php

declare(strict_types=1);

namespace App\Filament\Customer\Resources\CustomerRequestResource\Pages;

use App\Enums\RequestSubmissionMethod;
use App\Filament\Customer\Resources\CustomerRequestResource;
use App\Filament\Customer\Resources\CustomerRequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Livewire\CustomerPendingQuoteActions;
use App\Models\Request;
use App\Services\CustomerPortal\CustomerRequestStagePresenter;
use App\Services\Portal\CustomerPortalContext;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineParty;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

final class ViewCustomerRequest extends ViewRecord
{
    protected static string $resource = CustomerRequestResource::class;

    /**
     * Re-render the page after notes or quote actions so activity and status stay current.
     */
    #[On('note-posted')]
    #[On('quote-action-taken')]
    public function refreshAfterNote(): void {}

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->load(['items.unitOfMeasure', 'project']);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getRecord()->title;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            '<span class="font-mono text-sm text-gray-500 dark:text-gray-400">'
            .e($this->getRecord()->request_number)
            .'</span>',
        );
    }

    public function infolist(Schema $schema): Schema
    {
        $presenter = app(CustomerRequestStagePresenter::class);

        return $schema
            ->columns(1)
            ->components([
                Grid::make()
                    ->columns(['default' => 1, 'lg' => 4])
                    ->schema([
                        Group::make()
                            ->columnSpan(['lg' => 1])
                            ->extraAttributes([
                                'class' => 'customer-request-sidebar lg:sticky lg:top-6 lg:self-start',
                                'style' => 'position: sticky; top: 1.5rem; align-self: flex-start; height: fit-content;',
                            ])
                            ->schema([
                                Section::make('Summary')
                                    ->schema([
                                        ViewEntry::make('summary_sidebar')
                                            ->label('')
                                            ->view('filament.customer.components.request-summary-sidebar'),
                                    ]),
                                Section::make('Attached Documents')
                                    ->visible(fn (Request $record): bool => $record->submission_method === RequestSubmissionMethod::DOCUMENT)
                                    ->schema([
                                        ViewEntry::make('attachments_list')
                                            ->label('')
                                            ->view('filament.customer.components.request-attachments-list'),
                                    ]),
                            ]),
                        Group::make()
                            ->columnSpan(['lg' => 3])
                            ->schema([
                                Livewire::make(CustomerPendingQuoteActions::class, fn (Request $record): array => [
                                    'request' => $record,
                                ])
                                    ->key(fn (Request $record): string => 'pending-quote-actions-'.$record->getKey())
                                    ->visible(fn (Request $record): bool => $record->buyerQuotes()
                                        ->where('status', \App\Enums\BuyerQuoteStatus::SENT)
                                        ->exists()),
                                Section::make('Request Progress')
                                    ->schema([
                                        ViewEntry::make('stage_timeline')
                                            ->label('')
                                            ->state(fn (Request $record): array => $presenter->timeline($record))
                                            ->view('filament.customer.components.request-progress-timeline'),
                                    ]),
                                Section::make('Quotes')
                                    ->schema([
                                        Livewire::make(BuyerQuotesRelationManager::class, fn (Request $record): array => [
                                            'ownerRecord' => $record,
                                            'pageClass' => self::class,
                                        ])->key(fn (Request $record): string => 'buyer-quotes-'.$record->getKey()),
                                    ])
                                    ->visible(fn (Request $record): bool => $record->buyerQuotes()
                                        ->where('status', '!=', \App\Enums\BuyerQuoteStatus::DRAFT)
                                        ->exists()),
                                Section::make('Request Items')
                                    ->visible(fn (Request $record): bool => $record->items()->exists())
                                    ->schema([
                                        ViewEntry::make('items_table')
                                            ->label('')
                                            ->view('filament.customer.components.request-items-table'),
                                    ]),
                                Section::make(fn (Request $record): string => 'Activity · '.$this->activityCount($record))
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        ViewEntry::make('activity_timeline')
                                            ->label('')
                                            ->state(fn (Request $record): array => app(PortalTimelineSource::class)->forParty(
                                                $record,
                                                TimelineParty::buyer(app(CustomerPortalContext::class)->companyId()),
                                            ))
                                            ->view('filament.customer.components.request-activity-timeline'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public function getRelationManagers(): array
    {
        return array_values(array_filter(
            parent::getRelationManagers(),
            fn (string $manager): bool => ! in_array($manager, [
                BuyerQuotesRelationManager::class,
            ], true),
        ));
    }

    protected function getHeaderActions(): array
    {
        $presenter = app(CustomerRequestStagePresenter::class);

        return [
            Action::make('status')
                ->label(fn (Request $record): string => $presenter->label($record))
                ->badge()
                ->color(fn (Request $record): string => $presenter->color($presenter->effectiveStage($record)))
                ->disabled()
                ->extraAttributes(['class' => 'pointer-events-none']),
            EditAction::make()
                ->visible(fn (Request $record): bool => $record->isEditableByCustomer()),
        ];
    }

    private function activityCount(Request $record): int
    {
        return count(app(PortalTimelineSource::class)->forParty(
            $record,
            TimelineParty::buyer(app(CustomerPortalContext::class)->companyId()),
        ));
    }
}
