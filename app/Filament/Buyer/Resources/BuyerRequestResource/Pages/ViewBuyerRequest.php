<?php

declare(strict_types=1);

namespace App\Filament\Buyer\Resources\BuyerRequestResource\Pages;

use App\Enums\BuyerQuoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RequestSubmissionMethod;
use App\Filament\Buyer\Resources\BuyerRequestResource;
use App\Filament\Buyer\Resources\BuyerRequestResource\RelationManagers\BuyerQuotesRelationManager;
use App\Filament\Concerns\InteractsWithPaymentCard;
use App\Livewire\BuyerPendingQuoteActions;
use App\Models\BuyerInvoice;
use App\Models\Request;
use App\Services\BuyerPortal\BuyerRequestStagePresenter;
use App\Services\Portal\BuyerPortalContext;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineParty;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

final class ViewBuyerRequest extends ViewRecord
{
    use InteractsWithPaymentCard;

    protected static string $resource = BuyerRequestResource::class;

    /**
     * Buyer-submitted payments are recorded as PENDING (awaiting staff
     * confirmation) rather than trusted immediately like staff entries.
     */
    protected function paymentActorType(): string
    {
        return 'buyer';
    }

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
        $presenter = app(BuyerRequestStagePresenter::class);

        return $schema
            ->columns(1)
            ->components([
                // Request header — same structure as the staff view, minus
                // staff-only data (internal notes, financials, approvals).
                Section::make()
                    ->schema([
                        TextEntry::make('request_number')
                            ->label('Request number')
                            ->weight('bold')
                            ->size('md')
                            ->copyable(),
                        TextEntry::make('title')
                            ->label('Title')
                            ->weight('bold')
                            ->size('md')
                            ->columnSpan(3),
                        TextEntry::make('stage')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (Request $record): string => $presenter->label($record))
                            ->color(fn (Request $record): string => $presenter->color($presenter->effectiveStage($record))),
                        TextEntry::make('priority')
                            ->badge(),
                        TextEntry::make('item_type_summary')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Goods' => 'primary',
                                'Services' => 'success',
                                'Mixed' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('project.name')
                            ->label('Project')
                            ->icon('heroicon-o-folder')
                            ->placeholder('—'),
                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('required_by')
                            ->label('Required By')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->since(),
                        TextEntry::make('submission_method')
                            ->label('Source')
                            ->badge()
                            ->formatStateUsing(fn (RequestSubmissionMethod $state): string => $state->getLabel())
                            ->visible(fn (Request $record): bool => $record->isPortalSubmission()),
                        TextEntry::make('submittedBy.name')
                            ->label('Submitted By')
                            ->placeholder('—')
                            ->visible(fn (Request $record): bool => $record->isPortalSubmission()),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Livewire::make(BuyerPendingQuoteActions::class, fn (Request $record): array => [
                    'request' => $record,
                ])
                    ->key(fn (Request $record): string => 'pending-quote-actions-'.$record->getKey())
                    ->visible(fn (Request $record): bool => $record->buyerQuotes()
                        ->where('status', BuyerQuoteStatus::SENT)
                        ->exists()),
                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->label('')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(fn (Request $record): bool => filled($record->description)),
                Section::make('Attached Documents')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->visible(fn (Request $record): bool => $record->submission_method === RequestSubmissionMethod::DOCUMENT)
                    ->schema([
                        ViewEntry::make('attachments_list')
                            ->label('')
                            ->view('filament.buyer.components.request-attachments-list'),
                    ]),
                Section::make('Request Progress')
                    ->schema([
                        ViewEntry::make('stage_timeline')
                            ->label('')
                            ->state(fn (Request $record): array => $presenter->timeline($record))
                            ->view('filament.buyer.components.request-progress-timeline'),
                    ]),
                Section::make('Quotes')
                    ->schema([
                        Livewire::make(BuyerQuotesRelationManager::class, fn (Request $record): array => [
                            'ownerRecord' => $record,
                            'pageClass' => self::class,
                        ])->key(fn (Request $record): string => 'buyer-quotes-'.$record->getKey()),
                    ])
                    ->visible(fn (Request $record): bool => $record->buyerQuotes()
                        ->where('status', '!=', BuyerQuoteStatus::DRAFT)
                        ->exists()),
                Section::make('Request Items')
                    ->visible(fn (Request $record): bool => $record->items()->exists())
                    ->schema([
                        ViewEntry::make('items_table')
                            ->label('')
                            ->view('filament.buyer.components.request-items-table'),
                    ]),
                Section::make('Payments')
                    ->icon('heroicon-o-credit-card')
                    ->visible(fn (Request $record): bool => $this->hasPayableInvoice($record))
                    ->schema($this->paymentCardEntries()),
                Section::make(fn (Request $record): string => 'Activity · '.$this->activityCount($record))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        ViewEntry::make('activity_timeline')
                            ->label('')
                            ->state(fn (Request $record): array => app(PortalTimelineSource::class)->forParty(
                                $record,
                                TimelineParty::buyer(app(BuyerPortalContext::class)->companyId()),
                            ))
                            ->view('filament.buyer.components.request-activity-timeline'),
                    ]),
            ]);
    }

    /**
     * Whether this request has an open standard invoice the buyer can pay
     * against, so the Payments section only surfaces when there is something
     * to pay.
     */
    private function hasPayableInvoice(Request $record): bool
    {
        return BuyerInvoice::query()
            ->where('request_id', $record->getKey())
            ->where('type', InvoiceType::STANDARD)
            ->whereNot('status', InvoiceStatus::CANCELLED)
            ->exists();
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
        $presenter = app(BuyerRequestStagePresenter::class);

        return [
            Action::make('status')
                ->label(fn (Request $record): string => $presenter->label($record))
                ->badge()
                ->color(fn (Request $record): string => $presenter->color($presenter->effectiveStage($record)))
                ->disabled()
                ->extraAttributes(['class' => 'pointer-events-none']),
            // Registered so its modal renders and the per-installment "Record
            // payment" buttons can open it via mountAction(); the header button
            // itself is hidden — the payment table rows are the trigger.
            $this->recordPaymentAction()->extraAttributes(['class' => 'hidden']),
            EditAction::make()
                ->visible(fn (Request $record): bool => $record->isEditableByBuyer()),
        ];
    }

    private function activityCount(Request $record): int
    {
        return count(app(PortalTimelineSource::class)->forParty(
            $record,
            TimelineParty::buyer(app(BuyerPortalContext::class)->companyId()),
        ));
    }
}
