<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title }} · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-white antialiased">
        <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12 sm:px-6">
            <div aria-hidden="true" class="absolute inset-x-0 top-0 h-72 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.18),transparent_65%)]"></div>

            <section class="relative w-full max-w-xl rounded-3xl border border-white/10 bg-zinc-900/90 p-6 shadow-2xl shadow-black/30 sm:p-10">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-3 font-semibold text-white">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-500 text-lg font-bold text-zinc-950">M7</span>
                    <span>{{ config('app.name') }}</span>
                </a>

                <p class="mt-10 text-sm font-semibold uppercase tracking-[0.22em] text-emerald-400">Error {{ $code }}</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $title }}</h1>
                <p class="mt-4 text-base leading-7 text-zinc-300">{{ $message }}</p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ url('/') }}" class="rounded-xl bg-emerald-500 px-5 py-3 text-center font-semibold text-zinc-950 transition hover:bg-emerald-400">Go to homepage</a>
                    <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.assign('{{ url('/') }}')" class="rounded-xl border border-white/15 px-5 py-3 font-semibold text-white transition hover:bg-white/5">Go back</button>
                </div>

                @if ($showContact ?? true)
                    <p class="mt-8 text-sm text-zinc-500">Still blocked? <a href="{{ url('/contact') }}" class="text-zinc-300 underline decoration-zinc-600 underline-offset-4 hover:text-white">Contact support</a>.</p>
                @endif
            </section>
        </main>
    </body>
</html>
