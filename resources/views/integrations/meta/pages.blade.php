<x-layouts::app :title="__('Select Facebook Page')">
    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div><h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">Select a Facebook Page</h1><p class="mt-1 text-zinc-500">Choose the Page whose Lead Ads should create CRM leads.</p></div>
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @forelse ($pages as $page)
                <form method="POST" action="{{ route('integrations.meta.page', $integration) }}" class="flex items-center justify-between gap-4 border-b border-zinc-100 py-4 first:pt-0 last:border-0 last:pb-0 dark:border-zinc-800">
                    @csrf
                    <input type="hidden" name="selection" value="{{ $selection }}">
                    <input type="hidden" name="page_id" value="{{ $page['id'] }}">
                    <div><p class="font-medium text-zinc-950 dark:text-white">{{ $page['name'] }}</p><p class="text-xs text-zinc-500">Page ID {{ $page['id'] }}</p></div>
                    <flux:button type="submit" variant="primary">Connect</flux:button>
                </form>
            @empty
                <p class="py-8 text-center text-zinc-500">No manageable Facebook Pages were returned. Check your Page role and app permissions.</p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
