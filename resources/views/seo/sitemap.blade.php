{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ([
        ['route' => 'home', 'priority' => '1.0', 'frequency' => 'weekly'],
        ['route' => 'legal.privacy', 'priority' => '0.4', 'frequency' => 'monthly'],
        ['route' => 'legal.terms', 'priority' => '0.4', 'frequency' => 'monthly'],
        ['route' => 'legal.data-deletion', 'priority' => '0.4', 'frequency' => 'monthly'],
    ] as $page)
        <url>
            <loc>{{ route($page['route']) }}</loc>
            <changefreq>{{ $page['frequency'] }}</changefreq>
            <priority>{{ $page['priority'] }}</priority>
        </url>
    @endforeach
</urlset>
