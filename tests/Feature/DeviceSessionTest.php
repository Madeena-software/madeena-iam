<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OauthClient;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    private OauthClient $client;

    private string $clientSecret = 'test-secret-value-abc';

    protected function setUp(): void
    {
        $_ENV['SESSION_DRIVER'] = 'database';
        $_SERVER['SESSION_DRIVER'] = 'database';
        putenv('SESSION_DRIVER=database');

        parent::setUp();

        $this->disableCookieEncryption();

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

    protected function tearDown(): void
    {
        $_ENV['SESSION_DRIVER'] = 'array';
        $_SERVER['SESSION_DRIVER'] = 'array';
        putenv('SESSION_DRIVER=array');

        parent::tearDown();
    }

    /**
     * Test user agent parser logic on the Session model.
     */
    public function test_user_agent_details_parsing(): void
    {
        $uas = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1' => [
                'browser' => 'Safari',
                'operating_system' => 'iPhone',
                'device' => 'Mobile',
            ],
            'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1' => [
                'browser' => 'Safari',
                'operating_system' => 'iPad',
                'device' => 'Tablet',
            ],
            'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36' => [
                'browser' => 'Chrome',
                'operating_system' => 'Android',
                'device' => 'Mobile',
            ],
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0' => [
                'browser' => 'Edge',
                'operating_system' => 'Windows',
                'device' => 'Desktop',
            ],
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:124.0) Gecko/20100101 Firefox/124.0' => [
                'browser' => 'Firefox',
                'operating_system' => 'macOS',
                'device' => 'Desktop',
            ],
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 OPR/109.0.0.0' => [
                'browser' => 'Opera',
                'operating_system' => 'Linux',
                'device' => 'Desktop',
            ],
        ];

        foreach ($uas as $uaString => $expected) {
            $session = new Session(['user_agent' => $uaString]);
            $details = $session->device_details;

            $this->assertEquals($expected['browser'], $details['browser']);
            $this->assertEquals($expected['operating_system'], $details['operating_system']);
            $this->assertEquals($expected['device'], $details['device']);
        }
    }

    /**
     * Test retrieving active sessions for authenticated user.
     */
    public function test_list_active_sessions_for_authenticated_user(): void
    {
        $user1 = User::create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Insert sessions
        DB::table('sessions')->insert([
            [
                'id' => 'sess_u1_1',
                'user_id' => $user1->id,
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/123.0.0',
                'payload' => 'payload1',
                'last_activity' => time(),
            ],
            [
                'id' => 'sess_u1_2',
                'user_id' => $user1->id,
                'ip_address' => '192.168.1.2',
                'user_agent' => 'Mozilla/5.0 (iPhone) Safari/604.1',
                'payload' => 'payload2',
                'last_activity' => time() - 3600,
            ],
            [
                'id' => 'sess_u2_1',
                'user_id' => $user2->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh) Firefox/124.0',
                'payload' => 'payload3',
                'last_activity' => time(),
            ],
        ]);

        // Generate Passport token for user 1
        $tokenResult = $user1->createToken($this->client->name);
        $token = $tokenResult->accessToken;

        // 1. Check listing sessions (using session ID via Header)
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Session-ID', 'sess_u1_1')
            ->getJson('/api/v1/sessions');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'id' => 'sess_u1_1',
                'ip_address' => '192.168.1.1',
                'is_current' => true,
            ])
            ->assertJsonFragment([
                'id' => 'sess_u1_2',
                'ip_address' => '192.168.1.2',
                'is_current' => false,
            ])
            ->assertJsonMissing([
                'id' => 'sess_u2_1',
            ]);

        // 2. Check listing sessions (using session ID via query param)
        $response2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/sessions?sso_session_id=sess_u1_2');

        $response2->assertStatus(200)
            ->assertJsonFragment([
                'id' => 'sess_u1_1',
                'is_current' => false,
            ])
            ->assertJsonFragment([
                'id' => 'sess_u1_2',
                'is_current' => true,
            ]);
    }

    /**
     * Test revoking sessions owned by the user and preventing revoking other users' sessions.
     */
    public function test_user_cannot_access_or_revoke_other_users_sessions(): void
    {
        $user1 = User::create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Insert sessions
        DB::table('sessions')->insert([
            [
                'id' => 'sess_u1_1',
                'user_id' => $user1->id,
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/123.0.0',
                'payload' => 'payload1',
                'last_activity' => time(),
            ],
            [
                'id' => 'sess_u2_1',
                'user_id' => $user2->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh) Firefox/124.0',
                'payload' => 'payload2',
                'last_activity' => time(),
            ],
        ]);

        // Generate Passport token for user 1
        $tokenResult = $user1->createToken($this->client->name);
        $token = $tokenResult->accessToken;

        // Try to delete user 2's session as user 1
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/sessions/sess_u2_1');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Session not found or unauthorized',
            ]);

        // Verify user 2's session is still in DB
        $this->assertDatabaseHas('sessions', [
            'id' => 'sess_u2_1',
        ]);

        // Successfully delete user 1's own session
        $response2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/sessions/sess_u1_1');

        $response2->assertStatus(200)
            ->assertJson([
                'message' => 'Session terminated successfully',
            ]);

        $this->assertDatabaseMissing('sessions', [
            'id' => 'sess_u1_1',
        ]);
    }

    public function test_session_revocation_invalidates_session(): void
    {
        // Set session driver to database for testing integration
        config(['session.driver' => 'database']);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');

        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        // POST /login to establish session in the database
        $responseLogin = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $responseLogin->assertRedirect(); // Successful login redirects

        // Get session ID from the session helper
        $sessionId = session()->getId();

        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
            'user_id' => $user->id,
        ]);

        // Verify that visiting /login with this session cookie redirects (guest middleware redirects authenticated users)
        $responseBefore = $this->withUnencryptedCookie(config('session.cookie'), $sessionId)
            ->get('/login');
        $responseBefore->assertRedirect();

        // Invalidate the session in database via API (simulating remote revocation)
        $tokenResult = $user->createToken($this->client->name);
        $token = $tokenResult->accessToken;

        $responseDelete = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Session-ID', $sessionId)
            ->deleteJson('/api/v1/sessions/'.$sessionId);

        $responseDelete->assertStatus(200)
            ->assertJson([
                'message' => 'Session terminated successfully',
                'is_current_terminated' => true,
            ]);

        $this->assertDatabaseMissing('sessions', [
            'id' => $sessionId,
        ]);

        // Clear the actingAs/Auth state in the test runner memory
        $this->flushSession();
        $this->flushHeaders();
        Auth::guard('web')->logout();
        Auth::guard('web')->forgetUser();
        $this->app['auth']->forgetGuards();

        // Perform a subsequent request with the deleted session cookie to verify guest state.
        $responseAfterRevocation = $this->withUnencryptedCookie(config('session.cookie'), $sessionId)
            ->get('/login');

        // Since the session was terminated, they are a guest and should see the login page (200 OK)
        $responseAfterRevocation->assertStatus(200);
    }
}
