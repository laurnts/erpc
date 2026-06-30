<div>
    <form wire:submit="accept">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4 w-full">
            Create Account
        </x-filament::button>
    </form>
</div>
