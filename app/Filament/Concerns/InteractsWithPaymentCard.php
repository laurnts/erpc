<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrepaymentType;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\BuyerQuote;
use App\Models\Currency;
use App\Models\PaymentDocumentApproval;
use App\Models\Request;
use App\Support\DocumentUpload;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Shared "Payments" card + Record-Payment modal used by both the staff request
 * page and the buyer portal request page.
 *
 * Both surfaces render the same payment terms table + paid/outstanding summary
 * and open the same modal via the per-installment "Record payment" buttons. The
 * only difference is the acting party: staff entries are trusted and recorded as
 * CONFIRMED immediately, while buyer entries are PENDING until staff confirm
 * them (and must carry a proof of transfer). Override {@see paymentActorType()}
 * on the buyer page to switch behaviour.
 */
trait InteractsWithPaymentCard
{
    /**
     * Temporary upload directory for payment proof files
     * (mirrors BuyerOrdersRelationManager so both surfaces stage into the same place).
     */
    private const string PAYMENT_PROOF_UPLOAD_DIRECTORY = 'uploads-tmp/buyer-payment-proof';

    /**
     * Which kind of account is recording the payment. Staff entries are trusted
     * (CONFIRMED); the buyer page overrides this to record PENDING entries.
     */
    protected function paymentActorType(): string
    {
        return 'staff';
    }

    /**
     * The two entries that make up the Payments card body: the per-installment
     * terms table and the paid / outstanding summary line. Shared by both the
     * staff and buyer request pages so the card renders identically.
     *
     * @return array<int, TextEntry>
     */
    protected function paymentCardEntries(): array
    {
        return [
            TextEntry::make('payment_terms_list')
                ->hiddenLabel()
                ->state(fn (Request $record): HtmlString => $this->getPaymentTermsList($record))
                ->placeholder('No payment terms')
                ->columnSpanFull(),
            TextEntry::make('payment_summary')
                ->hiddenLabel()
                ->state(fn (Request $record): HtmlString => $this->getPaymentSummary($record))
                ->columnSpanFull(),
        ];
    }

    /**
     * Format a currency value using the team's base currency.
     */
    private function formatCurrency(float $value): string
    {
        if ($value === 0.0) {
            return '-';
        }

        /** @var \App\Models\Team|null $team */
        $team = filament()->getTenant();

        // Try to get the base currency (active)
        $currency = $team?->getBaseCurrency();

        // If not found (e.g., inactive), try to get it by code anyway
        if ($currency === null && $team !== null) {
            $code = $team->getErpSettings()->default_currency;
            $currency = Currency::query()
                ->where('code', $code)
                ->first();
        }

        if ($currency === null) {
            return number_format($value, 2);
        }

        return $currency->format($value);
    }

    /**
     * Get effective buyer total (confirmed orders > draft orders > expected from quotes).
     */
    private function getEffectiveBuyerTotal(Request $record): float
    {
        if ($record->has_buyer_order_confirmed || $record->buyer_total > 0) {
            return $record->buyer_total;
        }

        return $record->expected_buyer_total;
    }

    /**
     * Get the primary buyer order for payment terms display.
     * Prefers confirmed orders, then most recent order.
     */
    private function getPrimaryBuyerOrder(Request $record): ?BuyerOrder
    {
        // Try to get confirmed order first
        $confirmedOrder = $record->buyerOrders()
            ->whereNotIn('status', [\App\Enums\OrderStatus::DRAFT, \App\Enums\OrderStatus::CANCELLED])
            ->with('buyerQuote')
            ->orderByDesc('confirmed_at')
            ->first();

        if ($confirmedOrder !== null) {
            return $confirmedOrder;
        }

        // Fall back to most recent order
        return $record->buyerOrders()
            ->with('buyerQuote')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get the active (non-cancelled) standard buyer invoice for the request, if any.
     * This is the invoice buyer payments are recorded against.
     */
    private function getActiveStandardInvoice(Request $record): ?BuyerInvoice
    {
        return BuyerInvoice::query()
            ->where('request_id', $record->getKey())
            ->where('type', InvoiceType::STANDARD)
            ->whereNot('status', InvoiceStatus::CANCELLED)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get the paid / total / outstanding summary line for the request's invoice.
     * Only confirmed payments count towards amount_paid (enforced by the invoice model).
     */
    private function getPaymentSummary(Request $record): HtmlString
    {
        $invoice = $this->getActiveStandardInvoice($record);

        if ($invoice === null) {
            return new HtmlString('<span class="text-gray-400">No invoice issued yet</span>');
        }

        $paid = (float) $invoice->amount_paid;
        // The invoice total may be unset (0) while the deal total lives on the
        // buyer order/quote — fall back to the effective buyer total so the
        // summary stays coherent with the terms table above it.
        $total = (float) $invoice->total;
        if ($total <= 0.0) {
            $total = $this->getEffectiveBuyerTotal($record);
        }
        $outstanding = max(0.0, $total - $paid);

        // Format through the base currency directly (formatCurrency renders 0 as
        // a dash, but "Paid Rp 0,-" reads better in this summary).
        $currency = $record->team?->getBaseCurrency();
        $fmt = fn (float $value): string => $currency !== null ? $currency->format($value) : number_format($value, 2);

        return new HtmlString(sprintf(
            '<div style="display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:0.5rem;font-size:0.875rem;">'
            .'<span style="color:#64748b;">Paid <span style="font-weight:600;color:#166534;">%s</span> of <span style="font-weight:600;color:#0f172a;">%s</span></span>'
            .'<span style="color:#64748b;">Outstanding <span style="font-weight:600;color:#92400e;">%s</span></span>'
            .'</div>',
            htmlspecialchars($fmt($paid)),
            htmlspecialchars($fmt($total)),
            htmlspecialchars($fmt($outstanding)),
        ));
    }

    /**
     * Render the team's bank / payment details (from ERP settings) as HTML.
     */
    private function getBankDetailsHtml(): HtmlString
    {
        /** @var \App\Models\Team|null $team */
        $team = filament()->getTenant();
        $settings = $team?->getErpSettings();

        if ($settings === null) {
            return new HtmlString('<span class="text-gray-400">No bank details configured</span>');
        }

        $rows = [];
        if ($settings->payment_bank_name !== '') {
            $rows[] = ['Bank', $settings->payment_bank_name];
        }
        if ($settings->payment_account_holder !== '') {
            $rows[] = ['Account holder', $settings->payment_account_holder];
        }
        if ($settings->payment_bank_account_number !== '') {
            $rows[] = ['Account number', $settings->payment_bank_account_number];
        }

        if ($rows === [] && $settings->payment_instructions === '') {
            return new HtmlString('<span class="text-gray-400">No bank details configured. Add them under Settings → General.</span>');
        }

        $lines = [];
        foreach ($rows as [$label, $value]) {
            $lines[] = sprintf(
                '<div style="display:flex;justify-content:space-between;gap:1rem;"><span style="color:#64748b;">%s</span><span style="font-weight:500;text-align:right;">%s</span></div>',
                htmlspecialchars($label),
                htmlspecialchars($value),
            );
        }

        if ($settings->payment_instructions !== '') {
            $lines[] = sprintf(
                '<p style="margin-top:0.25rem;font-size:0.75rem;color:#64748b;">%s</p>',
                htmlspecialchars($settings->payment_instructions),
            );
        }

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:0.25rem;font-size:0.875rem;">'.implode('', $lines).'</div>'
        );
    }

    /**
     * "Record Payment" action for the Payments card.
     *
     * Shows the outstanding balance and where-to-pay bank details, then records a
     * payment against the active standard invoice, optionally attaching a
     * proof-of-transfer file. Staff entries are trusted and recorded as CONFIRMED
     * immediately; buyer entries are PENDING (awaiting staff confirmation) and
     * must carry proof of transfer.
     */
    public function recordPaymentAction(): Action
    {
        $isBuyer = $this->paymentActorType() === 'buyer';

        return Action::make('recordPayment')
            ->label('Record Payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(function (): bool {
                /** @var Request $record */
                $record = $this->getRecord();
                $invoice = $this->getActiveStandardInvoice($record);

                return $invoice !== null && $invoice->status->canRecordPayment();
            })
            ->modalHeading('Record Payment')
            ->modalDescription($isBuyer
                ? 'Transfer to the bank account below, then submit your payment with the proof of transfer. Our team will confirm it.'
                : 'Transfer to the bank account below, then record the payment and attach the proof of transfer.')
            ->modalSubmitActionLabel('Record Payment')
            ->form(function (array $arguments) use ($isBuyer): array {
                /** @var Request $record */
                $record = $this->getRecord();
                $invoice = $this->getActiveStandardInvoice($record);

                // Default to the clicked installment's amount, not the whole balance.
                $installmentAmount = isset($arguments['amount'])
                    ? (float) $arguments['amount']
                    : (float) ($invoice?->amount_outstanding ?? 0);
                /** @var string|null $term */
                $term = $arguments['term'] ?? null;

                return [
                    Placeholder::make('installment')
                        ->label('Installment')
                        ->content($term.' — '.$this->formatCurrency($installmentAmount))
                        ->visible($term !== null),
                    Placeholder::make('amount_outstanding')
                        ->label('Total outstanding')
                        ->content($this->formatCurrency((float) ($invoice?->amount_outstanding ?? 0))),
                    Placeholder::make('bank_details')
                        ->label('Where to pay')
                        ->content($this->getBankDetailsHtml()),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->default((string) $installmentAmount),
                    Select::make('payment_method')
                        ->label('Payment Method')
                        ->options(PaymentMethod::class)
                        ->default(PaymentMethod::BANK_TRANSFER->value)
                        ->required(),
                    DatePicker::make('payment_date')
                        ->label('Payment Date')
                        ->default(now())
                        ->required(),
                    TextInput::make('reference_number')
                        ->label('Reference')
                        ->maxLength(255),
                    FileUpload::make('proof')
                        ->label('Proof of Transfer')
                        ->helperText(DocumentUpload::helperText(10240))
                        ->acceptedFileTypes(DocumentUpload::ACCEPTED_MIME_TYPES)
                        ->disk('local')
                        ->directory(self::PAYMENT_PROOF_UPLOAD_DIRECTORY)
                        ->visibility('private')
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->required($isBuyer)
                        ->maxSize(10240)
                        ->validationMessages([
                            'max' => DocumentUpload::maxSizeMessage(10240),
                        ]),
                    Textarea::make('note')
                        ->label('Note')
                        ->rows(2)
                        ->maxLength(1000),
                ];
            })
            ->action(function (array $data) use ($isBuyer): void {
                /** @var Request $record */
                $record = $this->getRecord();
                $invoice = $this->getActiveStandardInvoice($record);

                if ($invoice === null || ! $invoice->status->canRecordPayment()) {
                    Notification::make()
                        ->title('Cannot record payment')
                        ->body('There is no open invoice to record a payment against.')
                        ->danger()
                        ->send();

                    return;
                }

                /** @var int|null $actorId */
                $actorId = auth()->id();

                $payment = BuyerPayment::create([
                    'team_id' => $invoice->team_id,
                    'buyer_invoice_id' => $invoice->getKey(),
                    'payment_method' => $data['payment_method'],
                    'amount' => $data['amount'],
                    'payment_date' => $data['payment_date'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => $data['note'] ?? null,
                    'status' => $isBuyer ? PaymentStatus::Pending : PaymentStatus::Confirmed,
                    'submitted_actor_type' => $this->paymentActorType(),
                    'submitted_by_id' => $actorId,
                    'confirmed_by_id' => $isBuyer ? null : $actorId,
                    'confirmed_at' => $isBuyer ? null : now(),
                ]);

                $files = $data['proof'] ?? null;
                $files = is_array($files) ? $files : ($files !== null ? [$files] : []);

                if ($files !== []) {
                    app(AttachUploadedFiles::class)->execute(
                        $payment,
                        $files,
                        'payment_proof',
                        self::PAYMENT_PROOF_UPLOAD_DIRECTORY,
                    );
                }

                Notification::make()
                    ->title($isBuyer ? 'Payment submitted' : 'Payment recorded')
                    ->body($isBuyer
                        ? 'Your payment has been submitted and is awaiting confirmation.'
                        : 'The payment has been recorded and the invoice updated.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Render a status pill with reliable inline colors.
     *
     * Filament color names are mapped to explicit inline styles rather than
     * Tailwind utility classes (e.g. `bg-warning-100`), because those class
     * names are assembled at runtime and are not always emitted by the JIT
     * build — which left some badges rendering with no background.
     */
    private function statusBadge(string $label, string $color): string
    {
        $palette = match ($color) {
            'success' => 'background-color:#dcfce7;color:#166534;',
            'warning' => 'background-color:#fef3c7;color:#92400e;',
            'danger' => 'background-color:#fee2e2;color:#991b1b;',
            'info' => 'background-color:#dbeafe;color:#1e40af;',
            'primary' => 'background-color:#e0e7ff;color:#3730a3;',
            default => 'background-color:#f1f5f9;color:#334155;',
        };

        return sprintf(
            '<span style="%sdisplay:inline-flex;align-items:center;padding:0.125rem 0.5rem;border-radius:9999px;font-size:0.75rem;font-weight:500;line-height:1.25;white-space:nowrap;">%s</span>',
            $palette,
            htmlspecialchars($label)
        );
    }

    /**
     * Render a payment installment's status cell: a green "Paid" badge once
     * covered, otherwise a grey button that opens the Record Payment modal
     * (or a plain "Unpaid" badge when no invoice can take a payment yet).
     */
    private function paymentStatusCell(string $status, bool $canRecord, float $amount, string $term): string
    {
        if ($status === 'Paid') {
            return $this->statusBadge('Paid', 'success');
        }

        if (! $canRecord) {
            return $this->statusBadge('Unpaid', 'gray');
        }

        // The installment amount + label ride along as mountAction() arguments so
        // the modal records only this portion, not the whole outstanding balance.
        $termArg = htmlspecialchars($term, ENT_QUOTES);

        return sprintf(
            '<button type="button" wire:click="mountAction(\'recordPayment\', {amount: %d, term: \'%s\'})" '
            .'style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.25rem 0.625rem;'
            .'border-radius:0.5rem;font-size:0.75rem;font-weight:500;line-height:1.25;white-space:nowrap;'
            .'cursor:pointer;background-color:#f1f5f9;color:#334155;border:1px solid #e2e8f0;">Record payment</button>',
            (int) round($amount),
            $termArg,
        );
    }

    /**
     * Get payment terms list as HTML, including per-installment amounts.
     *
     * Amounts are derived from the accepted buyer quote applied to the effective
     * buyer total: an optional prepayment installment followed by each payment term.
     */
    private function getPaymentTermsList(Request $record): HtmlString
    {
        $buyerOrder = $this->getPrimaryBuyerOrder($record);

        if ($buyerOrder === null || $buyerOrder->buyerQuote === null) {
            return new HtmlString('<span class="text-gray-400">No payment terms</span>');
        }

        $quote = $buyerOrder->buyerQuote;
        $paymentTerms = $quote->paymentTerms;
        $buyerTotal = $this->getEffectiveBuyerTotal($record);
        $invoice = $this->getActiveStandardInvoice($record);
        $paid = (float) ($invoice?->amount_paid ?? 0);
        $canRecord = $invoice !== null && $invoice->status->canRecordPayment();

        $cell = 'padding:0.5rem 1rem 0.5rem 0;border-top:1px solid rgba(148,163,184,0.25);vertical-align:middle;';
        $amountCell = 'padding:0.5rem 1rem 0.5rem 0;border-top:1px solid rgba(148,163,184,0.25);vertical-align:middle;text-align:right;white-space:nowrap;';
        $lastCell = 'padding:0.5rem 0 0.5rem 0;border-top:1px solid rgba(148,163,184,0.25);vertical-align:middle;text-align:right;';

        $rows = [];

        $prepaymentAmount = $this->getPrepaymentAmount($quote, $buyerTotal);
        if ($prepaymentAmount > 0.0) {
            $status = $paid >= ($prepaymentAmount - 0.01) ? 'Paid' : 'Not Paid';
            $portion = $quote->prepayment_type === PrepaymentType::PERCENT
                ? $quote->prepayment_percent.'%'
                : '—';

            $rows[] = sprintf(
                '<tr><td style="%swhite-space:nowrap;font-weight:500;">Prepayment</td><td style="%s">%s</td><td style="%s">%s</td><td style="%s">%s</td></tr>',
                $cell,
                $cell,
                htmlspecialchars($portion),
                $amountCell,
                htmlspecialchars($this->formatCurrency($prepaymentAmount)),
                $lastCell,
                $this->paymentStatusCell($status, $canRecord, $prepaymentAmount, 'Prepayment'),
            );
        }

        foreach ($paymentTerms as $term) {
            $status = $this->getPaymentTermStatus($record, $term->due_days, $term->percentage);
            $amount = $buyerTotal * (float) $term->percentage / 100;

            $rows[] = sprintf(
                '<tr><td style="%swhite-space:nowrap;font-weight:500;">Settlement <span style="color:#94a3b8;font-weight:400;">· net %d days</span></td><td style="%s">%d%%</td><td style="%s">%s</td><td style="%s">%s</td></tr>',
                $cell,
                $term->due_days,
                $cell,
                $term->percentage,
                $amountCell,
                htmlspecialchars($this->formatCurrency($amount)),
                $lastCell,
                $this->paymentStatusCell($status, $canRecord, $amount, 'Settlement')
            );
        }

        if ($rows === []) {
            return new HtmlString('<span class="text-gray-400">No payment terms</span>');
        }

        $head = 'padding:0 1rem 0.5rem 0;font-weight:500;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;';
        $headAmount = 'padding:0 1rem 0.5rem 0;font-weight:500;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;text-align:right;';
        $headLast = 'padding:0 0 0.5rem 0;font-weight:500;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;text-align:right;';
        $html = sprintf(
            '<table style="width:100%%;border-collapse:collapse;font-size:0.875rem;"><thead><tr><th style="%stext-align:left;">Term</th><th style="%stext-align:left;">Portion</th><th style="%s">Amount</th><th style="%s">Status</th></tr></thead><tbody>%s</tbody></table>',
            $head,
            $head,
            $headAmount,
            $headLast,
            implode('', $rows)
        );

        return new HtmlString($html);
    }

    /**
     * Compute the prepayment installment amount from the quote and buyer total.
     */
    private function getPrepaymentAmount(BuyerQuote $quote, float $buyerTotal): float
    {
        if ($quote->prepayment_type === PrepaymentType::PERCENT) {
            return $buyerTotal * (float) $quote->prepayment_percent / 100;
        }

        if ($quote->prepayment_type === PrepaymentType::FIXED) {
            return (float) $quote->prepayment_amount;
        }

        return 0.0;
    }

    /**
     * Get payment term status (Paid/Not Paid) based on invoice payments or approved acceptance report.
     */
    private function getPaymentTermStatus(Request $record, int $dueDays, int $percentage): string
    {
        // If an acceptance report payment document for this term is approved, consider Paid
        $paymentTermsKey = "{$dueDays}-{$percentage}";
        $paymentMedia = $record->getMedia('completion_reports')
            ->filter(fn ($media): bool => (bool) $media->getCustomProperty('is_payment_document', false)
                && $media->getCustomProperty('payment_terms') === $paymentTermsKey);
        $paymentMediaIds = $paymentMedia->pluck('id')->toArray();
        if ($paymentMediaIds !== [] && $record->team_id !== null) {
            $hasApprovedDoc = PaymentDocumentApproval::query()
                ->whereIn('media_id', $paymentMediaIds)
                ->where('team_id', $record->team_id)
                ->exists();
            if ($hasApprovedDoc) {
                return 'Paid';
            }
        }

        // Get invoices for this request with matching net_days
        $invoices = BuyerInvoice::query()
            ->where('request_id', $record->getKey())
            ->where('net_days', $dueDays)
            ->whereNotIn('status', [InvoiceStatus::CANCELLED, InvoiceStatus::DRAFT])
            ->get();

        if ($invoices->isEmpty()) {
            return 'Not Paid';
        }

        // Calculate expected payment amount based on percentage
        $buyerTotal = $this->getEffectiveBuyerTotal($record);
        $expectedAmount = ($buyerTotal * $percentage) / 100;

        // Sum up paid amounts from invoices
        $paidAmount = 0.0;
        foreach ($invoices as $invoice) {
            $paidAmount += (float) $invoice->amount_paid;
        }

        // Consider paid if paid amount equals or exceeds expected amount (with small tolerance)
        if ($paidAmount >= ($expectedAmount - 0.01)) {
            return 'Paid';
        }

        return 'Not Paid';
    }
}
