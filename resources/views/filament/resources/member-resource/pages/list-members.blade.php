@php
    $pendingTeam = $this->getPendingInvitationsTeam();
    $owner = $this->getTeamOwner();
@endphp

<x-filament-panels::page>
    @if($owner)
        <x-filament::section>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <x-filament::avatar
                        src="{{ \Filament\Facades\Filament::getUserAvatarUrl($owner) }}"
                        alt="{{ $owner->name }}"
                    />
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $owner->name }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $owner->email }}
                        </span>
                    </div>
                </div>

                <x-filament::badge color="warning">
                    Owner
                </x-filament::badge>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}

    @if($pendingTeam)
        <div class="mt-6">
            <x-filament::section>
                @livewire(\App\Livewire\App\Teams\PendingTeamInvitations::class, ['team' => $pendingTeam], key('pending-invitations'))
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
