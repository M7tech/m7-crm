<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_a_public_product_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('A practical CRM for Iraqi sales teams')
            ->assertSee('Starter')
            ->assertSee('Growth')
            ->assertSee(route('register'), false)
            ->assertSee(route('legal.privacy'), false);
    }

    public function test_signed_in_users_go_from_home_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
