<x-layouts::app :title="__('Preview contact import')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <a href="{{ route('contacts.import.create') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Upload another file</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Review {{ $import->original_name }}</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Nothing has been imported yet. Review the results before confirming.</p>
        </div>

        @error('import')
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
        @enderror

        <div class="grid gap-3 sm:grid-cols-4">
            @foreach ([
                ['Total', $summary['total'], 'text-zinc-950 dark:text-white'],
                ['Ready', $summary['ready'], 'text-emerald-700 dark:text-emerald-400'],
                ['Duplicates', $summary['duplicate'], 'text-amber-700 dark:text-amber-400'],
                ['Invalid', $summary['invalid'], 'text-red-700 dark:text-red-400'],
            ] as [$label, $value, $color])
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-zinc-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $color }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/60">
                        <tr>
                            <th class="px-4 py-3">Row</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Company</th>
                            <th class="px-4 py-3">Email / phone</th>
                            <th class="px-4 py-3">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($rows as $row)
                            <tr class="align-top text-zinc-700 dark:text-zinc-300">
                                <td class="px-4 py-3 text-zinc-500">{{ $row['row_number'] }}</td>
                                <td class="px-4 py-3 font-medium text-zinc-950 dark:text-white">{{ trim(($row['data']['first_name'] ?? '').' '.($row['data']['last_name'] ?? '')) ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $row['data']['company'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row['data']['email'] ?? ($row['data']['phone'] ?? '—') }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $row['status'] === 'ready',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $row['status'] === 'duplicate',
                                        'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => $row['status'] === 'invalid',
                                    ])>{{ ucfirst($row['status']) }}</span>
                                    <p class="mt-1 max-w-sm text-xs text-zinc-500">{{ $row['message'] }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <form method="POST" action="{{ route('contacts.import.store') }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            <input type="hidden" name="import_id" value="{{ $import->id }}" />
            <input type="hidden" name="token" value="{{ $token }}" />
            <fieldset>
                <legend class="font-semibold text-zinc-950 dark:text-white">When an email or phone matches an existing contact</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <input type="radio" name="duplicate_strategy" value="skip" checked class="mt-1" />
                        <span><span class="block font-medium text-zinc-950 dark:text-white">Skip duplicates</span><span class="text-sm text-zinc-500">Keep existing contacts unchanged.</span></span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <input type="radio" name="duplicate_strategy" value="update" class="mt-1" />
                        <span><span class="block font-medium text-zinc-950 dark:text-white">Update duplicates</span><span class="text-sm text-zinc-500">Replace matching contact fields with CSV values.</span></span>
                    </label>
                </div>
            </fieldset>
            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button :href="route('contacts.import.create')" variant="ghost" wire:navigate>Cancel</flux:button>
                <flux:button type="submit" variant="primary">Import contacts</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
