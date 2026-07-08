<?php

declare(strict_types=1);

namespace App\Actions\SupplierPortal;

use App\Actions\Media\AttachUploadedFiles;
use App\Enums\SupplierQuoteSubmissionMethod;
use App\Models\ExchangeRate;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SupplierQuoteSubmittedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Portal counterpart of the admin "Input price" flow: per-item unit prices,
 * quote-level currency/validity/notes, and the quotation document upload.
 * The attribute whitelist is this action's explicit parameter list — no raw
 * form payload ever reaches the models, and `exchange_rate` in particular is
 * ALWAYS resolved server-side from the ExchangeRate table (it drives
 * base-currency comparison ranking, so client values are never accepted).
 * The existing observer machinery performs the PENDING→RECEIVED transition
 * unchanged.
 */
final readonly class SubmitSupplierRequestResponse
{
    public function __construct(
        private AttachUploadedFiles $attachUploadedFiles,
    ) {}

    /**
     * @param  array<int|string, mixed>  $itemPrices  unit prices keyed by supplier quote item id
     */
    public function execute(
        SupplierQuote $quote,
        User $user,
        array $itemPrices,
        int $currencyId,
        Carbon|string|null $validUntil,
        ?string $notes,
        mixed $quotationFiles = null,
    ): SupplierQuote {
        $exchangeRate = $this->resolveExchangeRate($quote, $currencyId);

        DB::transaction(function () use ($quote, $user, $itemPrices, $currencyId, $exchangeRate, $validUntil, $notes, $quotationFiles): void {
            $quote->forceFill([
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'valid_until' => $validUntil ?? $quote->valid_until,
                'notes' => $notes,
                'quoted_at' => now(),
                'submitted_via' => SupplierQuoteSubmissionMethod::Portal,
                'submitted_at' => now(),
                'submitted_by_user_id' => $user->getKey(),
            ])->save();

            $this->applyItemPrices($quote, $itemPrices);

            $files = is_array($quotationFiles)
                ? $quotationFiles
                : ($quotationFiles !== null ? [$quotationFiles] : []);

            if ($files !== []) {
                $this->attachUploadedFiles->execute($quote, $files, 'quotation', SupplierQuote::QUOTATION_UPLOAD_DIRECTORY);
            }
        });

        $quote->refresh();

        $this->notifyTeam($quote);

        return $quote;
    }

    /**
     * Update main-line unit prices only; child/detail rows of service quotes
     * are read-only in the portal. Item saves run the existing
     * SupplierQuoteItemObserver, which recalculates line and quote totals and
     * performs the PENDING→RECEIVED transition exactly as the admin flow does.
     *
     * @param  array<int|string, mixed>  $itemPrices
     */
    private function applyItemPrices(SupplierQuote $quote, array $itemPrices): void
    {
        $mainItems = $quote->items()->with('requestItem')->get()
            ->filter(fn (SupplierQuoteItem $item): bool => $item->requestItem === null
                || $item->requestItem->parent_id === null);

        foreach ($mainItems as $item) {
            $price = $itemPrices[$item->getKey()] ?? null;

            if (! is_numeric($price)) {
                continue;
            }

            $item->unit_price = (string) $price;
            $item->save();
        }
    }

    /**
     * Server-side exchange-rate resolution, mirroring the admin form's Hidden
     * field: base currency ⇒ 1; otherwise the latest effective rate from the
     * ExchangeRate table, defaulting to 1 when none exists.
     */
    private function resolveExchangeRate(SupplierQuote $quote, int $currencyId): string
    {
        $baseCurrency = $quote->team?->getBaseCurrency();

        if ($baseCurrency === null || $currencyId === $baseCurrency->getKey()) {
            return '1';
        }

        $rate = ExchangeRate::query()
            ->where('team_id', $quote->team_id)
            ->where('from_currency_id', $currencyId)
            ->where('to_currency_id', $baseCurrency->getKey())
            ->orderByDesc('effective_date')
            ->value('rate');

        return $rate !== null ? (string) $rate : '1';
    }

    private function notifyTeam(SupplierQuote $quote): void
    {
        $team = $quote->team;

        if (! $team instanceof Team) {
            return;
        }

        $recipients = $team->allUsers()
            ->filter(fn (User $user): bool => $user->hasVerifiedEmail())
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SupplierQuoteSubmittedNotification($quote));
    }
}
