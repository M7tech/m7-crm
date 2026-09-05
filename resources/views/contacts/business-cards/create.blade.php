<x-layouts::app :title="__('Scan business card')">
    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6 lg:p-8" data-card-scanner data-assets="{{ asset('ocr/v7-1') }}">
        <a href="{{ route('contacts.index') }}" class="text-emerald-700 underline" wire:navigate>Back to contacts</a>
        <h1 class="text-2xl font-semibold">Scan a business card</h1>
        <p class="text-zinc-600 dark:text-zinc-400">Your device reads the photo. Review the details before saving. Only the contact fields you approve are sent to the CRM.</p>
        <noscript>Enable JavaScript to scan on this device, or use the server scanner below.</noscript>
        <section class="space-y-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700" data-drop-zone>
            <img data-preview hidden alt="Business card preview" class="max-h-80 w-full object-contain">
            <label class="block font-medium">Choose a photo
                <input data-image type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full">
            </label>
            <label class="block font-medium">Or take a photo
                <input data-camera type="file" accept="image/*" capture="environment" class="mt-2 block w-full">
            </label>
            <p class="text-sm text-zinc-500">You can also drop an image here. JPG, PNG, WebP · up to 10 MB. Keep the card flat and well lit.</p>
            <label class="block">Card language
                <select data-language class="mt-1 block w-full rounded-lg border p-2 dark:bg-zinc-900">
                    <option value="eng+ara">Arabic and English</option>
                    <option value="eng">English</option>
                    <option value="kur">Kurdish Sorani</option>
                    <option value="kmr+eng">Kurdish Kurmanji and English</option>
                </select>
            </label>
            <p class="text-sm text-zinc-500">Keep this page open while scanning. The first scan downloads language files; later scans can reuse them. Always check names and numbers, especially on mixed-language cards.</p>
            <div class="flex flex-wrap gap-3">
                <button type="button" data-scan disabled class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Scan on this device</button>
                <button type="button" data-clear class="rounded-lg border px-4 py-2">Cancel / clear photo</button>
            </div>
            <p data-progress role="status" aria-live="polite" class="text-sm"></p>
        </section>
        <form data-review hidden action="{{ route('contacts.store') }}" method="POST" class="space-y-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
            @csrf
            <h2 class="text-xl font-semibold">Review contact details</h2>
            <p data-company-hint class="text-sm text-zinc-500"></p>
            <label class="block">Destination company
                <select name="company_id" required class="mt-1 block w-full rounded-lg border p-2 dark:bg-zinc-900">
                    <option value="">Choose a company</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </label>
            @foreach (['first_name' => 'First name', 'last_name' => 'Last name', 'job_title' => 'Job title', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                <label class="block">{{ $label }}
                    <input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" dir="auto" @required($field === 'first_name') maxlength="{{ ['first_name' => 100, 'last_name' => 100, 'job_title' => 120, 'email' => 255, 'phone' => 40][$field] }}" class="mt-1 block w-full rounded-lg border p-2 dark:bg-zinc-900">
                </label>
            @endforeach
            <label class="block">Notes (review website and address here)
                <textarea name="notes" dir="auto" maxlength="2000" rows="4" class="mt-1 block w-full rounded-lg border p-2 dark:bg-zinc-900"></textarea>
            </label>
            <input type="hidden" name="status" value="active">
            <details><summary class="cursor-pointer">Read extracted text on this device</summary><pre data-raw dir="auto" class="mt-2 whitespace-pre-wrap text-sm"></pre></details>
            <p data-save-error role="alert" class="text-red-600"></p>
            <button data-save type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Save contact and clear photo</button>
        </form>
        <p class="text-sm text-zinc-500">Photo processing stays on this device. Clearing the preview does not delete the original photo from your gallery.</p>
        <details class="text-sm">
            <summary class="cursor-pointer">Device cannot scan?</summary>
            <p class="mt-2">The optional server scanner uploads the card for processing and deletes it after saving, or after 24 hours if unsaved.</p>
            <a href="{{ route('contacts.business-cards.server') }}" class="text-emerald-700 underline" wire:navigate>Use server scanner</a>
        </details>
    </div>
</x-layouts::app>
