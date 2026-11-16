<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_is_authenticated(): void
    {
        $response = $this->post(route('register'), [
            'fullname' => 'Test Student',
            'email' => 'student@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'fullname' => 'Test Student',
            'email' => 'student@example.test',
        ]);
    }

    public function test_registration_rejects_duplicate_email_addresses(): void
    {
        User::create([
            'fullname' => 'Existing Student',
            'email' => 'existing@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response = $this->from(route('register'))->post(route('register'), [
            'fullname' => 'Another Student',
            'email' => 'existing@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_a_strong_confirmed_password(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'fullname' => 'Test Student',
            'email' => 'student@example.test',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_registered_user_can_sign_in_and_sign_out(): void
    {
        $user = User::create([
            'fullname' => 'Test Student',
            'email' => 'student@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->post(route('login'), [
            'email' => 'student@example.test',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_authenticate_a_user(): void
    {
        User::create([
            'fullname' => 'Test Student',
            'email' => 'student@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'student@example.test',
            'password' => 'incorrect-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_away_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
