<x-layouts::app :title="__('Companies')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">CRM</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Companies') }}</h1>
                <p class="mt-1 text-base text-zinc-600 dark:text-zinc-400">{{ __('Business accounts belonging to your company workspace.') }}</p>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">{{ __('Company directory') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __(':count companies', ['count' => number_format($companies->total())]) }}</p>
                </div>

                @if ($companies->isEmpty())
                    <div class="px-5 py-14 text-center">
                        <div class="mx-auto flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            <flux:icon.building-office-2 class="size-5" />
                        </div>
                        <h3 class="mt-4 font-semibold text-zinc-950 dark:text-white">{{ __('No companies yet') }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('Add the first customer company using the form.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-start text-sm">
                            <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/60">
                                <tr>
                                    <th class="px-5 py-3">{{ __('Company') }}</th>
                                    <th class="px-5 py-3">{{ __('Relationship') }}</th>
                                    <th class="px-5 py-3">{{ __('Purchases') }}</th>
                                    <th class="px-5 py-3">{{ __('Latest purchase') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($companies as $company)
                                    <tr class="text-zinc-700 dark:text-zinc-300">
                                        <td class="px-5 py-4">
                                            <div class="font-medium text-zinc-950 dark:text-white">{{ $company->name }}</div>
                                            <div class="mt-0.5 text-xs text-zinc-500">{{ $company->email ?: __('No email') }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span @class([
                                                'rounded-full px-2 py-1 text-xs font-semibold',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $company->won_leads_count > 0,
                                                'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $company->won_leads_count === 0,
                                            ])>{{ __($company->won_leads_count > 0 ? 'Customer' : 'Prospect') }}</span>
                                        </td>
                                        <td class="px-5 py-4"><span class="font-medium text-zinc-950 dark:text-white">{{ $company->won_leads_count }}</span><span class="ms-1 text-xs text-zinc-500">{{ __('of :count leads', ['count' => $company->leads_count]) }}</span></td>
                                        <td class="px-5 py-4 text-zinc-500">{{ $company->last_purchase_at ? \Illuminate\Support\Carbon::parse($company->last_purchase_at)->format('M j, Y') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($companies->hasPages())
                        <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                            {{ $companies->links() }}
                        </div>
                    @endif
                @endif
            </section>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">{{ __('Add company') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Create a customer business account.') }}</p>

                <form method="POST" action="{{ route('companies.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <flux:input name="name" :label="__('Company name')" :value="old('name')" required autofocus />
                    <flux:input name="phone" :label="__('Phone')" :value="old('phone')" />
                    <flux:input name="email" type="email" :label="__('Email')" :value="old('email')" />
                    <flux:input name="city" :label="__('City')" :value="old('city')" />
                    <flux:textarea name="notes" :label="__('Notes')" rows="3">{{ old('notes') }}</flux:textarea>
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Add company') }}</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::app>
