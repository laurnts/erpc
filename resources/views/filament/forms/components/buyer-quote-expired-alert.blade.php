@php
    /** @var \App\Models\BuyerQuote|null $record */
    $record = $getRecord();
@endphp
@if($record && $record->exists && $record->is_expired)
    <div class="rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-800 dark:bg-danger-950/30" role="alert">
        <div class="flex items-start gap-3">
            <svg class="h-6 w-6 shrink-0 text-danger-600 dark:text-danger-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
                <h4 class="text-sm font-semibold text-danger-800 dark:text-danger-200">
                    {{ __('This quote has expired') }}
                </h4>
                <p class="mt-1 text-sm text-danger-700 dark:text-danger-300">
                    {{ __('Valid until was :date. Key accounts and the buyer are notified when a quote expires.', ['date' => $record->valid_until?->format('d M Y') ?? '—']) }}
                </p>
            </div>
        </div>
    </div>
@endif
