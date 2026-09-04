<x-layouts::app :title="__('Contacts')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">CRM</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Contacts</h1>
                <p class="mt-1 text-base text-zinc-600 dark:text-zinc-400">People connected to customer companies in your workspace.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('create', \App\Models\BusinessCardScan::class)
                    <flux:button :href="route('contacts.business-cards.create')" icon="camera" variant="primary" wire:navigate>Scan business card</flux:button>
                @endcan
                @can('create', \App\Models\ContactImport::class)
                    <flux:button :href="route('contacts.import.create')" icon="arrow-up-tray" wire:navigate>Import CSV</flux:button>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">Contact directory</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ number_format($contacts->total()) }} contacts</p>
                </div>

                @if ($contacts->isEmpty())
                    <div class="px-5 py-14 text-center">
                        <div class="mx-auto flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            <flux:icon.users class="size-5" />
                        </div>
                        <h3 class="mt-4 font-semibold text-zinc-950 dark:text-white">No contacts yet</h3>
                        <p class="mt-1 text-sm text-zinc-500">Add the first contact using the form.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-start text-sm">
                            <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/60">
                                <tr>
                                    <th class="px-5 py-3">Contact</th>
                                    <th class="px-5 py-3">Company</th>
                                    <th class="px-5 py-3">Phone</th>
                                    <th class="px-5 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($contacts as $contact)
                                    <tr class="text-zinc-700 dark:text-zinc-300">
                                        <td class="px-5 py-4">
                                            <a href="{{ route('contacts.show', $contact) }}" class="font-medium text-zinc-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-400" wire:navigate>
                                                {{ $contact->full_name }}
                                            </a>
                                            <div class="mt-0.5 text-xs text-zinc-500">{{ $contact->job_title ?: ($contact->email ?: 'No job title') }}</div>
                                        </td>
                                        <td class="px-5 py-4">{{ $contact->company->name }}</td>
                                        <td class="px-5 py-4">{{ $contact->phone ?: '—' }}</td>
                                        <td class="px-5 py-4">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $contact->status === 'active',
                                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $contact->status !== 'active',
                                            ])>{{ ucfirst($contact->status) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($contacts->hasPages())
                        <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                            {{ $contacts->links() }}
                        </div>
                    @endif
                @endif
            </section>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Add contact</h2>
                <p class="mt-1 text-sm text-zinc-500">Connect a person to a customer company.</p>

                @if ($companies->isEmpty())
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Add a company before creating its contacts.
                        <a href="{{ route('companies.index') }}" class="mt-2 block font-semibold underline" wire:navigate>Go to companies</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('contacts.store') }}" class="mt-5 grid gap-4">
                        @csrf
                        @include('contacts._fields', ['contact' => null])
                        <flux:button type="submit" variant="primary" class="w-full">Add contact</flux:button>
                    </form>
                @endif
            </aside>
        </div>
    </div>
</x-layouts::app>
