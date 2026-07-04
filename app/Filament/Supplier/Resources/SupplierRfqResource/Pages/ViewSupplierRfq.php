<?php

declare(strict_types=1);

namespace App\Filament\Supplier\Resources\SupplierRfqResource\Pages;

use App\Actions\SupplierPortal\DeclineSupplierRfq;
use App\Actions\SupplierPortal\SubmitSupplierRfqResponse;
use App\Filament\Supplier\Resources\SupplierRfqResource;
use App\Filament\Supplier\Resources\SupplierRfqResource\Schemas\SupplierRfqSubmissionForm;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Services\SupplierPortal\SupplierRfqStatusPresenter;
use App\Support\SafeCast;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

final class ViewSupplierRfq extends ViewRecord
{
    protected static string $resource = SupplierRfqResource::class;

    public function infolist(Schema $schema): Schema
    {
        $presenter = app(SupplierRfqStatusPresenter::class);

        return $schema
            ->components([
                Section::make('Quote Request')
                    ->schema([
                        TextEntry::make('quote_number')
                            ->label('Reference'),
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
                    ->columns(2),
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit Quote')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => $this->supplierUser()?->can('submit', $this->quoteRecord()) === true)
                ->form(fn (): array => SupplierRfqSubmissionForm::components($this->quoteRecord()))
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

                    app(SubmitSupplierRfqResponse::class)->execute(
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

                    $this->redirect(SupplierRfqResource::getUrl('view', ['record' => $quote]));
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

                    app(DeclineSupplierRfq::class)->execute($quote, $user);

                    Notification::make()
                        ->title('Quote request declined')
                        ->success()
                        ->send();

                    $this->redirect(SupplierRfqResource::getUrl('index'));
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
