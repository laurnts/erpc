<div>
    @if ($accountExists)
        @php($signedInAsInvitee = auth('supplier')->check() && auth('supplier')->user()?->email === $invitation?->email)

        <div class="space-y-4">
            @if ($signedInAsInvitee)
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Accept access to <strong>{{ $invitation?->company?->name }}</strong> for
                    <strong>{{ $invitation?->email }}</strong>.
                </p>

                <form wire:submit="accept">
                    <x-filament::button type="submit" class="w-full">
                        Accept Invitation
                    </x-filament::button>
                </form>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    An account already exists for <strong>{{ $invitation?->email }}</strong>.
                    Sign in to that account to accept access to {{ $invitation?->company?->name }}.
                </p>

                <form wire:submit="accept">
                    <x-filament::button type="submit" class="w-full">
                        Sign in to accept
                    </x-filament::button>
                </form>
            @endif
        </div>
    @else
        <form wire:submit="accept">
            {{ $this->form }}

            <x-filament::button type="submit" class="mt-4 w-full">
                Create Account
            </x-filament::button>
        </form>
    @endif
</div>
