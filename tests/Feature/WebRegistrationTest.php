<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Mail\NewUserRegistrationAdminMail;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private OauthClient $client;

    private string $clientSecret = 'test-secret-value-abc';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('passport:keys', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Client App',
            'secret' => Hash::make($this->clientSecret),
            'redirect_uris' => ['https://testapp.local/callback'],
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);
    }

    public function test_register_page_renders_successfully(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200)
            ->assertSee('register')
            ->assertSee('Name')
            ->assertSee('Email Address')
            ->assertSee('Password')
            ->assertSee('Confirm Password');
    }

    public function test_authenticated_user_cannot_visit_register_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/register');

        $response->assertRedirect('/');
    }

    public function test_user_can_register_standalone_successfully(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'password' => 'securePassword123',
            'password_confirmation' => 'securePassword123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated('web');

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
        ]);

        // Standalone registration should not trigger admin emails or client pivots
        Mail::assertNothingQueued();
    }

    public function test_user_can_register_via_sso_and_notifies_admin(): void
    {
        Mail::fake();

        // Create super_admin role and user
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole($role);

        $queryParams = [
            'client_id' => $this->client->id,
            'redirect_uri' => 'https://testapp.local/callback',
            'response_type' => 'code',
            'state' => 'randomstate123',
            'scope' => 'read',
        ];

        // Access register page with query params
        $response = $this->get('/register?' . http_build_query($queryParams));
        $response->assertStatus(200);

        // Submit form including query params as hidden fields/POST parameters
        $postData = array_merge([
            'name' => 'SSO User',
            'email' => 'sso.user@example.com',
            'password' => 'securePassword123',
            'password_confirmation' => 'securePassword123',
        ], $queryParams);

        $response = $this->post('/register', $postData);

        // Assert correct redirect to authorize endpoint
        $response->assertRedirect(route('passport.authorizations.authorize', $queryParams));
        $this->assertAuthenticated('web');

        // Verify database records
        $user = User::where('email', 'sso.user@example.com')->first();
        $this->assertNotNull($user);

        // Assert that the client_user pivot was created with pending approval
        $pivot = $user->clients()->wherePivot('client_id', $this->client->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(UserStatus::PENDING_APPROVAL, $pivot->pivot->status);

        // Assert admin notification mail was queued
        Mail::assertQueued(NewUserRegistrationAdminMail::class, function ($mail) use ($admin, $user) {
            return $mail->hasTo($admin->email) &&
                   $mail->user->id === $user->id &&
                   $mail->client->id === $this->client->id;
        });
    }

    public function test_registration_validation_rules(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest('web');
    }
}
