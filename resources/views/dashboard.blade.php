<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">{{ $tenant?->name ?? 'M7 CRM' }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Sales workspace</h1>
                <p class="mt-1 text-base text-zinc-600 dark:text-zinc-400">Your first secure CRM foundation is ready.</p>
            </div>
            <flux:button :href="route('companies.index')" icon="plus" variant="primary" wire:navigate>Add company</flux:button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500">Companies</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ number_format($companyCount) }}</p>
                <p class="mt-2 text-sm text-zinc-500">Customer business accounts</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500">Team members</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ number_format($teamCount) }}</p>
                <p class="mt-2 text-sm text-zinc-500">Users in this workspace</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500">Plan</p>
                <p class="mt-3 text-2xl font-semibold capitalize tracking-tight text-zinc-950 dark:text-white">{{ $tenant?->plan ?? 'Platform' }}</p>
                <p class="mt-2 text-sm text-zinc-500">Workspace subscription</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/40">
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Tenant isolation</p>
                <p class="mt-3 text-2xl font-semibold text-emerald-950 dark:text-emerald-100">Active</p>
                <p class="mt-2 text-sm text-emerald-700/80 dark:text-emerald-300/80">Company data is scope-protected</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <div>
                    <h2 class="font-semibold text-zinc-950 dark:text-white">Recently added companies</h2>
                    <p class="mt-1 text-sm text-zinc-500">Your latest customer accounts</p>
                </div>
                <flux:link :href="route('companies.index')" wire:navigate>View all</flux:link>
            </div>

            @forelse ($recentCompanies as $company)
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4 last:border-0 dark:border-zinc-800">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-zinc-950 dark:text-white">{{ $company->name }}</p>
                        <p class="mt-0.5 truncate text-sm text-zinc-500">{{ $company->city ?: 'City not added' }} · {{ $company->phone ?: 'No phone' }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-zinc-500">{{ $company->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-medium text-zinc-950 dark:text-white">No customer companies yet</p>
                    <p class="mt-1 text-sm text-zinc-500">Add the first company to begin building the CRM.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts::app>
