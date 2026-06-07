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

class AuditLogTest extends TestCase
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
            'redirect_uris' => ['https://testapp.local/callback'],
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Test successful API login logs a success record.
     */
    public function test_successful_api_login_logs_success(): void
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

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => true,
            'status' => 'success',
        ]);
    }

    /**
     * Test successful web login logs a success record.
     */
    public function test_successful_web_login_logs_success(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Simulating the user being redirected from the authorize page so there's an intended URL
        session()->put('url.intended', 'https://sso.mhcsgo.cloud/oauth/authorize?client_id='.$this->client->id);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => true,
            'status' => 'success',
        ]);
    }

    /**
     * Test failed API login (invalid password) logs failed_password.
     */
    public function test_failed_api_login_logs_failed_password(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrongpassword',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(401);

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'failed_password',
        ]);
    }

    /**
     * Test failed API login (non-existent user) logs failed_password with null user fields.
     */
    public function test_failed_api_login_non_existent_user_logs_failed_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'doesnotexist@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(401);

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => null,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'failed_password',
        ]);
    }

    /**
     * Test failed web login (invalid password) logs failed_password.
     */
    public function test_failed_web_login_logs_failed_password(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        session()->put('url.intended', 'https://sso.mhcsgo.cloud/oauth/authorize?client_id='.$this->client->id);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'failed_password',
        ]);
    }

    /**
     * Test failed web login (non-existent user) logs failed_password.
     */
    public function test_failed_web_login_non_existent_user_logs_failed_password(): void
    {
        session()->put('url.intended', 'https://sso.mhcsgo.cloud/oauth/authorize?client_id='.$this->client->id);

        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => null,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'failed_password',
        ]);
    }

    /**
     * Test invalid client credentials logs invalid_client.
     */
    public function test_invalid_client_api_login_logs_invalid_client(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => 'invalid-secret',
        ]);

        $response->assertStatus(401);

        $this->assertDatabaseHas('authentication_logs', [
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'invalid_client',
        ]);
    }

    /**
     * Test unauthorized application logs blocked_app.
     */
    public function test_unauthorized_application_api_login_logs_blocked_app(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Do not attach user to client, which means unauthorized

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'blocked_app',
        ]);
    }

    /**
     * Test suspended application status logs blocked_app.
     */
    public function test_suspended_application_api_login_logs_blocked_app(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::PENDING_APPROVAL->value, // Not approved / suspended
            'is_blocked' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'blocked_app',
        ]);
    }

    /**
     * Test blocked user for application logs blocked_app.
     */
    public function test_blocked_user_api_login_logs_blocked_app(): void
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

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'blocked_app',
        ]);
    }

    /**
     * Test middleware blocks and logs blocked_app when client access is checked for authorized user but blocked client.
     */
    public function test_middleware_client_access_logs_blocked_app(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        // User is blocked for this client
        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => true,
        ]);

        $response = $this->actingAs($user, 'web')
            ->get('/oauth/authorize?client_id='.$this->client->id.'&redirect_uri=https://testapp.local/callback&response_type=code');

        $response->assertStatus(403);

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'blocked_app',
        ]);
    }

    /**
     * Test prompt=none redirection checks and logs blocked_app.
     */
    public function test_prompt_none_blocked_app_logs_blocked_app(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Not attached to client
        $response = $this->actingAs($user, 'web')
            ->get('/oauth/authorize?client_id='.$this->client->id.'&redirect_uri=https://testapp.local/callback&response_type=code&prompt=none');

        $response->assertRedirect('https://testapp.local/callback?error=access_denied');

        $this->assertDatabaseHas('authentication_logs', [
            'authenticatable_id' => $user->id,
            'client_id' => $this->client->id,
            'login_successful' => false,
            'status' => 'blocked_app',
        ]);
    }
}
