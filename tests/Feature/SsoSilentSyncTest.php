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

class SsoSilentSyncTest extends TestCase
{
    use RefreshDatabase;

    private OauthClient $client;

    private string $clientSecret = 'test-secret-value-abc';

    protected function setUp(): void
    {
        parent::setUp();

        // Run passport installation tasks for API personal token generation and keys
        $this->artisan('passport:keys', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        // Create a test client application with redirect URI
        $this->client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Client App',
            'secret' => Hash::make($this->clientSecret),
            'redirect_uris' => ['https://testapp.local/callback'],
            'grant_types' => ['authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Test successful silent login code issuance for an authenticated, permitted user.
     */
    public function test_successful_silent_login_code_issuance_for_authenticated_permitted_user(): void
    {
        $user = User::create([
            'name' => 'Authorized User',
            'email' => 'auth@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => false,
        ]);

        $queryParams = [
            'client_id' => $this->client->id,
            'redirect_uri' => 'https://testapp.local/callback',
            'response_type' => 'code',
            'scope' => '',
            'state' => 'state-123456',
            'prompt' => 'none',
        ];

        $response = $this->actingAs($user, 'web')
            ->get('/oauth/authorize?'.http_build_query($queryParams));

        $response->assertStatus(302);

        $redirectUrl = $response->headers->get('Location');
        $this->assertNotNull($redirectUrl);
        $this->assertStringStartsWith('https://testapp.local/callback', $redirectUrl);
        $this->assertStringContainsString('code=', $redirectUrl);
        $this->assertStringContainsString('state=state-123456', $redirectUrl);
    }

    /**
     * Test immediate redirect with error=login_required for an unauthenticated user.
     */
    public function test_immediate_redirect_with_login_required_for_unauthenticated_user(): void
    {
        $queryParams = [
            'client_id' => $this->client->id,
            'redirect_uri' => 'https://testapp.local/callback',
            'response_type' => 'code',
            'scope' => '',
            'state' => 'state-unauth',
            'prompt' => 'none',
        ];

        $response = $this->get('/oauth/authorize?'.http_build_query($queryParams));

        $response->assertStatus(302);

        $redirectUrl = $response->headers->get('Location');
        $this->assertNotNull($redirectUrl);
        $this->assertStringStartsWith('https://testapp.local/callback', $redirectUrl);
        $this->assertStringContainsString('error=login_required', $redirectUrl);
        $this->assertStringContainsString('state=state-unauth', $redirectUrl);
    }

    /**
     * Test immediate redirect with error=access_denied for an authenticated user without client permission.
     */
    public function test_immediate_redirect_with_access_denied_for_authenticated_unpermitted_user(): void
    {
        $user = User::create([
            'name' => 'Unpermitted User',
            'email' => 'unpermitted@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Case A: User has no client_user relationship at all
        $queryParams = [
            'client_id' => $this->client->id,
            'redirect_uri' => 'https://testapp.local/callback',
            'response_type' => 'code',
            'scope' => '',
            'state' => 'state-noperm',
            'prompt' => 'none',
        ];

        $response = $this->actingAs($user, 'web')
            ->get('/oauth/authorize?'.http_build_query($queryParams));

        $response->assertStatus(302);
        $redirectUrl = $response->headers->get('Location');
        $this->assertNotNull($redirectUrl);
        $this->assertStringStartsWith('https://testapp.local/callback', $redirectUrl);
        $this->assertStringContainsString('error=access_denied', $redirectUrl);
        $this->assertStringContainsString('state=state-noperm', $redirectUrl);

        // Case B: User has status pending_approval
        $user->clients()->attach($this->client->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
        ]);

        $response2 = $this->actingAs($user, 'web')
            ->get('/oauth/authorize?'.http_build_query($queryParams));

        $response2->assertStatus(302);
        $redirectUrl2 = $response2->headers->get('Location');
        $this->assertNotNull($redirectUrl2);
        $this->assertStringContainsString('error=access_denied', $redirectUrl2);

        // Case C: User is blocked
        $user->clients()->updateExistingPivot($this->client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => true,
        ]);

        $response3 = $this->actingAs($user, 'web')
            ->get('/oauth/authorize?'.http_build_query($queryParams));

        $response3->assertStatus(302);
        $redirectUrl3 = $response3->headers->get('Location');
        $this->assertNotNull($redirectUrl3);
        $this->assertStringContainsString('error=access_denied', $redirectUrl3);
    }
}
