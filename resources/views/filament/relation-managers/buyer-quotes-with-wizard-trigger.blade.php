<div class="fi-resource-relation-manager">
    {{-- Open create buyer quote wizard at step 2 when page loaded with ?open_buyer_quote_wizard=1&sq_ids=... --}}
    @if($pendingBuyerQuoteWizardIds !== null)
        <div
            wire:init="$wire.call('openBuyerQuoteWizardFromRedirect')"
            class="hidden"
            aria-hidden="true"
        ></div>
    @endif
    {{ $this->content }}

    <x-filament-panels::unsaved-action-changes-alert />
</div>
