{{-- Load quote details only (no supplier_groups) to avoid 502; line items built on submit. --}}
<div
    x-data
    x-init="$wire.call('loadWizardStep2Data')"
    class="hidden"
    aria-hidden="true"
></div>
