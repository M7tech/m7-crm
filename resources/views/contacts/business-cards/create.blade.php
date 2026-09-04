<x-layouts::app :title="__('Scan business card')">
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to contacts</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Scan a business card</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Take a clear photo or upload an image. Arabic, English, Kurdish Sorani, and Kurdish Kurmanji can be read together.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @unless ($scannerAvailable)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <p class="font-semibold">Local scanner unavailable</p>
                <p class="mt-1">The application must be redeployed with its bundled OCR engine before cards can be scanned.</p>
            </div>
        @endunless

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <form method="POST" action="{{ route('contacts.business-cards.store') }}" enctype="multipart/form-data" class="grid gap-5" x-data="{
                    preview: null,
                    dragging: false,
                    selectFile(file, assign = false) {
                        if (!file) return;
                        if (assign) {
                            const transfer = new DataTransfer();
                            transfer.items.add(file);
                            this.$refs.cardImage.files = transfer.files;
                        }
                        const reader = new FileReader();
                        reader.onload = event => this.preview = event.target.result;
                        reader.readAsDataURL(file);
                    }
                }">
                    @csrf

                    <div
                        class="relative overflow-hidden rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50 p-5 text-center transition dark:border-zinc-600 dark:bg-zinc-800/50"
                        :class="dragging ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30' : ''"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="dragging = false; selectFile($event.dataTransfer.files[0], true)"
                    >
                        <img x-show="preview" :src="preview" alt="Business card preview" class="mx-auto mb-5 max-h-80 w-full rounded-xl object-contain" x-cloak>
                        <div x-show="!preview" class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            <flux:icon.camera class="size-7" />
                        </div>
                        <h2 class="mt-4 font-semibold text-zinc-950 dark:text-white">Photograph or drop the card here</h2>
                        <p class="mt-1 text-sm text-zinc-500">Use good light, keep all four edges visible, and avoid glare.</p>
                        <label class="mt-5 inline-flex min-h-10 cursor-pointer items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Choose image or camera
                            <input
                                x-ref="cardImage"
                                type="file"
                                name="card_image"
                                accept="image/jpeg,image/png,image/webp"
                                capture="environment"
                                class="sr-only"
                                required
                                @change="selectFile($event.target.files[0])"
                            >
                        </label>
                        <p class="mt-3 text-xs text-zinc-500">JPG, PNG, or WebP · maximum 10 MB</p>
                    </div>

                    @error('card_image')
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="rounded-xl bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-300">
                        <p class="font-medium text-zinc-900 dark:text-white">Privacy</p>
                        <p class="mt-1">The image is read only by this server. After you approve and save the contact, the image and temporary OCR result are deleted immediately. Unsaved scans expire after 24 hours.</p>
                    </div>

                    <flux:button type="submit" variant="primary" class="w-full" :disabled="! $scannerAvailable">Scan business card</flux:button>
                </form>
            </section>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">Recent scans</h2>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($scans as $scan)
                        <a href="{{ route('contacts.business-cards.show', $scan) }}" class="block px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:navigate>
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate text-sm font-medium text-zinc-950 dark:text-white">{{ $scan->contact?->full_name ?: $scan->original_name }}</p>
                                <span @class([
                                    'rounded-full px-2 py-1 text-xs font-medium capitalize',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => in_array($scan->status, ['completed', 'saved'], true),
                                    'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' => $scan->status === 'failed',
                                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => in_array($scan->status, ['queued', 'processing'], true),
                                ])>{{ $scan->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">{{ $scan->created_at->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-zinc-500">No business cards scanned yet.</p>
                    @endforelse
                </div>
            </aside>
        </div>
    </div>
</x-layouts::app>
