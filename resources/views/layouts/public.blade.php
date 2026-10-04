<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('locales.rtl'), true) ? 'rtl' : 'ltr' }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-3 font-semibold">
                    <x-app-logo-icon class="size-8 fill-current" />
                    <span>{{ config('app.name') }}</span>
                </a>
                <nav aria-label="Legal pages" class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <x-language-switcher compact />
                    <a href="{{ route('legal.privacy') }}" @class(['font-semibold text-emerald-700 dark:text-emerald-400' => request()->routeIs('legal.privacy'), 'hover:underline'])>{{ __('Privacy') }}</a>
                    <a href="{{ route('legal.terms') }}" @class(['font-semibold text-emerald-700 dark:text-emerald-400' => request()->routeIs('legal.terms'), 'hover:underline'])>{{ __('Terms') }}</a>
                    <a href="{{ route('legal.data-deletion') }}" @class(['font-semibold text-emerald-700 dark:text-emerald-400' => request()->routeIs('legal.data-deletion'), 'hover:underline'])>{{ __('Data deletion') }}</a>
                    <a href="{{ route('contact.create') }}" @class(['font-semibold text-emerald-700 dark:text-emerald-400' => request()->routeIs('contact.*'), 'hover:underline'])>{{ __('Contact') }}</a>
                    <a href="{{ route(auth()->check() ? 'dashboard' : 'login') }}" class="rounded-lg bg-emerald-700 px-3 py-2 font-medium text-white hover:bg-emerald-800">{{ __(auth()->check() ? 'Open CRM' : 'Log in') }}</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex w-full max-w-5xl flex-col gap-2 px-4 py-6 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>&copy; {{ now()->year }} {{ config('legal.operator_name') }}.</p>
                <p><a href="{{ route('contact.create') }}" class="hover:underline">{{ __('Contact support') }}</a> · {{ __('Privacy') }}: <a href="mailto:{{ config('legal.contact_email') }}" class="hover:underline">{{ config('legal.contact_email') }}</a></p>
            </div>
        </footer>
    </body>
</html>
