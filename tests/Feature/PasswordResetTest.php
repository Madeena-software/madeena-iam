<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('old-secure-password123'),
        ]);
    }

    public function test_password_reset_page_can_be_rendered(): void
    {
        $token = Password::broker()->createToken($this->user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $this->user->email]));

        $response->assertStatus(200)
            ->assertViewIs('auth.reset-password')
            ->assertSee($token)
            ->assertSee($this->user->email);
    }

    public function test_password_reset_validation_errors(): void
    {
        // 1. Password confirmation mismatch
        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertSessionHasErrors(['password']);

        // 2. Invalid email format
        $response2 = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'not-an-email',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response2->assertSessionHasErrors(['email']);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertTrue(Hash::check('old-secure-password123', $this->user->fresh()->password));
    }

    public function test_password_reset_successful(): void
    {
        $token = Password::broker()->createToken($this->user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password123', $this->user->fresh()->password));
    }

    public function test_password_reset_fails_with_nonexistent_email(): void
    {
        $token = Password::broker()->createToken($this->user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'nonexistent@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertTrue(Hash::check('old-secure-password123', $this->user->fresh()->password));
    }
}
