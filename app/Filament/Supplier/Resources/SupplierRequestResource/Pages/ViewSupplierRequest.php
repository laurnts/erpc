<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierRequestResource\Pages;

use App\Actions\SupplierPortal\DeclineSupplierRequest;
use App\Actions\SupplierPortal\SubmitSupplierRequestResponse;
use App\Filament\Supplier\Resources\SupplierRequestResource;
use App\Filament\Supplier\Resources\SupplierRequestResource\Schemas\SupplierRequestSubmissionForm;
use App\Models\Request;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Services\Portal\SupplierPortalContext;
use App\Services\SupplierPortal\SupplierRequestStatusPresenter;
use App\Services\Timeline\PortalTimelineSource;
use App\Services\Timeline\TimelineParty;
use App\Support\SafeCast;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

final class ViewSupplierRequest extends ViewRecord
{
    protected static string $resource = SupplierRequestResource::class;

    /**
     * Re-render the page (and its activity infolist) after the pinned composer
     * posts a note so the supplier's new note surfaces immediately.
     */
    #[On('note-posted')]
    public function refreshAfterNote(): void {}

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function infolist(Schema $schema): Schema
    {
        $presenter = app(SupplierRequestStatusPresenter::class);

        return $schema
            ->columns(1)
            ->components([
                // Request header — same structure as the staff request view,
                // restricted to this supplier's own quotation data.
                Section::make()
                    ->schema([
                        TextEntry::make('quote_number')
                            ->label('Reference')
                            ->weight('bold')
                            ->size('md')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (SupplierQuote $record): string => $presenter->label($record))
                            ->color(fn (SupplierQuote $record): string => $presenter->color($record)),
                        TextEntry::make('sent_to_supplier_at')
                            ->label('Received')
                            ->dateTime(),
                        TextEntry::make('valid_until')
                            ->label('Valid Until')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('currency.code')
                            ->label('Currency')
                            ->visible(fn (SupplierQuote $record): bool => $record->submitted_at !== null),
                        TextEntry::make('total')
                            ->label('Your Total')
                            ->formatStateUsing(fn (SupplierQuote $record): string => $record->formatted_total)
                            ->visible(fn (SupplierQuote $record): bool => $record->submitted_at !== null),
                        TextEntry::make('submitted_at')
                            ->label('Submitted At')
                            ->dateTime()
                            ->visible(fn (SupplierQuote $record): bool => $record->submitted_at !== null),
                        TextEntry::make('notes')
                            ->label('Your Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Requested Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Description')
                                    ->formatStateUsing(fn (SupplierQuoteItem $record, string $state): string => $record->requestItem?->parent_id !== null
                                        ? '└─ '.$state
                                        : $state),
                                TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->numeric(),
                                TextEntry::make('unit_label')
                                    ->label('Unit'),
                                TextEntry::make('notes')
                                    ->label('Notes')
                                    ->placeholder('—'),
                                TextEntry::make('unit_price')
                                    ->label('Your Unit Price')
                                    ->formatStateUsing(fn (SupplierQuoteItem $record): string => $record->formatted_unit_price)
                                    ->visible(fn (): bool => $this->quoteRecord()->submitted_at !== null),
                                TextEntry::make('line_total')
                                    ->label('Line Total')
                                    ->formatStateUsing(fn (SupplierQuoteItem $record): string => $record->formatted_line_total)
                                    ->visible(fn (): bool => $this->quoteRecord()->submitted_at !== null),
                                TextEntry::make('is_selected')
                                    ->label('Result')
                                    ->badge()
                                    ->getStateUsing(fn (SupplierQuoteItem $record): ?string => $record->requestItem?->parent_id !== null
                                        ? null
                                        : ($record->is_selected ? 'Won' : 'Not selected'))
                                    ->color(fn (SupplierQuoteItem $record): string => $record->is_selected ? 'success' : 'gray')
                                    ->placeholder('—')
                                    ->visible(fn (): bool => $this->quoteRecord()->outcomes_announced_at !== null),
                            ])
                            ->columns($this->quoteRecord()->outcomes_announced_at !== null ? 7 : 6),
                    ]),
                Section::make('Activities')
                    ->description('Your interactions on this quotation, most recent first.')
                    ->schema([
                        ViewEntry::make('activity_timeline')
                            ->label('')
                            ->state(fn (): array => [
                                'entries' => $this->activityTimeline(),
                                'request' => $this->parentRequest(),
                            ])
                            ->view('filament.supplier.components.request-activity-timeline'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The supplier-scoped, redacted portal timeline for the request behind
     * this quotation. The audience helper keys every subject rule to the
     * authenticated supplier's company id, so only this supplier's own
     * documents on the (possibly shared) request are ever enumerated.
     *
     * @return list<\App\Data\TimelineEntry>
     */
    private function activityTimeline(): array
    {
        $request = $this->parentRequest();

        if ($request === null) {
            return [];
        }

        $companyId = app(SupplierPortalContext::class)->companyId();

        return app(PortalTimelineSource::class)
            ->forParty($request, TimelineParty::supplier($companyId));
    }

    /**
     * Resolve the parent Request from the quotation without trusting the
     * narrow portal projection (which deliberately omits request_id): the
     * request is reached through this quote's own key via the relationship.
     */
    private function parentRequest(): ?Request
    {
        return Request::query()
            ->whereHas('supplierQuotes', fn (Builder $query): Builder => $query->whereKey($this->quoteRecord()->getKey()))
            ->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit Quote')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => $this->supplierUser()?->can('submit', $this->quoteRecord()) === true)
                ->form(fn (): array => SupplierRequestSubmissionForm::components($this->quoteRecord()))
                ->modalWidth('2xl')
                ->action(function (array $data): void {
                    $user = $this->supplierUser();
                    // Re-fetch the full row: the portal projection is narrow,
                    // and the write path must operate on complete attributes.
                    $quote = $this->freshQuote();

                    abort_unless($user !== null && $user->can('submit', $quote), 403);

                    /** @var array<int|string, mixed> $itemPrices */
                    $itemPrices = is_array($data['item_prices'] ?? null) ? $data['item_prices'] : [];

                    $validUntil = isset($data['valid_until']) && is_string($data['valid_until']) && $data['valid_until'] !== ''
                        ? Carbon::parse($data['valid_until'])
                        : null;

                    $notes = isset($data['notes']) && is_string($data['notes']) && trim($data['notes']) !== ''
                        ? $data['notes']
                        : null;

                    app(SubmitSupplierRequestResponse::class)->execute(
                        quote: $quote,
                        user: $user,
                        itemPrices: $itemPrices,
                        currencyId: SafeCast::toInt($data['currency_id'] ?? 0),
                        validUntil: $validUntil,
                        notes: $notes,
                        quotationFiles: $data['quotation_file'] ?? null,
                    );

                    Notification::make()
                        ->title('Quote submitted')
                        ->body('Thank you — your quotation has been submitted for review.')
                        ->success()
                        ->send();

                    $this->redirect(SupplierRequestResource::getUrl('view', ['record' => $quote]));
                }),
            Action::make('decline')
                ->label('Decline')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Decline this quote request?')
                ->modalDescription('We will inform the purchasing team that you will not be quoting this request.')
                ->visible(fn (): bool => $this->supplierUser()?->can('decline', $this->quoteRecord()) === true)
                ->action(function (): void {
                    $user = $this->supplierUser();
                    $quote = $this->freshQuote();

                    abort_unless($user !== null && $user->can('decline', $quote), 403);

                    app(DeclineSupplierRequest::class)->execute($quote, $user);

                    Notification::make()
                        ->title('Quote request declined')
                        ->success()
                        ->send();

                    $this->redirect(SupplierRequestResource::getUrl('index'));
                }),
        ];
    }

    private function supplierUser(): ?User
    {
        $user = auth()->guard('supplier')->user();

        return $user instanceof User ? $user : null;
    }

    private function quoteRecord(): SupplierQuote
    {
        /** @var SupplierQuote $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * Full-attribute reload of the page record: the portal projection is
     * deliberately narrow, but write paths must see complete rows.
     */
    private function freshQuote(): SupplierQuote
    {
        return SupplierQuote::query()
            ->whereKey($this->quoteRecord()->getKey())
            ->firstOrFail();
    }
}
