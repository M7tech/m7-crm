<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLegalPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('legal.operator_name', 'M7 CRM Test Operator');
        config()->set('legal.contact_email', 'privacy@example.test');
        config()->set('legal.effective_date', '2026-10-04');
    }

    public function test_privacy_policy_is_public_and_describes_meta_and_card_processing(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('privacy@example.test')
            ->assertSee('Meta integration data')
            ->assertSee('Business-card scans')
            ->assertSee(route('legal.data-deletion'), false);
    }

    public function test_terms_are_public(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Meta connections')
            ->assertSee('M7 CRM Test Operator');
    }

    public function test_data_deletion_instructions_are_public_and_actionable(): void
    {
        $this->get(route('legal.data-deletion'))
            ->assertOk()
            ->assertSee('User Data Deletion')
            ->assertSee('privacy@example.test')
            ->assertSee('Do not send passwords, access tokens, or an App Secret.');
    }
}
