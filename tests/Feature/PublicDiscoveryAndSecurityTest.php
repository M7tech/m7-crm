<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicDiscoveryAndSecurityTest extends TestCase
{
    public function test_robots_file_points_to_public_only_sitemap(): void
    {
        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /dashboard')
            ->assertSee('Sitemap: '.route('seo.sitemap'));

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('contact.create'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertDontSee(route('dashboard'), false)
            ->assertDontSee(route('login'), false);
    }

    public function test_web_responses_include_baseline_security_headers(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), geolocation=(), microphone=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_secure_responses_enable_hsts(): void
    {
        $this->withServerVariables(['HTTPS' => 'on'])
            ->get(route('home', absolute: false))
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_public_pages_are_indexable_but_authentication_pages_are_not(): void
    {
        $this->get(route('home'))
            ->assertSee('<meta name="robots" content="index,follow"', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'"', false);

        $this->get(route('login'))
            ->assertSee('<meta name="robots" content="noindex,nofollow"', false);
    }
}
