<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\BuyerQuoteStatus;
use App\Models\BuyerQuote;
use App\Models\Request;
use App\Services\Erp\PdfGenerationService;
use App\Support\DocumentUpload;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

final class BuyerPendingQuoteActions extends BaseLivewireComponent
{
    public Request $request;

    public function mount(Request $request): void
    {
        $this->request = $request;
    }

    #[On('note-posted')]
    public function refreshAfterNote(): void {}

    /**
     * @return list<BuyerQuote>
     */
    public function getPendingQuotesProperty(): array
    {
        return $this->request->buyerQuotes()
            ->where('status', BuyerQuoteStatus::SENT)
            ->with('currency')
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('Accept')
            ->color('success')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Accept this quote?')
            ->modalDescription('To complete acceptance, you will need to upload your purchase order.')
            ->modalSubmitActionLabel('Confirm')
            ->action(function (array $arguments): void {
                $this->resolveQuote((int) ($arguments['quote'] ?? 0));
            })
            ->after(function (array $arguments): void {
                $quoteId = (int) ($arguments['quote'] ?? 0);

                $this->mountedActions = [];
                $this->cachedMountedActions = [];

                $this->mountAction('uploadPo', ['quote' => $quoteId]);
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading('Reject this quote?')
            ->action(function (array $arguments): void {
                $quote = $this->resolveQuote((int) ($arguments['quote'] ?? 0));
                $quote->markAsRejected();

                $this->dispatch('quote-action-taken');

                Notification::make()
                    ->title('Quote rejected')
                    ->warning()
                    ->send();
            });
    }

    public function uploadPoAction(): Action
    {
        return Action::make('uploadPo')
            ->label('Upload PO')
            ->color('gray')
            ->modalHeading('Upload Purchase Order')
            ->modalDescription('Upload your purchase order to complete quote acceptance.')
            ->modalSubmitActionLabel('Upload')
            ->schema(fn (array $arguments): array => [
                Section::make('Upload Purchase Order')
                    ->schema([
                        FileUpload::make('buyer_po_files')
                            ->label('PO File')
                            ->helperText(DocumentUpload::helperText(2048))
                            ->acceptedFileTypes(DocumentUpload::ACCEPTED_MIME_TYPES)
                            ->disk('local')
                            ->directory(BuyerQuote::PO_FILES_UPLOAD_DIRECTORY)
                            ->visibility('private')
                            ->multiple()
                            ->maxFiles(10)
                            ->maxSize(2048)
                            ->required(),
                    ]),
            ])
            ->action(function (array $arguments, array $data): void {
                $quote = $this->resolveQuote((int) ($arguments['quote'] ?? 0), 'uploadPo');

                app(AttachUploadedFiles::class)->execute($quote, $data['buyer_po_files'] ?? [], 'buyer_po', BuyerQuote::PO_FILES_UPLOAD_DIRECTORY);

                $quote->refresh();

                if ($quote->status === BuyerQuoteStatus::SENT && $quote->getMedia('buyer_po')->isNotEmpty()) {
                    $quote->markAsAccepted();
                }

                $this->dispatch('quote-action-taken');

                Notification::make()
                    ->title('Quote accepted')
                    ->body('Your purchase order has been uploaded.')
                    ->success()
                    ->send();
            });
    }

    public function downloadPdfAction(): Action
    {
        return Action::make('downloadPdf')
            ->label('PDF')
            ->color('gray')
            ->outlined()
            ->action(function (array $arguments): \Symfony\Component\HttpFoundation\StreamedResponse {
                $quote = $this->findQuote((int) ($arguments['quote'] ?? 0));

                abort_unless($this->portalUser()?->can('view', $quote) ?? false, 403);

                $pdfService = app(PdfGenerationService::class);
                $content = $pdfService->generateBuyerQuotePdf($quote);
                $filename = $pdfService->getBuyerQuoteFilename($quote);

                return response()->streamDownload(
                    callback: static function () use ($content): void {
                        echo $content;
                    },
                    name: $filename,
                    headers: ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public function render(): View
    {
        return view('livewire.buyer-pending-quote-actions', [
            'pendingQuotes' => $this->pendingQuotes,
        ]);
    }

    private function resolveQuote(int $quoteId, string $ability = 'respond'): BuyerQuote
    {
        $quote = $this->findQuote($quoteId);

        abort_unless($this->portalUser()?->can($ability, $quote) ?? false, 403);

        if ($ability === 'respond' && $quote->status !== BuyerQuoteStatus::SENT) {
            abort(403);
        }

        return $quote;
    }

    private function findQuote(int $quoteId): BuyerQuote
    {
        return BuyerQuote::query()
            ->whereKey($quoteId)
            ->where('request_id', $this->request->getKey())
            ->firstOrFail();
    }

    private function portalUser(): ?\App\Models\User
    {
        $user = Filament::auth()->user();

        return $user instanceof \App\Models\User ? $user : null;
    }
}
