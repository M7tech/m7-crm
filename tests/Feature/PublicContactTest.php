<?php

namespace Tests\Feature;

use App\Mail\PublicContactMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicContactTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('support.email', 'support@example.test');
    }

    public function test_contact_page_is_public_and_can_show_plan_interest(): void
    {
        $this->get(route('contact.create', ['plan' => 'growth']))
            ->assertOk()
            ->assertSee('Talk to the M7 CRM team')
            ->assertSee('Plan interest:')
            ->assertSee('Growth')
            ->assertSee('name="plan" value="growth"', false);
    }

    public function test_valid_contact_request_is_queued_to_support(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.test',
            'company' => 'Example Company',
            'topic' => 'sales',
            'plan' => 'growth',
            'message' => 'Please tell me more about the Growth plan.',
            'website' => '',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('status')
            ->assertRedirect(route('contact.create'));

        Mail::assertQueued(PublicContactMessage::class, function (PublicContactMessage $mail): bool {
            return $mail->hasTo('support@example.test')
                && $mail->hasReplyTo('visitor@example.test')
                && $mail->details['plan'] === 'growth';
        });
    }

    public function test_contact_request_is_validated(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'topic' => 'invalid',
            'message' => '',
        ])->assertSessionHasErrors(['name', 'email', 'topic', 'message']);

        Mail::assertNothingQueued();
    }

    public function test_honeypot_submission_is_discarded_without_email(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.test',
            'topic' => 'sales',
            'message' => 'Automated message',
            'website' => 'https://spam.example',
        ])->assertSessionHasNoErrors()->assertRedirect(route('contact.create'));

        Mail::assertNothingQueued();
    }
}
