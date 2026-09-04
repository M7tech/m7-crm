<x-layouts::app :title="__('Review business card')">
    <div
        class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8"
        @if (in_array($scan->status, ['queued', 'processing'], true)) x-data x-init="setTimeout(() => window.location.reload(), 4000)" @endif
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('contacts.business-cards.create') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Business-card scanner</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Review business card</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">Check every extracted field before saving it as a CRM contact.</p>
            </div>
            <span @class([
                'w-fit rounded-full px-3 py-1.5 text-sm font-semibold capitalize',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => in_array($scan->status, ['completed', 'saved'], true),
                'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' => $scan->status === 'failed',
                'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => in_array($scan->status, ['queued', 'processing'], true),
            ])>{{ $scan->status }}</span>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @if (in_array($scan->status, ['queued', 'processing'], true))
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center dark:border-amber-900 dark:bg-amber-950/30">
                <div class="mx-auto size-9 animate-spin rounded-full border-4 border-amber-200 border-t-amber-700 dark:border-amber-900 dark:border-t-amber-300"></div>
                <h2 class="mt-4 font-semibold text-amber-950 dark:text-amber-100">Reading the card</h2>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">This page refreshes automatically. You can safely leave and return later.</p>
            </section>
        @elseif ($scan->status === 'failed')
            <section class="rounded-2xl border border-red-200 bg-red-50 p-6 dark:border-red-900 dark:bg-red-950/30">
                <h2 class="font-semibold text-red-950 dark:text-red-100">The card could not be read</h2>
                <p class="mt-1 text-sm text-red-800 dark:text-red-200">Try again, or delete this scan and upload a sharper image with less glare.</p>
                <form method="POST" action="{{ route('contacts.business-cards.retry', $scan) }}" class="mt-4">
                    @csrf
                    <flux:button type="submit" variant="primary">Retry scan</flux:button>
                </form>
            </section>
        @elseif ($scan->status === 'saved')
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950/30">
                <h2 class="font-semibold text-emerald-950 dark:text-emerald-100">Contact saved</h2>
                @if ($scan->contact)
                    <a href="{{ route('contacts.show', $scan->contact) }}" class="mt-2 inline-block text-sm font-semibold text-emerald-800 underline dark:text-emerald-200" wire:navigate>Open {{ $scan->contact->full_name }}</a>
                @endif
            </section>
        @elseif ($scan->status === 'completed')
            <div class="grid gap-6 lg:grid-cols-[minmax(18rem,0.85fr)_minmax(0,1.15fr)]">
                <section class="h-fit rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if ($scan->image_path)
                        <img src="{{ route('contacts.business-cards.image', $scan) }}" alt="Uploaded business card" class="max-h-[34rem] w-full rounded-xl object-contain" loading="eager">
                    @endif

                    <dl class="mt-4 grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Printed company</dt>
                            <dd class="mt-1 text-zinc-900 dark:text-zinc-100" dir="auto">{{ data_get($scan->extracted_data, 'company_name') ?: 'Not detected' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Detected languages</dt>
                            <dd class="mt-2 flex flex-wrap gap-2">
                                @forelse (data_get($scan->extracted_data, 'detected_languages', []) as $language)
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $language }}</span>
                                @empty
                                    <span class="text-zinc-500">Not detected</span>
                                @endforelse
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">Contact details</h2>
                    <p class="mt-1 text-sm text-zinc-500">OCR can make mistakes. Correct anything before saving.</p>

                    @if ($companies->isEmpty())
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            Add a CRM company before saving this contact.
                            <a href="{{ route('companies.index') }}" class="mt-2 block font-semibold underline" wire:navigate>Go to companies</a>
                        </div>
                    @else
                        <form method="POST" action="{{ route('contacts.business-cards.save', $scan) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                            @csrf

                            <div class="sm:col-span-2">
                                <label for="company_id" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">CRM company</label>
                                <select id="company_id" name="company_id" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                                    <option value="">Select a company</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}" @selected((string) old('company_id', $suggestedCompanyId) === (string) $company->id)>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                                @error('company_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            @foreach ([
                                'first_name' => ['First name', true, 'text'],
                                'last_name' => ['Last name', false, 'text'],
                                'job_title' => ['Job title', false, 'text'],
                                'phone' => ['Phone', false, 'text'],
                                'email' => ['Email', false, 'email'],
                            ] as $field => [$label, $required, $type])
                                <div @class(['sm:col-span-2' => $field === 'email'])>
                                    <label for="{{ $field }}" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</label>
                                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $suggested[$field]) }}" @required($required) dir="auto" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                                    @error($field) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                </div>
                            @endforeach

                            <div class="sm:col-span-2">
                                <label for="status" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Status</label>
                                <select id="status" name="status" required class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                                    <option value="active" @selected(old('status', $suggested['status']) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $suggested['status']) === 'inactive')>Inactive</option>
                                </select>
                                @error('status') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="notes" class="mb-2 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Notes</label>
                                <textarea id="notes" name="notes" rows="5" dir="auto" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">{{ old('notes', $suggested['notes']) }}</textarea>
                                @error('notes') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <flux:button type="submit" variant="primary" class="sm:col-span-2">Save contact</flux:button>
                        </form>
                    @endif
                </section>
            </div>
        @endif

        @if ($scan->status !== 'saved')
            <section class="rounded-2xl border border-red-200 bg-white p-5 dark:border-red-900 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Delete scan</h2>
                <p class="mt-1 text-sm text-zinc-500">This permanently removes the private image and extracted result.</p>
                <form method="POST" action="{{ route('contacts.business-cards.destroy', $scan) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger">Delete scan</flux:button>
                </form>
            </section>
        @endif
    </div>
</x-layouts::app>
