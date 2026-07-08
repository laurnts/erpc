@php
    /** @var list<\App\Models\BuyerQuote> $pendingQuotes */
@endphp

<div>
    @if (count($pendingQuotes) > 0)
        <div
            class="p-6"
            style="background-color: #fff7e6; border: 1px solid #f0cf8a; border-radius: 10px;"
        >
            <div class="mb-1 flex items-center gap-2">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: #c9850f;" aria-hidden="true"></span>
                <h3 class="text-sm font-semibold" style="color: #8a5a0a;">
                    Quote awaiting your confirmation
                </h3>
            </div>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Choose accept or reject for this quote. Accepting will require a purchase order upload.
            </p>

            <div class="space-y-3">
                @foreach ($pendingQuotes as $quote)
                    @php
                        $daysLeft = $quote->valid_until
                            ? (int) now()->startOfDay()->diffInDays($quote->valid_until->startOfDay(), false)
                            : null;
                    @endphp

                    <div
                        class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                        style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px;"
                        wire:key="pending-quote-{{ $quote->getKey() }}"
                    >
                        <div class="min-w-0 shrink-0 sm:w-48">
                            <p class="font-mono text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $quote->quote_number }} · v{{ $quote->version }}
                            </p>

                            @if ($quote->valid_until)
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    Valid until {{ $quote->valid_until->format('M j, Y') }}
                                    @if ($daysLeft !== null)
                                        · {{ max(0, $daysLeft) }} {{ \Illuminate\Support\Str::plural('day', max(0, $daysLeft)) }} left
                                    @endif
                                </p>
                            @endif
                        </div>

                        <p class="text-xl font-bold text-gray-950 dark:text-white sm:flex-1 sm:text-center">
                            {{ $quote->currency?->code }} {{ number_format((float) $quote->total, 2) }}
                        </p>

                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                            {{ ($this->downloadPdfAction)(['quote' => $quote->getKey()]) }}
                            {{ ($this->rejectAction)(['quote' => $quote->getKey()]) }}
                            {{ ($this->acceptAction)(['quote' => $quote->getKey()]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
