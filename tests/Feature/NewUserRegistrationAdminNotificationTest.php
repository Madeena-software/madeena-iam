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

class NewUserRegistrationAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private OauthClient $client;

    private string $clientSecret = 'test-secret-value-abc';

    protected function setUp(): void
    {
        parent::setUp();

        // Run passport installation tasks for API personal token generation if needed
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

    public function test_admin_is_notified_via_email_on_new_user_registration(): void
    {
        Mail::fake();

        // Create a super_admin role and user
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole($role);

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

        // Assert that the email was queued to the super admin
        Mail::assertQueued(NewUserRegistrationAdminMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email) &&
                   $mail->user->name === 'John Doe' &&
                   $mail->client->id === $this->client->id;
        });
    }

    public function test_admin_notification_fails_silently_if_no_super_admin_exists(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'securePassword123',
            'client_id' => $this->client->id,
            'client_secret' => $this->clientSecret,
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201);

        Mail::assertNothingQueued();
    }

    public function test_sso_silent_flow_auto_creates_pivot_and_notifies_admin(): void
    {
        Mail::fake();

        // Create a super_admin role and user
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole($role);

        $user = User::factory()->create();

        // Ensure user is not registered for this client initially
        $this->assertFalse($user->clients()->where('client_id', $this->client->id)->exists());

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
        $response->assertRedirect('https://testapp.local/callback?error=access_denied&state=state-123456');

        // Assert that the client_user pivot was auto-created with pending_approval
        $pivot = $user->clients()->wherePivot('client_id', $this->client->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(UserStatus::PENDING_APPROVAL, $pivot->pivot->status);

        // Assert that the email was queued to the super admin
        Mail::assertQueued(NewUserRegistrationAdminMail::class, function ($mail) use ($admin, $user) {
            return $mail->hasTo($admin->email) &&
                   $mail->user->id === $user->id &&
                   $mail->client->id === $this->client->id;
        });
    }

    public function test_sso_standard_flow_auto_creates_pivot_and_notifies_admin(): void
    {
        Mail::fake();

        // Create a super_admin role and user
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole($role);

        $user = User::factory()->create();

        // Ensure user is not registered for this client initially
        $this->assertFalse($user->clients()->where('client_id', $this->client->id)->exists());

        // prompt is empty (standard flow)
        $queryParams = [
            'client_id' => $this->client->id,
            'redirect_uri' => 'https://testapp.local/callback',
            'response_type' => 'code',
            'scope' => '',
            'state' => 'state-123456',
        ];

        $response = $this->actingAs($user, 'web')
            ->get('/oauth/authorize?'.http_build_query($queryParams));

        // Returns 403 from CheckClientAccess middleware with custom pending approval message
        $response->assertStatus(403);
        $response->assertSee('Your account is pending approval for this application.');

        // Assert that the client_user pivot was auto-created with pending_approval
        $pivot = $user->clients()->wherePivot('client_id', $this->client->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(UserStatus::PENDING_APPROVAL, $pivot->pivot->status);

        // Assert that the email was queued to the super admin
        Mail::assertQueued(NewUserRegistrationAdminMail::class, function ($mail) use ($admin, $user) {
            return $mail->hasTo($admin->email) &&
                   $mail->user->id === $user->id &&
                   $mail->client->id === $this->client->id;
        });
    }
}
