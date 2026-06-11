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

class ClientUserLinkTest extends TestCase
{
    use RefreshDatabase;

    private OauthClient $client;

    private string $clientSecret = 'link-test-secret-123';

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

        $this->client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Link App Client',
            'secret' => Hash::make($this->clientSecret),
            'redirect_uris' => 'https://localhost/callback',
            'grant_types' => 'password,authorization_code',
            'revoked' => false,
            'is_active' => true,
        ]);
    }

    public function test_link_updates_client_app_user_id_successfully(): void
    {
        $user = User::factory()->create();
        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => false,
        ]);

        // Login to get token
        $loginPayload = [
            'email' => $user->email,
            'password' => 'password', // UserFactory uses 'password'
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $loginResponse = $this->postJson('/api/v1/auth/login', $loginPayload);
        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/client-user/link', [
                'client_app_user_id' => 'external-user-id-abc-123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Client app user linked successfully',
                'client_app_user_id' => 'external-user-id-abc-123',
            ]);

        $pivot = $user->clients()->wherePivot('client_id', $this->client->id)->first()->pivot;
        $this->assertEquals('external-user-id-abc-123', $pivot->client_app_user_id);
    }

    public function test_link_fails_without_authentication(): void
    {
        $response = $this->patchJson('/api/v1/client-user/link', [
            'client_app_user_id' => 'external-user-id-abc-123',
        ]);

        $response->assertStatus(401);
    }

    public function test_link_fails_when_user_not_registered_for_client(): void
    {
        $user = User::factory()->create();

        // Create another client
        $anotherClient = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Another App Client',
            'secret' => Hash::make($this->clientSecret),
            'redirect_uris' => 'https://localhost/callback',
            'grant_types' => 'password,authorization_code',
            'revoked' => false,
            'is_active' => true,
        ]);

        // Attach user to another client, not this.client
        $user->clients()->attach($anotherClient->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => false,
        ]);

        // Login to get token (using another client)
        $loginPayload = [
            'email' => $user->email,
            'password' => 'password',
            'client_id' => $anotherClient->id,
            'client_secret' => $this->clientSecret,
        ];

        $loginResponse = $this->postJson('/api/v1/auth/login', $loginPayload);
        $token = $loginResponse->json('access_token');

        // We make the request using the token from anotherClient, but our API will read that token's client ID.
        // Since the user is not registered for that client ID in $this->client?
        // Wait, the route gets the client ID from the token: $token->client_id which is $anotherClient->id.
        // But the user is registered for $anotherClient.
        // So let's test if user tries to call link with a token, but the token's client is not in their registered clients?
        // Wait! In this case, since user IS attached to $anotherClient, the pivot will exist for $anotherClient and succeed.
        // To test "User is not registered for this application", we can use a token from a client, but then detach the client from user, or use a personal access token where client_id is the personal access client.
        // Ah! If they login with personal access client, they might not have a client_user pivot for the personal access client.
        // Let's create a token directly for the user using `createToken` with a dummy client name or personal access client name, then we won't have a pivot.
        // Let's write that test:
        $token = $user->createToken('Personal Access Token')->accessToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/client-user/link', [
                'client_app_user_id' => 'external-user-id-abc-123',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User is not registered for this application',
            ]);
    }

    public function test_link_validates_required_client_app_user_id(): void
    {
        $user = User::factory()->create();
        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => false,
        ]);

        $loginPayload = [
            'email' => $user->email,
            'password' => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $loginResponse = $this->postJson('/api/v1/auth/login', $loginPayload);
        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/client-user/link', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_app_user_id']);
    }
}
