<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private OauthClient $client;

    private string $clientSecret = 'test-secret-value-abc';

    protected function setUp(): void
    {
        parent::setUp();

        // Run passport installation tasks for API personal token generation
        $this->artisan('passport:keys', [
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'Personal Access Client',
            '--provider' => 'users',
            '--no-interaction' => true,
        ]);

        // Create a test client application
        $this->client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Client App',
            'secret' => Hash::make($this->clientSecret),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => 'password,authorization_code',
            'revoked' => false,
            'is_active' => true,
        ]);
    }

    public function test_api_registration_succeeds_with_valid_credentials(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'securePassword123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User registered successfully. Awaiting approval.',
                'status' => 'pending_approval',
            ]);

        // Assert user exists in database
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);

        // Assert user's client association is pending_approval
        $pivot = $user->clients()->wherePivot('client_id', $this->client->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(UserStatus::PENDING_APPROVAL, $pivot->pivot->status);
    }

    public function test_api_registration_fails_due_to_validation_errors(): void
    {
        // 1. Taken email
        User::factory()->create(['email' => 'john@example.com']);
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'securePassword123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 2. Short password
        $payload['email'] = 'new@example.com';
        $payload['password'] = 'short';
        $response = $this->postJson('/api/v1/auth/register', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_api_login_succeeds_for_approved_user(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => false,
        ]);

        $payload = [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'status',
                ],
                'sso_session_id',
            ])
            ->assertJson([
                'user' => [
                    'email' => 'jane@example.com',
                    'status' => 'approved',
                ],
            ]);
    }

    public function test_api_login_fails_for_pending_approval_user(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
        ]);

        $payload = [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User account is not approved or is suspended for this application',
            ]);
    }

    public function test_api_login_fails_for_suspended_user(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::SUSPENDED->value,
            'is_blocked' => false,
        ]);

        $payload = [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User account is not approved or is suspended for this application',
            ]);
    }

    public function test_api_login_fails_for_blocked_user(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => true,
        ]);

        $payload = [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User is blocked from accessing this application',
            ]);
    }

    public function test_api_login_fails_for_unassociated_client(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User is not authorized for this application',
            ]);
    }

    public function test_api_login_fails_for_invalid_client_secret(): void
    {
        $payload = [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => 'invalid-secret-key-999',
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid client credentials',
            ]);
    }

    public function test_user_profile_retrieval_returns_correct_fields(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => false,
            'client_app_user_id' => 'client-user-999',
        ]);

        // Login first to get token
        $payload = [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $loginResponse = $this->postJson('/api/v1/auth/login', $payload);
        $token = $loginResponse->json('access_token');

        // Request profile with Bearer Token
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'status' => 'approved',
                'avatar_url' => null,
                'app_registration' => [
                    'status' => 'approved',
                    'is_blocked' => false,
                    'client_app_user_id' => 'client-user-999',
                ],
            ]);
    }
}
