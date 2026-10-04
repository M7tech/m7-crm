@php
    $title = 'CRM for Iraqi sales teams';
    $description = 'Manage customers, sales pipelines, follow-ups, Meta leads, and Messenger conversations in one secure workspace.';
    $limitLabels = [
        'members' => 'team members',
        'companies' => 'client companies',
        'automation_rules' => 'automation rules',
        'meta_connections' => 'Meta Page connections',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-zinc-950/90 backdrop-blur">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3 font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-emerald-500 text-zinc-950"><x-app-logo-icon class="size-7 fill-current" /></span>
                    <span class="hidden sm:inline">{{ config('app.name') }}</span>
                </a>
                <nav aria-label="Main navigation" class="hidden items-center gap-6 text-sm text-zinc-300 md:flex">
                    <a href="#features" class="transition hover:text-white">Features</a>
                    <a href="#security" class="transition hover:text-white">Security</a>
                    <a href="#plans" class="transition hover:text-white">Plans</a>
                </nav>
                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-300 transition hover:bg-white/5 hover:text-white">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-400"><span class="sm:hidden">Start</span><span class="hidden sm:inline">Create workspace</span></a>
                </div>
            </div>
        </header>

        <main>
            <section class="relative isolate overflow-hidden">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(circle_at_25%_20%,rgba(16,185,129,0.2),transparent_38%),radial-gradient(circle_at_80%_35%,rgba(59,130,246,0.13),transparent_32%)]"></div>
                <div class="mx-auto grid w-full max-w-7xl gap-14 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[minmax(0,0.9fr)_minmax(32rem,1.1fr)] lg:items-center lg:px-8">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-sm text-emerald-300">
                            <span class="size-1.5 rounded-full bg-emerald-400"></span>
                            Built for focused sales teams
                        </div>
                        <h1 class="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-6xl">A practical CRM for Iraqi sales teams</h1>
                        <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-300">Keep client companies, contacts, opportunities, follow-ups, Facebook leads, and Messenger conversations in one tenant-isolated workspace.</p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ route('register') }}" class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950 shadow-lg shadow-emerald-950/40 transition hover:bg-emerald-400">Start a workspace</a>
                            <a href="#features" class="rounded-xl border border-white/15 bg-white/5 px-5 py-3 font-semibold text-white transition hover:bg-white/10">See what is included</a>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-400">
                            <span>✓ No credit card required</span>
                            <span>✓ Default sales pipeline included</span>
                            <span>✓ Asia/Baghdad timezone</span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-2xl">
                        <div aria-hidden="true" class="absolute -inset-5 -z-10 rounded-[2rem] bg-emerald-500/10 blur-2xl"></div>
                        <div class="overflow-hidden rounded-2xl border border-white/10 bg-zinc-900 shadow-2xl shadow-black/40">
                            <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                                <div><p class="text-sm font-semibold">Sales pipeline</p><p class="text-xs text-zinc-500">Current opportunities</p></div>
                                <span class="rounded-lg bg-emerald-400/10 px-2.5 py-1 text-xs font-medium text-emerald-300">Example workspace</span>
                            </div>
                            <div class="grid grid-cols-3 gap-px bg-white/10">
                                <div class="bg-zinc-900 p-4"><p class="text-xs text-zinc-500">Open leads</p><p class="mt-1 text-2xl font-semibold">24</p></div>
                                <div class="bg-zinc-900 p-4"><p class="text-xs text-zinc-500">Pipeline value</p><p class="mt-1 text-2xl font-semibold">18.4m IQD</p></div>
                                <div class="bg-zinc-900 p-4"><p class="text-xs text-zinc-500">Due today</p><p class="mt-1 text-2xl font-semibold">6</p></div>
                            </div>
                            <div class="grid gap-3 p-4 sm:grid-cols-3">
                                @foreach (['New' => ['Retail expansion', 'Office renewal'], 'Qualified' => ['Hotel renovation', 'New showroom'], 'Proposal' => ['Annual supply contract']] as $stage => $items)
                                    <div class="rounded-xl bg-zinc-950/70 p-3">
                                        <div class="mb-3 flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ $stage }}</span><span class="rounded-full bg-white/5 px-2 py-0.5 text-xs text-zinc-500">{{ count($items) }}</span></div>
                                        <div class="space-y-2">
                                            @foreach ($items as $item)
                                                <div class="rounded-lg border border-white/10 bg-zinc-900 p-3"><p class="text-sm font-medium">{{ $item }}</p><p class="mt-1 text-xs text-zinc-500">Follow-up scheduled</p></div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="border-y border-white/10 bg-zinc-900/50 py-20 sm:py-24">
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-2xl"><p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">One sales workspace</p><h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">From first contact to closed opportunity</h2><p class="mt-4 text-lg text-zinc-400">The core tools are connected, so the team spends less time moving information between apps.</p></div>
                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['Customers and contacts', 'Keep every customer company and its people together. Import CSV files or review business-card details scanned on the device.'],
                            ['Visual sales pipeline', 'Move opportunities between configurable stages, track value, assignment, win or loss, and retain the activity history.'],
                            ['Tasks and follow-ups', 'Assign work, schedule reminders, see overdue items, and keep completion history tied to the sales process.'],
                            ['Meta Lead Ads', 'Route each connected Facebook Page to its own CRM company, pipeline stage, and responsible team member.'],
                            ['Messenger inbox', 'Receive Facebook Page text conversations, reply from the CRM, and import available message history.'],
                            ['Reports and automation', 'Review conversion and value metrics, then create bounded follow-up tasks when a lead enters a chosen stage.'],
                        ] as [$heading, $copy])
                            <article class="rounded-2xl border border-white/10 bg-zinc-900 p-6"><div class="mb-5 flex size-10 items-center justify-center rounded-xl bg-emerald-400/10 text-lg text-emerald-300">✓</div><h3 class="font-semibold">{{ $heading }}</h3><p class="mt-2 leading-7 text-zinc-400">{{ $copy }}</p></article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="security" class="py-20 sm:py-24">
                <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
                    <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">Security by design</p><h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Each company stays in its own workspace</h2><p class="mt-5 text-lg leading-8 text-zinc-400">Tenant ownership is resolved on the server. Roles control write access, provider credentials are encrypted, and signed webhooks are processed safely in the background.</p></div>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        @foreach ([['Fail-closed isolation', 'Queries return no tenant data when a workspace is not resolved.'], ['Role-based access', 'Company admins, managers, and salespeople receive bounded permissions.'], ['Encrypted credentials', 'Meta secrets and Page access tokens are encrypted at rest.'], ['Reviewed capture', 'Business-card photos stay on the device by default; only approved fields are saved.']] as [$heading, $copy])
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-5"><dt class="font-semibold text-white">{{ $heading }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-400">{{ $copy }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            </section>

            <section id="plans" class="border-y border-white/10 bg-zinc-900/50 py-20 sm:py-24">
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-2xl"><p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">Workspace capacity</p><h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Start focused, then grow</h2><p class="mt-4 text-zinc-400">New workspaces begin on Starter. Contact us when you need larger capacity.</p></div>
                    <div class="mt-10 grid gap-5 lg:grid-cols-3">
                        @foreach ($plans as $key => $plan)
                            <article @class(['rounded-2xl border p-6', 'border-emerald-400/50 bg-emerald-400/5' => $key === config('plans.default'), 'border-white/10 bg-zinc-900' => $key !== config('plans.default')])>
                                <div class="flex items-center justify-between gap-3"><h3 class="text-xl font-semibold">{{ $plan['label'] }}</h3>@if ($key === config('plans.default'))<span class="rounded-full bg-emerald-400/15 px-2.5 py-1 text-xs font-medium text-emerald-300">Starts here</span>@endif</div>
                                <ul class="mt-6 space-y-3 text-sm text-zinc-300">
                                    @foreach ($plan['limits'] as $limit => $value)
                                        <li class="flex items-start gap-2"><span class="text-emerald-400">✓</span><span><strong class="text-white">{{ $value === null ? 'Unlimited' : number_format($value) }}</strong> {{ $limitLabels[$limit] ?? str_replace('_', ' ', $limit) }}</span></li>
                                    @endforeach
                                </ul>
                                @if ($key === config('plans.default'))
                                    <a href="{{ route('register') }}" class="mt-7 block rounded-xl bg-emerald-500 px-4 py-3 text-center font-semibold text-zinc-950 hover:bg-emerald-400">Create Starter workspace</a>
                                @else
                                    <a href="{{ route('contact.create', ['plan' => $key]) }}" class="mt-7 block rounded-xl border border-white/15 px-4 py-3 text-center font-semibold text-white hover:bg-white/5">Discuss {{ $plan['label'] }}</a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="px-4 py-20 sm:px-6 sm:py-24">
                <div class="mx-auto max-w-4xl rounded-3xl border border-emerald-400/20 bg-[linear-gradient(135deg,rgba(16,185,129,0.16),rgba(24,24,27,0.8))] p-8 text-center sm:p-12"><h2 class="text-3xl font-semibold tracking-tight">Bring the sales process into one place</h2><p class="mx-auto mt-4 max-w-2xl text-zinc-300">Create your company workspace, invite the team, and start with a ready-to-use pipeline.</p><a href="{{ route('register') }}" class="mt-7 inline-block rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950 hover:bg-emerald-400">Create workspace</a></div>
            </section>
        </main>

        <footer class="border-t border-white/10">
            <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p>&copy; {{ now()->year }} {{ config('legal.operator_name') }}.</p>
                <nav aria-label="Footer" class="flex flex-wrap gap-x-5 gap-y-2"><a href="{{ route('legal.privacy') }}" class="hover:text-white">Privacy</a><a href="{{ route('legal.terms') }}" class="hover:text-white">Terms</a><a href="{{ route('legal.data-deletion') }}" class="hover:text-white">Data deletion</a><a href="{{ route('contact.create') }}" class="hover:text-white">Contact</a></nav>
            </div>
        </footer>
    </body>
</html>
