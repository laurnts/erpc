<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold text-black dark:text-white mb-2">Register for Buyer Portal Access</h1>

    @if ($submitted)
        <div class="flex flex-col items-center gap-4 rounded-xl border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950/40 p-10 text-center mt-6 p-4">
            <x-heroicon-o-check-circle class="h-10 w-10 text-green-600 dark:text-green-400"/>
            <p class="text-lg font-semibold text-green-800 dark:text-gray-300">Application received</p>
            <p class="text-sm text-green-700 dark:text-gray-200">
                Your application is awaiting approval. We will notify you by email as soon as a decision has been made.
                You will not be able to sign in until your application is approved.
            </p>
            <a href="{{ route('catalog.home') }}" class="mt-2 rounded-md bg-primary hover:bg-primary-600 px-4 py-2 text-sm font-medium text-white">
                Back to catalog
            </a>
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            Apply for access to our buyer portal. Once approved by our team, you can submit quote requests and
            track them online.
        </p>

        <form wire:submit="submit" class="flex flex-col gap-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your name</label>
                <input id="name" type="text" wire:model="name" autocomplete="name"
                       class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                @error('name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email address</label>
                <input id="email" type="email" wire:model="email" autocomplete="email"
                       class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                @error('email') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company name</label>
                <input id="company_name" type="text" wire:model="company_name" autocomplete="organization"
                       class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                @error('company_name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone <span class="text-gray-400">(optional)</span></label>
                <input id="phone" type="text" wire:model="phone" autocomplete="tel"
                       class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                @error('phone') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message <span class="text-gray-400">(optional)</span></label>
                <textarea id="message" wire:model="message" rows="3"
                          class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100"></textarea>
                @error('message') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password"
                       class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                @error('password') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm password</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                       class="w-full rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
            </div>

            <button type="submit"
                    class="mt-2 rounded-md bg-primary hover:bg-primary-600 px-5 py-2.5 text-sm font-medium text-white transition">
                Submit application
            </button>
        </form>
    @endif
</div>
