<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96"/>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg"/>
    <link rel="shortcut icon" href="/favicon.ico"/>

    <script>
        // Avoid dark-mode FOUC: same convention as the marketing header.
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>
<body class="antialiased bg-white dark:bg-black text-gray-800 dark:text-gray-200 min-h-screen flex flex-col">

<header class="bg-white/95 dark:bg-black/95 border-b border-gray-100 dark:border-gray-900 sticky top-0 z-50 backdrop-blur-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('catalog.home') }}" class="flex items-center gap-2.5 shrink-0" aria-label="{{ config('app.name') }} Home">
                <img class="h-8 w-auto" src="{{ asset('relaticle-logomark.svg') }}" alt="{{ config('app.name') }} Logo">
                <span class="font-bold text-lg text-black dark:text-white hidden sm:block">{{ config('app.name') }}</span>
            </a>

            <div class="flex items-center gap-4">
                <button id="theme-toggle"
                        class="p-2 text-gray-500 dark:text-gray-400 hover:text-primary rounded-full"
                        aria-label="Toggle dark mode"
                        onclick="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';">
                    <x-heroicon-o-sun class="h-5 w-5 hidden dark:block"/>
                    <x-heroicon-o-moon class="h-5 w-5 block dark:hidden"/>
                </button>

                <nav class="hidden md:flex items-center gap-5 text-sm font-medium">
                    <a href="{{ url()->getCustomerPortalUrl('login') }}" class="text-gray-700 dark:text-gray-200 hover:text-primary">Buyer Login</a>
                    <a href="{{ url()->getSupplierPortalUrl('login') }}" class="text-gray-700 dark:text-gray-200 hover:text-primary">Supplier Login</a>
                    <a href="{{ url()->getAppUrl('login') }}" class="text-gray-700 dark:text-gray-200 hover:text-primary">Staff Login</a>
                    <a href="{{ route('catalog.register') }}" class="bg-primary hover:bg-primary-600 text-white px-4 py-2 rounded-md">Register</a>
                </nav>

                <livewire:catalog.cart-badge/>
            </div>
        </div>

        <nav class="md:hidden flex items-center gap-4 text-sm font-medium mt-3 overflow-x-auto">
            <a href="{{ url()->getCustomerPortalUrl('login') }}" class="text-gray-700 dark:text-gray-200 whitespace-nowrap">Buyer Login</a>
            <a href="{{ url()->getSupplierPortalUrl('login') }}" class="text-gray-700 dark:text-gray-200 whitespace-nowrap">Supplier Login</a>
            <a href="{{ url()->getAppUrl('login') }}" class="text-gray-700 dark:text-gray-200 whitespace-nowrap">Staff Login</a>
            <a href="{{ route('catalog.register') }}" class="text-primary whitespace-nowrap">Register</a>
        </nav>
    </div>
</header>

<main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    {{ $slot }}
</main>

<footer class="border-t border-gray-100 dark:border-gray-900 py-6 mt-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
        <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
        <div class="flex items-center gap-4">
            <a href="{{ route('terms.show') }}" class="hover:text-primary">Terms of Service</a>
            <a href="{{ route('policy.show') }}" class="hover:text-primary">Privacy Policy</a>
        </div>
    </div>
</footer>

{{-- Toast confirming add-to-quote; listens for the Livewire browser event --}}
<div x-data="{ show: false, message: '' }"
     x-on:catalog-cart-added.window="message = ($event.detail.name ?? 'Article') + ' added to your quote cart'; show = true; clearTimeout($el._toastTimer); $el._toastTimer = setTimeout(() => show = false, 2500)"
     x-show="show"
     x-cloak
     x-transition.opacity.duration.200ms
     class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-lg bg-gray-900 dark:bg-gray-100 px-4 py-3 text-sm font-medium text-white dark:text-gray-900 shadow-lg">
    <x-heroicon-o-check-circle class="h-5 w-5 text-green-400 dark:text-green-600"/>
    <span x-text="message"></span>
</div>

@livewireScripts
</body>
</html>
