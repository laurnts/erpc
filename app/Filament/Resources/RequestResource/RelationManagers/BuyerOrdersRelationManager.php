<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequestResource\RelationManagers;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\BuyerQuoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStage;
use App\Filament\Actions\DownloadPdfAction;
use App\Filament\Resources\RequestResource\RelationManagers\Concerns\HasRequestStageTab;
use App\Mail\Erp\BuyerOrderToBuyerMail;
use App\Mail\Erp\InvoiceToBuyerMail;
use App\Models\BuyerInvoice;
use App\Models\BuyerOrder;
use App\Models\BuyerPayment;
use App\Models\BuyerQuote;
use App\Models\Request;
use App\Services\Email\EmailTemplateService;
use App\Support\DocumentUpload;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

final class BuyerOrdersRelationManager extends RelationManager
{
    use HasRequestStageTab;

    protected static string $relationship = 'buyerOrders';

    protected static ?string $title = 'Invoices';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-currency-dollar';

    /**
     * Temp upload directory for staff-submitted payment proof files. The
     * FileUpload component and the AttachUploadedFiles call site must
     * reference the same value — drift between them silently drops files.
     */
    private const string PAYMENT_PROOF_UPLOAD_DIRECTORY = 'uploads-tmp/buyer-payment-proof';

    protected static function getAssociatedStage(): RequestStage
    {
        return RequestStage::AWAITING_BUYER_CONFIRMATION;
    }

    protected static function getBaseTabTitle(): string
    {
        return 'Invoices';
    }

    /**
     * Get the form schema for buyer orders.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public function getFormSchema(): array
    {
        return [
            Section::make('Order Details')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('order_number_display')
                                ->label('Order Number')
                                ->content(fn (?BuyerOrder $record): string => $record->order_number ?? 'Auto-generated'),
                            Placeholder::make('status_display')
                                ->label('Status')
                                ->content(fn (?BuyerOrder $record): string => $record?->status->getLabel() ?? 'Draft'),
                            Placeholder::make('buyer_quote_display')
                                ->label('Source Quote')
                                ->content(fn (?BuyerOrder $record): string => $record->buyerQuote->quote_number ?? 'None'),
                            Placeholder::make('currency_display')
                                ->label('Currency')
                                ->content(fn (?BuyerOrder $record): string => $record?->buyerQuote?->currency?->code ?? '-'),
                        ]),
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('ordered_at_display')
                                ->label('Ordered At')
                                ->content(fn (?BuyerOrder $record): string => $record->ordered_at?->format('Y-m-d H:i') ?? '-'),
                            Placeholder::make('confirmed_at_display')
                                ->label('Confirmed At')
                                ->content(fn (?BuyerOrder $record): string => $record->confirmed_at?->format('Y-m-d H:i') ?? '-'),
                            Placeholder::make('buyer_display')
                                ->label('Buyer')
                                ->content(fn (?BuyerOrder $record): string => $record->buyer->name ?? '-'),
                            Placeholder::make('buyer_reference_display')
                                ->label('Buyer Reference')
                                ->content(fn (?BuyerOrder $record): string => $record->buyer_reference ?? '-'),
                        ]),
                ]),

            Section::make('Payment Terms')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Placeholder::make('prepayment_percent_display')
                                ->label('Prepayment %')
                                ->content(fn (?BuyerOrder $record): string => ($record->prepayment_percent ?? 0).'%'),
                            Placeholder::make('payment_terms_days_display')
                                ->label('Payment Terms (Days)')
                                ->content(fn (?BuyerOrder $record): string => (string) ($record->payment_terms_days ?? 30)),
                            Placeholder::make('payment_terms_text_display')
                                ->label('Payment Terms Description')
                                ->content(fn (?BuyerOrder $record): string => $record->payment_terms_text ?? '-'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Line Items')
                ->schema([
                    Repeater::make('items')
                        ->relationship(
                            modifyQueryUsing: fn ($query) => $query->with(['unitOfMeasure', 'requestItem.supplier', 'buyerQuoteItem.supplierQuoteItem.supplierQuote.supplier'])
                        )
                        ->schema([
                            Grid::make(12)
                                ->schema([
                                    TextInput::make('description')
                                        ->columnSpan(5)
                                        ->disabled(),
                                    TextInput::make('quantity')
                                        ->columnSpan(2)
                                        ->disabled(),
                                    Placeholder::make('unit_display')
                                        ->label('Unit')
                                        ->content(fn ($record) => $record?->unit_label ?? '—')
                                        ->columnSpan(1),
                                    TextInput::make('unit_price')
                                        ->label('Price')
                                        ->columnSpan(2)
                                        ->disabled()
                                        ->helperText(function ($record): ?string {
                                            if ($record === null) {
                                                return null;
                                            }

                                            // Try to get supplier from request item first
                                            $supplier = $record->requestItem?->supplier;

                                            // Fallback to supplier from quote chain
                                            if ($supplier === null && $record->buyerQuoteItem?->supplierQuoteItem?->supplierQuote?->supplier !== null) {
                                                $supplier = $record->buyerQuoteItem->supplierQuoteItem->supplierQuote->supplier;
                                            }

                                            return $supplier !== null ? "From: {$supplier->name}" : null;
                                        }),
                                    TextInput::make('line_total')
                                        ->label('Total')
                                        ->columnSpan(2)
                                        ->disabled(),
                                ]),
                        ])
                        ->columns(1)
                        ->deletable(false)
                        ->addable(false)
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                ]),

            Section::make('Summary')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Placeholder::make('subtotal_display')
                                ->label('Subtotal')
                                ->content(fn (?BuyerOrder $record): string => $record instanceof BuyerOrder
                                    ? ($record->buyerQuote?->currency?->formatNumber((float) $record->subtotal) ?? number_format((float) $record->subtotal, 2))
                                    : '0,-'),
                            Placeholder::make('tax_total_display')
                                ->label('Tax Total')
                                ->content(fn (?BuyerOrder $record): string => $record instanceof BuyerOrder
                                    ? ($record->buyerQuote?->currency?->formatNumber((float) $record->tax_total) ?? number_format((float) $record->tax_total, 2))
                                    : '0,-'),
                            Placeholder::make('total_display')
                                ->label('Total')
                                ->content(fn (?BuyerOrder $record): string => $record instanceof BuyerOrder
                                    ? ($record->buyerQuote?->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
                                    : '0,-'),
                            Placeholder::make('total_base_display')
                                ->label('Total (Base)')
                                ->content(function (?BuyerOrder $record): string {
                                    if (! $record instanceof BuyerOrder) {
                                        return '0,-';
                                    }
                                    /** @var \App\Models\Team|null $team */
                                    $team = \Filament\Facades\Filament::getTenant();
                                    $baseCurrency = $team?->getBaseCurrency();

                                    return $baseCurrency !== null
                                        ? $baseCurrency->format((float) $record->total)
                                        : number_format((float) $record->total, 2);
                                }),
                        ]),
                ])
                ->collapsible(),

            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->disabled(),
                    Textarea::make('internal_notes')
                        ->label('Internal Notes')
                        ->rows(2)
                        ->disabled(),
                ])
                ->collapsed(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components($this->getFormSchema());
    }

    private function activeInvoiceFor(BuyerOrder $order): ?BuyerInvoice
    {
        if ($order->relationLoaded('buyerInvoices')) {
            return $order->buyerInvoices
                ->filter(fn (BuyerInvoice $invoice): bool => $invoice->type === InvoiceType::STANDARD
                    && $invoice->status !== InvoiceStatus::CANCELLED)
                ->sortByDesc('id')
                ->first();
        }

        return BuyerInvoice::query()
            ->where('buyer_order_id', $order->getKey())
            ->where('type', InvoiceType::STANDARD)
            ->whereNot('status', InvoiceStatus::CANCELLED)
            ->latest('id')
            ->first();
    }

    private function sendInvoiceEmailToBuyer(
        BuyerOrder $record,
        BuyerInvoice $invoice,
        string $successTitle,
        string $successBody,
        string $failureTitle,
        string $failureBodyPrefix,
    ): void {
        $buyerEmail = $record->buyer->email ?? null;
        $buyerName = $record->buyer->name ?? 'Buyer';

        if (empty($buyerEmail)) {
            Notification::make()
                ->title('Cannot send email')
                ->body("The buyer ({$buyerName}) does not have an email address configured.")
                ->warning()
                ->send();

            return;
        }

        try {
            app(EmailTemplateService::class)->sendWithTeamSettings(
                $record->team,
                new InvoiceToBuyerMail($invoice),
                $buyerEmail,
            );

            Notification::make()
                ->title($successTitle)
                ->body($successBody)
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Failed to send buyer invoice email', [
                'invoice_id' => $invoice->id,
                'buyer_order_id' => $record->id,
                'buyer_email' => $buyerEmail,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title($failureTitle)
                ->body($failureBodyPrefix.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        /** @var Request $request */
        $request = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('order_number')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with('buyerInvoices'))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')

                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')

                    ->sortable(),
                TextColumn::make('buyerQuote.quote_number')
                    ->label('Source Quote')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('invoice_status')
                    ->label('Invoice')
                    ->badge()
                    ->placeholder('Not issued')
                    ->getStateUsing(fn (BuyerOrder $record): ?InvoiceStatus => $record->buyerInvoices
                        ->filter(fn (BuyerInvoice $invoice): bool => $invoice->type === InvoiceType::STANDARD
                            && $invoice->status !== InvoiceStatus::CANCELLED)
                        ->sortByDesc('id')
                        ->first()?->status),
                TextColumn::make('invoice_due')
                    ->label('Due')
                    ->date()
                    ->placeholder('—')
                    ->getStateUsing(fn (BuyerOrder $record): ?\Illuminate\Support\Carbon => $record->buyerInvoices
                        ->filter(fn (BuyerInvoice $invoice): bool => $invoice->type === InvoiceType::STANDARD
                            && $invoice->status !== InvoiceStatus::CANCELLED)
                        ->sortByDesc('id')
                        ->first()?->due_at),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->formatStateUsing(fn (BuyerOrder $record): string => $record->buyerQuote?->currency?->formatNumber((float) $record->subtotal) ?? number_format((float) $record->subtotal, 2))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (BuyerOrder $record): string => $record->buyerQuote?->currency?->format((float) $record->total) ?? number_format((float) $record->total, 2))
                    ->sortable(),
                TextColumn::make('payment_terms_days')
                    ->label('Terms')
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                TextColumn::make('ordered_at')
                    ->label('Ordered')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('confirmed_at')
                    ->label('Confirmed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class),
            ])
            ->headerActions([
                Action::make('createFromQuote')
                    ->label('Create from Quote')
                    ->icon('heroicon-o-document-plus')
                    ->size(Size::Small)
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Select::make('buyer_quote_id')
                            ->label('Select Accepted Quote')
                            ->options(fn (): array => BuyerQuote::query()
                                ->where('request_id', $request->getKey())
                                ->where('status', BuyerQuoteStatus::ACCEPTED)
                                ->get()
                                ->mapWithKeys(fn (BuyerQuote $quote): array => [
                                    $quote->getKey() => sprintf(
                                        '%s (v%d) - %s',
                                        $quote->quote_number,
                                        $quote->version,
                                        number_format((float) $quote->total, 2)
                                    ),
                                ])
                                ->all())
                            ->default(function () use ($request): ?int {
                                $acceptedQuote = BuyerQuote::query()
                                    ->where('request_id', $request->getKey())
                                    ->where('status', BuyerQuoteStatus::ACCEPTED)
                                    ->first();

                                return $acceptedQuote?->getKey();
                            })
                            ->selectablePlaceholder(false)
                            ->required()

                            ->helperText('Only accepted quotes can be converted to orders.'),
                    ])
                    ->action(function (array $data) use ($request): void {
                        /** @var BuyerQuote $buyerQuote */
                        $buyerQuote = BuyerQuote::findOrFail($data['buyer_quote_id']);

                        // Check if quote belongs to this request
                        if ($buyerQuote->request_id !== $request->getKey()) {
                            Notification::make()
                                ->title('Error')
                                ->body('Quote does not belong to this request.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Create order from quote
                        $order = BuyerOrder::createFromQuote($buyerQuote);

                        // Show credit limit warning if applicable
                        $warning = $order->getCreditLimitWarning();
                        if ($warning !== null) {
                            Notification::make()
                                ->title('Credit Limit Warning')
                                ->body($warning)
                                ->warning()
                                ->persistent()
                                ->send();
                        }

                        Notification::make()
                            ->title('Order created')
                            ->body(sprintf('Order %s created from quote %s.', $order->order_number, $buyerQuote->quote_number))
                            ->success()
                            ->send();
                    })
                    ->visible(
                        // Only show if there are accepted quotes

                        fn (): bool => BuyerQuote::query()
                            ->where('request_id', $request->getKey())
                            ->where('status', BuyerQuoteStatus::ACCEPTED)
                            ->exists()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalWidth('7xl')
                        ->form(fn (): array => $this->getFormSchema()),
                    DownloadPdfAction::make()
                        ->label('PDF'),
                    Action::make('send')
                        ->label('Send')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->authorize(fn (?BuyerOrder $record): bool => $record !== null && auth()->user()?->can('send', $record) === true)
                        ->visible(fn (?BuyerOrder $record): bool => $record !== null && $record->status === OrderStatus::DRAFT)
                        ->requiresConfirmation()
                        ->modalHeading('Send order email to buyer?')
                        ->modalDescription(function (BuyerOrder $record): string {
                            $buyerEmail = $record->buyer->email ?? null;
                            $buyerName = $record->buyer->name ?? 'Unknown';
                            $description = 'This will mark the order as sent and send the order email to the buyer.';

                            if (empty($buyerEmail)) {
                                $description .= "\n\n⚠️ **Warning:** The buyer ({$buyerName}) does not have an email address configured. The order will be marked as sent, but no email will be sent.";
                            } else {
                                $description .= "\n\n📧 Email will be sent to: {$buyerEmail}";
                            }

                            return $description;
                        })
                        ->action(function (BuyerOrder $record): void {
                            // Send email to buyer
                            $buyerEmail = $record->buyer->email ?? null;
                            $buyerName = $record->buyer->name ?? 'Buyer';

                            // Mark as sent first
                            $record->markAsSent();

                            if (empty($buyerEmail)) {
                                Notification::make()
                                    ->title('Order marked as sent')
                                    ->body("Order has been marked as sent, but no email was sent because the buyer ({$buyerName}) does not have an email address configured.")
                                    ->warning()
                                    ->send();

                                return;
                            }

                            try {
                                $emailService = app(EmailTemplateService::class);
                                $settings = $record->team->getErpSettings();
                                $emailService->sendWithTeamSettings(
                                    $record->team,
                                    new BuyerOrderToBuyerMail($record),
                                    $buyerEmail,
                                    $settings->email_template_buyer_order, // Old system fallback
                                    $settings->email_template_buyer_order_id ?? null, // New system
                                    \App\Models\EmailTemplate::TYPE_BUYER_ORDER
                                );

                                Notification::make()
                                    ->title('Order sent')
                                    ->body("Order has been sent successfully to {$buyerEmail}.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error('Failed to send buyer order email', [
                                    'order_id' => $record->id,
                                    'buyer_email' => $buyerEmail,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Failed to send email')
                                    ->body("Order has been marked as sent, but the email could not be sent to {$buyerEmail}. Error: ".$e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('resend')
                        ->label('Resend')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->authorize(fn (?BuyerOrder $record): bool => $record !== null && auth()->user()?->can('send', $record) === true)
                        ->visible(fn (?BuyerOrder $record): bool => $record !== null && $record->status === OrderStatus::SENT)
                        ->requiresConfirmation()
                        ->modalHeading('Resend order email?')
                        ->modalDescription(function (BuyerOrder $record): string {
                            $buyerEmail = $record->buyer->email ?? null;
                            $buyerName = $record->buyer->name ?? 'Unknown';
                            $description = 'This will resend the order email to the buyer without changing the order status.';

                            if (empty($buyerEmail)) {
                                $description .= "\n\n⚠️ **Warning:** The buyer ({$buyerName}) does not have an email address configured. No email will be sent.";
                            } else {
                                $description .= "\n\n📧 Email will be sent to: {$buyerEmail}";
                            }

                            return $description;
                        })
                        ->action(function (BuyerOrder $record): void {
                            // Resend email to buyer (without changing status)
                            $buyerEmail = $record->buyer->email ?? null;
                            $buyerName = $record->buyer->name ?? 'Buyer';

                            if (empty($buyerEmail)) {
                                Notification::make()
                                    ->title('Cannot resend email')
                                    ->body("The buyer ({$buyerName}) does not have an email address configured.")
                                    ->warning()
                                    ->send();

                                return;
                            }

                            try {
                                $emailService = app(EmailTemplateService::class);
                                $settings = $record->team->getErpSettings();
                                $emailService->sendWithTeamSettings(
                                    $record->team,
                                    new BuyerOrderToBuyerMail($record),
                                    $buyerEmail,
                                    $settings->email_template_buyer_order, // Old system fallback
                                    $settings->email_template_buyer_order_id ?? null, // New system
                                    \App\Models\EmailTemplate::TYPE_BUYER_ORDER
                                );

                                Notification::make()
                                    ->title('Email resent')
                                    ->body("Order email has been resent successfully to {$buyerEmail}.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Log::error('Failed to resend buyer order email', [
                                    'order_id' => $record->id,
                                    'buyer_email' => $buyerEmail,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);

                                Notification::make()
                                    ->title('Failed to resend email')
                                    ->body("The email could not be sent to {$buyerEmail}. Error: ".$e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('issueInvoice')
                        ->label('Issue Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->visible(fn (?BuyerOrder $record): bool => $record !== null
                            && $record->status === OrderStatus::CONFIRMED
                            && ! BuyerInvoice::query()
                                ->where('buyer_order_id', $record->getKey())
                                ->where('type', InvoiceType::STANDARD)
                                ->whereNot('status', InvoiceStatus::CANCELLED)
                                ->exists())
                        ->requiresConfirmation()
                        ->modalHeading('Issue invoice to buyer?')
                        ->modalDescription(function (BuyerOrder $record): string {
                            $buyerEmail = $record->buyer->email ?? null;
                            $description = 'This will create an invoice from the order and email it to the buyer.';

                            if (empty($buyerEmail)) {
                                $description .= "\n\n⚠️ **Warning:** The buyer has no email address configured. The invoice will be created, but no email will be sent.";
                            } else {
                                $description .= "\n\n📧 Invoice will be sent to: {$buyerEmail}";
                            }

                            return $description;
                        })
                        ->action(function (BuyerOrder $record): void {
                            try {
                                $invoice = BuyerInvoice::issueFromOrder($record);
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Could not issue invoice')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $buyerEmail = $record->buyer->email ?? null;

                            if (empty($buyerEmail)) {
                                Notification::make()
                                    ->title('Invoice issued')
                                    ->body('Invoice has been created, but no email was sent because the buyer has no email address.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $this->sendInvoiceEmailToBuyer(
                                $record,
                                $invoice,
                                'Invoice issued',
                                "Invoice has been issued and sent to {$buyerEmail}.",
                                'Invoice issued (email failed)',
                                "Invoice was created, but the email to {$buyerEmail} could not be sent. Error: ",
                            );
                        }),
                    Action::make('resendInvoice')
                        ->label('Resend Invoice')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->authorize(fn (?BuyerOrder $record): bool => $record !== null && auth()->user()?->can('send', $record) === true)
                        ->visible(function (?BuyerOrder $record): bool {
                            if ($record === null) {
                                return false;
                            }

                            $invoice = $this->activeInvoiceFor($record);

                            return $invoice !== null && $invoice->status !== InvoiceStatus::DRAFT;
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Resend invoice email?')
                        ->modalDescription(function (BuyerOrder $record): string {
                            $invoice = $this->activeInvoiceFor($record);
                            $buyerEmail = $record->buyer->email ?? null;
                            $buyerName = $record->buyer->name ?? 'Unknown';
                            $description = 'This will resend the invoice email to the buyer without changing the invoice status.';

                            if ($invoice !== null) {
                                $description .= "\n\nInvoice: {$invoice->invoice_number}";
                            }

                            if (empty($buyerEmail)) {
                                $description .= "\n\n⚠️ **Warning:** The buyer ({$buyerName}) does not have an email address configured. No email will be sent.";
                            } else {
                                $description .= "\n\n📧 Email will be sent to: {$buyerEmail}";
                            }

                            return $description;
                        })
                        ->action(function (BuyerOrder $record): void {
                            $invoice = $this->activeInvoiceFor($record);

                            if ($invoice === null) {
                                Notification::make()
                                    ->title('Cannot resend invoice')
                                    ->body('No active invoice was found for this order.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $buyerEmail = $record->buyer->email ?? null;

                            $this->sendInvoiceEmailToBuyer(
                                $record,
                                $invoice,
                                'Email resent',
                                "Invoice email has been resent successfully to {$buyerEmail}.",
                                'Failed to resend email',
                                "The invoice email could not be sent to {$buyerEmail}. Error: ",
                            );
                        }),
                    Action::make('recordPayment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(function (?BuyerOrder $record): bool {
                            if ($record === null) {
                                return false;
                            }
                            $invoice = $this->activeInvoiceFor($record);

                            return $invoice !== null && $invoice->status->canRecordPayment();
                        })
                        ->form(fn (BuyerOrder $record): array => [
                            TextInput::make('amount')
                                ->label('Amount')
                                ->numeric()
                                ->required()
                                ->default(fn (): string => (string) ($this->activeInvoiceFor($record)?->amount_outstanding ?? 0)),
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
                                ->maxSize(10240)
                                ->validationMessages([
                                    'max' => DocumentUpload::maxSizeMessage(10240),
                                ]),
                            Textarea::make('note')
                                ->label('Note')
                                ->rows(2)
                                ->maxLength(1000),
                        ])
                        ->action(function (BuyerOrder $record, array $data): void {
                            $invoice = $this->activeInvoiceFor($record);

                            if ($invoice === null || ! $invoice->status->canRecordPayment()) {
                                Notification::make()
                                    ->title('Cannot record payment')
                                    ->body('There is no open invoice to record a payment against.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            /** @var int|null $staffId */
                            $staffId = auth()->id();

                            $payment = BuyerPayment::create([
                                'team_id' => $invoice->team_id,
                                'buyer_invoice_id' => $invoice->getKey(),
                                'payment_method' => $data['payment_method'],
                                'amount' => $data['amount'],
                                'payment_date' => $data['payment_date'],
                                'reference_number' => $data['reference_number'] ?? null,
                                'notes' => $data['note'] ?? null,
                                'status' => PaymentStatus::Confirmed,
                                'submitted_actor_type' => 'staff',
                                'submitted_by_id' => $staffId,
                                'confirmed_by_id' => $staffId,
                                'confirmed_at' => now(),
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
                                ->title('Payment recorded')
                                ->body('The payment has been recorded and the invoice updated.')
                                ->success()
                                ->send();
                        }),
                    Action::make('confirmPayment')
                        ->label('Confirm Payment')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(function (?BuyerOrder $record): bool {
                            if ($record === null) {
                                return false;
                            }
                            $invoice = $this->activeInvoiceFor($record);

                            return $invoice !== null && $invoice->payments()
                                ->where('status', PaymentStatus::Pending->value)
                                ->exists();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Confirm buyer payment?')
                        ->modalDescription('This confirms the oldest pending buyer-submitted payment and applies it against the invoice outstanding balance.')
                        ->action(function (BuyerOrder $record): void {
                            $invoice = $this->activeInvoiceFor($record);

                            $payment = $invoice?->payments()
                                ->where('status', PaymentStatus::Pending->value)
                                ->oldest('id')
                                ->first();

                            if ($payment === null) {
                                Notification::make()
                                    ->title('Nothing to confirm')
                                    ->body('There are no pending payments for this invoice.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            /** @var \App\Models\User $staff */
                            $staff = auth()->user();
                            $payment->confirm($staff);

                            Notification::make()
                                ->title('Payment confirmed')
                                ->body('The payment has been confirmed and the invoice updated.')
                                ->success()
                                ->send();
                        }),
                    Action::make('confirm')
                        ->label('Confirm')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (BuyerOrder $record): bool => $record->status->canConfirm())
                        ->modalHeading('Confirm this order?')
                        ->modalWidth(Width::Large)
                        ->modalDescription(function (BuyerOrder $record): HtmlString {
                            $buyer = $record->buyer;
                            $orderTotal = (float) $record->total;

                            $message = 'This will mark the order as confirmed.';

                            // Add credit information only if credit_status is enabled
                            if ($buyer && $buyer->credit_status) {
                                $availableCredit = (float) $buyer->available_credit;
                                $message .= "\n\n";
                                $message .= 'Order Total: '.number_format($orderTotal, 2)."\n";
                                $message .= 'Available Credit: '.number_format($availableCredit, 2);

                                if ($availableCredit < $orderTotal) {
                                    $message .= "\n\n⚠️ **Warning:** Insufficient credit available. Confirmation will fail.";
                                } elseif ($availableCredit - $orderTotal < ($orderTotal * 0.1)) {
                                    $message .= "\n\n⚠️ **Warning:** Low credit remaining after confirmation.";
                                }
                            }

                            $warning = $record->getCreditLimitWarning();
                            if ($warning !== null) {
                                $message .= "\n\n".$warning;
                            }

                            return new HtmlString(nl2br($message));
                        })
                        ->form(function (BuyerOrder $record): array {
                            $buyer = $record->buyer;
                            $schema = [];

                            // Only show toggle if buyer has credit_status enabled
                            if ($buyer && $buyer->credit_status) {
                                $schema[] = Toggle::make('use_credit')
                                    ->label('Use Credit')
                                    ->default(true)
                                    ->helperText('When enabled, available credit will be reduced when confirming this order.');
                            }

                            // If credit_status is disabled, return empty schema (no toggle shown)
                            return $schema;
                        })
                        ->action(function (BuyerOrder $record, array $data): void {
                            try {
                                $buyer = $record->buyer;

                                // If buyer has credit_status enabled, use form value (defaults to true)
                                // If credit_status is disabled, toggle wasn't shown, so default to false
                                $useCredit = ($buyer && $buyer->credit_status)
                                    ? ($data['use_credit'] ?? true)  // Toggle was shown, default to true
                                    : false;  // Toggle not shown, don't use credit

                                $record->confirm($useCredit);

                                $notificationBody = $useCredit
                                    ? 'Order has been confirmed and credit has been reduced.'
                                    : 'Order has been confirmed without using credit.';

                                Notification::make()
                                    ->title('Order confirmed')
                                    ->body($notificationBody)
                                    ->success()
                                    ->send();
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title('Confirmation failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('An error occurred while confirming the order: '.$e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->authorize(fn (?BuyerOrder $record): bool => $record !== null && auth()->user()?->can('cancel', $record) === true)
                        ->visible(fn (BuyerOrder $record): bool => $record->status->canCancel())
                        ->requiresConfirmation()
                        ->modalHeading('Cancel this order?')
                        ->modalDescription(function (BuyerOrder $record): string {
                            $message = 'This will mark the order as cancelled. This action cannot be undone.';
                            if ($record->status === OrderStatus::CONFIRMED) {
                                $message .= "\n\nCredit will be restored when the order is cancelled.";
                            }

                            return $message;
                        })
                        ->action(function (BuyerOrder $record): void {
                            $wasConfirmed = $record->status === OrderStatus::CONFIRMED;
                            $record->cancel();

                            $message = 'Order has been cancelled.';
                            if ($wasConfirmed) {
                                $message .= ' Credit has been restored.';
                            }

                            Notification::make()
                                ->title('Order cancelled')
                                ->body($message)
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => false), // Disable bulk delete for orders
                ]),
            ]);
    }
}
