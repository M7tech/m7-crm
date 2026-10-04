<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>
<meta name="description" content="{{ $description ?? __('A secure CRM for customer relationships, sales pipelines, follow-ups, and Meta leads.') }}" />
<meta property="og:title" content="{{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}" />
<meta property="og:description" content="{{ $description ?? __('A secure CRM for customer relationships, sales pipelines, follow-ups, and Meta leads.') }}" />
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ request()->url() }}" />
<meta name="robots" content="{{ $robots ?? (auth()->check() ? 'noindex,nofollow' : 'index,follow') }}" />
<link rel="canonical" href="{{ $canonical ?? request()->url() }}" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
