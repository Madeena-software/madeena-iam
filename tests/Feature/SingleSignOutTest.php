<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AuthenticationLog;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SingleSignOutTest extends TestCase
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

    public function test_api_logout_revokes_tokens_and_logs_audit_trail(): void
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

        // Create a login audit trail log
        AuthenticationLog::create([
            'authenticatable_type' => get_class($user),
            'authenticatable_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Symfony',
            'login_at' => now(),
        ]);

        // Create access token
        $tokenResult = $user->createToken($this->client->name);
        $token = $tokenResult->accessToken;
        $tokenId = $tokenResult->token->id;

        // Verify token is active initially
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenId,
            'revoked' => false,
        ]);

        // Request API logout
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        // Assert token is revoked
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenId,
            'revoked' => true,
        ]);

        // Assert the audit trail has been updated
        $log = AuthenticationLog::where('authenticatable_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->logout_at);
    }

    public function test_web_logout_destroys_session_revokes_tokens_and_logs_audit_trail(): void
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

        AuthenticationLog::create([
            'authenticatable_type' => get_class($user),
            'authenticatable_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Symfony',
            'login_at' => now(),
        ]);

        // Create access token
        $tokenResult = $user->createToken($this->client->name);
        $tokenId = $tokenResult->token->id;

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenId,
            'revoked' => false,
        ]);

        // Log the user in to web guard
        $this->actingAs($user, 'web');
        $this->assertAuthenticatedAs($user, 'web');

        // Request web logout
        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest('web');

        // Assert token is revoked
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenId,
            'revoked' => true,
        ]);

        // Assert the audit trail has been updated
        $log = AuthenticationLog::where('authenticatable_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->logout_at);
    }

    public function test_web_logout_via_get_route(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($user, 'web');
        $this->assertAuthenticatedAs($user, 'web');

        $response = $this->get('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest('web');
    }
}
