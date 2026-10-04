@php
    $topics = [
        'sales' => 'Sales and plans',
        'support' => 'Product support',
        'privacy' => 'Privacy question',
        'other' => 'Other',
    ];
    $planLabel = $selectedPlan ? config("plans.plans.{$selectedPlan}.label") : null;
@endphp
<x-layouts::public title="Contact">
    <div class="grid gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(30rem,1.2fr)] lg:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400">Contact</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Talk to the M7 CRM team</h1>
            <p class="mt-5 text-lg leading-8 text-zinc-600 dark:text-zinc-300">Ask about plans, setup, integrations, or product support. Your message goes to the support queue and is not added to a customer workspace.</p>
            <div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="font-semibold">Privacy and deletion requests</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">For a formal data request, use the <a href="{{ route('legal.data-deletion') }}" class="text-emerald-700 underline dark:text-emerald-400">data deletion instructions</a> so we can verify the account or workspace safely.</p>
            </div>
        </div>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-7 dark:border-zinc-800 dark:bg-zinc-900">
            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
            @endif

            @if ($planLabel)
                <p class="mb-5 rounded-xl bg-zinc-100 px-4 py-3 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">Plan interest: <strong>{{ $planLabel }}</strong></p>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" class="grid gap-5">
                @csrf
                @if ($selectedPlan)<input type="hidden" name="plan" value="{{ $selectedPlan }}">@endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-medium">Name
                        <input name="name" value="{{ old('name') }}" required autocomplete="name" maxlength="100" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                        @error('name')<span class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-sm font-medium">Email
                        <input name="email" value="{{ old('email') }}" type="email" required autocomplete="email" maxlength="255" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                        @error('email')<span class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
                    </label>
                </div>

                <label class="block text-sm font-medium">Company <span class="font-normal text-zinc-500">(optional)</span>
                    <input name="company" value="{{ old('company') }}" autocomplete="organization" maxlength="160" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                    @error('company')<span class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
                </label>

                <label class="block text-sm font-medium">Topic
                    <select name="topic" required class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                        @foreach ($topics as $value => $label)<option value="{{ $value }}" @selected(old('topic', $selectedPlan ? 'sales' : 'support') === $value)>{{ $label }}</option>@endforeach
                    </select>
                    @error('topic')<span class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
                </label>

                <label class="block text-sm font-medium">Message
                    <textarea name="message" required maxlength="4000" rows="7" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">{{ old('message') }}</textarea>
                    @error('message')<span class="mt-1 block text-sm text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
                </label>

                <div aria-hidden="true" class="absolute -start-[10000px] top-auto size-px overflow-hidden">
                    <label>Website<input name="website" value="" tabindex="-1" autocomplete="off"></label>
                </div>

                <p class="text-xs leading-5 text-zinc-500">By sending this form, you agree that we may use the submitted details to respond as described in our <a href="{{ route('legal.privacy') }}" class="underline">Privacy Policy</a>.</p>
                <button type="submit" class="rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50">Send message</button>
            </form>
        </section>
    </div>
</x-layouts::public>
