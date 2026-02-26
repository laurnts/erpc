<?php

declare(strict_types=1);

namespace App\Jobs\Erp;

use App\Enums\BuyerQuoteStatus;
use App\Mail\Erp\QuoteExpiredMail;
use App\Models\BuyerQuote;
use App\Notifications\Erp\QuoteExpiredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Job to check for buyer quotes that have expired and send emails to the buyer and key accounts.
 * Runs daily; notifies only once per quote using notification_metadata.
 */
final class CheckExpiredQuotesJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    private const string EXPIRED_NOTIFIED_KEY = 'expired_notified';

    public function handle(): void
    {
        // Only consider quotes that expired yesterday (valid_until date is yesterday)
        // so we notify once per quote the day after expiry and avoid notifying for old history.
        $yesterday = Carbon::yesterday();

        $quotes = BuyerQuote::query()
            ->with(['buyer.keyAccounts', 'request'])
            ->where('status', BuyerQuoteStatus::SENT)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', $yesterday)
            ->get();

        /** @var BuyerQuote $quote */
        foreach ($quotes as $quote) {
            if ($this->hasBeenNotified($quote)) {
                continue;
            }

            $this->notifyBuyerAndKeyAccounts($quote);
            $this->markAsNotified($quote);
        }
    }

    private function hasBeenNotified(BuyerQuote $quote): bool
    {
        /** @var Collection<string, mixed>|array<string, mixed>|null $metadata */
        $metadata = $quote->getAttributeValue('notification_metadata');

        if ($metadata === null) {
            return false;
        }

        if (is_array($metadata)) {
            return isset($metadata[self::EXPIRED_NOTIFIED_KEY]) && $metadata[self::EXPIRED_NOTIFIED_KEY] === true;
        }

        return false;
    }

    private function notifyBuyerAndKeyAccounts(BuyerQuote $quote): void
    {
        $buyer = $quote->buyer;
        $buyerEmail = $buyer?->email ?? null;

        if (! empty($buyerEmail)) {
            try {
                Mail::to($buyerEmail)->send(new QuoteExpiredMail($quote, 'buyer'));
            } catch (\Throwable $e) {
                Log::error('Failed to send quote expired email to buyer', [
                    'quote_id' => $quote->getKey(),
                    'buyer_email' => $buyerEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $keyAccounts = $buyer?->keyAccounts ?? collect();
        foreach ($keyAccounts as $user) {
            if (empty($user->email)) {
                continue;
            }
            try {
                $user->notify(new QuoteExpiredNotification($quote));
            } catch (\Throwable $e) {
                Log::error('Failed to send quote expired notification to key account', [
                    'quote_id' => $quote->getKey(),
                    'user_id' => $user->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function markAsNotified(BuyerQuote $quote): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $quote->getAttributeValue('notification_metadata') ?? [];

        $metadata[self::EXPIRED_NOTIFIED_KEY] = true;
        $metadata[self::EXPIRED_NOTIFIED_KEY . '_at'] = now()->toIso8601String();

        $quote->forceFill(['notification_metadata' => $metadata]);
        $quote->saveQuietly();
    }
}
