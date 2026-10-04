<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PublicSitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = [
            ['route' => 'home', 'priority' => '1.0', 'frequency' => 'weekly'],
            ['route' => 'contact.create', 'priority' => '0.7', 'frequency' => 'monthly'],
            ['route' => 'legal.privacy', 'priority' => '0.4', 'frequency' => 'monthly'],
            ['route' => 'legal.terms', 'priority' => '0.4', 'frequency' => 'monthly'],
            ['route' => 'legal.data-deletion', 'priority' => '0.4', 'frequency' => 'monthly'],
        ];

        $urls = collect($pages)->map(function (array $page): string {
            $location = htmlspecialchars(route($page['route']), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            return "    <url>\n"
                ."        <loc>{$location}</loc>\n"
                ."        <changefreq>{$page['frequency']}</changefreq>\n"
                ."        <priority>{$page['priority']}</priority>\n"
                .'    </url>';
        })->implode("\n");

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .$urls."\n"
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
