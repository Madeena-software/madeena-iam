<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $password = 'securePassword123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make($this->password),
        ]);
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertSee('Madeena IAM')
            ->assertSee('Email Address')
            ->assertSee('Password');
    }

    public function test_authenticated_user_cannot_visit_login_page(): void
    {
        $response = $this->actingAs($this->user)->get('/login');

        $response->assertRedirect('/');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => $this->password,
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated('web');
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('web');
    }

    public function test_login_validation_rules(): void
    {
        $response = $this->post('/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest('web');
    }

    public function test_user_can_logout(): void
    {
        $response = $this->actingAs($this->user, 'web')->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest('web');
    }
}
