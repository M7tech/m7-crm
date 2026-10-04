@props(['compact' => false])

<form method="POST" action="{{ route('locale.update') }}" class="shrink-0">
    @csrf
    <label class="sr-only" for="locale-{{ $compact ? 'compact' : 'full' }}">{{ __('Language') }}</label>
    <select
        id="locale-{{ $compact ? 'compact' : 'full' }}"
        name="locale"
        onchange="this.form.submit()"
        class="rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm text-zinc-800 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
        aria-label="{{ __('Language') }}"
    >
        @foreach (config('locales.labels') as $locale => $label)
            <option value="{{ $locale }}" @selected(app()->getLocale() === $locale)>{{ $label }}</option>
        @endforeach
    </select>
</form>
