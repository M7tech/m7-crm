<x-layouts::app :title="$contact->full_name">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to contacts</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $contact->full_name }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $contact->job_title ?: 'Contact' }} at {{ $contact->company->name }}</p>
            </div>
            <div class="flex gap-2">
                @can('update', $contact)
                    <flux:button :href="route('contacts.edit', $contact)" variant="primary" wire:navigate>Edit contact</flux:button>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <dl class="grid sm:grid-cols-2">
                @foreach ([
                    'Company' => $contact->company->name,
                    'Status' => ucfirst($contact->status),
                    'Email' => $contact->email ?: '—',
                    'Phone' => $contact->phone ?: '—',
                    'Job title' => $contact->job_title ?: '—',
                    'Added' => $contact->created_at->format('M j, Y'),
                ] as $label => $value)
                    <div class="border-b border-zinc-100 px-5 py-4 last:border-b-0 sm:odd:border-e dark:border-zinc-800">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($contact->notes)
                <div class="px-5 py-5">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Notes</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $contact->notes }}</p>
                </div>
            @endif
        </section>

        @can('delete', $contact)
            <section class="rounded-2xl border border-red-200 bg-white p-5 dark:border-red-900 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Delete contact</h2>
                <p class="mt-1 text-sm text-zinc-500">This removes the contact permanently.</p>
                <form method="POST" action="{{ route('contacts.destroy', $contact) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger">Delete contact</flux:button>
                </form>
            </section>
        @endcan
    </div>
</x-layouts::app>
