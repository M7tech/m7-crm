<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'company_name' => 'Atlas Trading',
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('tenants', ['name' => 'Atlas Trading']);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertSame(UserRole::CompanyAdmin, $user->role);
        $this->assertNotNull($user->tenant_id);
        $this->assertSame('Atlas Trading', Tenant::findOrFail($user->tenant_id)->name);
    }

    public function test_company_name_is_required_for_registration(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('company_name');
        $this->assertGuest();
    }
}
