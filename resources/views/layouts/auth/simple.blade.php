@php($robots = 'noindex,nofollow')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
                <nav aria-label="Legal" class="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-2 text-xs text-zinc-500">
                    <a href="{{ route('legal.privacy') }}" class="hover:text-zinc-900 hover:underline dark:hover:text-white">Privacy</a>
                    <a href="{{ route('legal.terms') }}" class="hover:text-zinc-900 hover:underline dark:hover:text-white">Terms</a>
                    <a href="{{ route('legal.data-deletion') }}" class="hover:text-zinc-900 hover:underline dark:hover:text-white">Data deletion</a>
                    <a href="{{ route('contact.create') }}" class="hover:text-zinc-900 hover:underline dark:hover:text-white">Contact</a>
                </nav>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
