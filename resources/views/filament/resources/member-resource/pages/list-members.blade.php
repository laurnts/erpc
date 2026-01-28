@php
    $pendingTeam = $this->getPendingInvitationsTeam();
@endphp

<x-filament-panels::page>
    {{ $this->table }}

    @if($pendingTeam)
        <div class="mt-6">
            <x-filament::section>
                @livewire(\App\Livewire\App\Teams\PendingTeamInvitations::class, ['team' => $pendingTeam], key('pending-invitations'))
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
