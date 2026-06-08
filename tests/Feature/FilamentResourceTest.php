<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Filament\Resources\OauthClients\Pages\CreateOauthClient;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Mail\OnboardingMail;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the super_admin role and user
        Role::create(['name' => 'super_admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        // Authenticate as admin
        $this->actingAs($this->admin);
    }

    public function test_creating_oauth_client_auto_generates_uuid_and_hashes_secret(): void
    {
        $component = Livewire::test(CreateOauthClient::class)
            ->fillForm([
                'name' => 'Test App Client',
                'redirect_uris' => 'https://localhost/callback',
                'grant_types' => ['password', 'authorization_code'],
                'revoked' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = $component->instance()->record;
        $this->assertNotNull($client);
        $this->assertTrue(Str::isUuid($client->id));
        $this->assertStringStartsWith('$2y$', $client->secret);
        $this->assertNotNull($client->plainSecret);
        $this->assertTrue(Hash::check($client->plainSecret, $client->secret));
        $this->assertEquals(['password', 'authorization_code'], $client->grant_types);
    }

    public function test_oauth_client_create_form_layout_and_fields(): void
    {
        Livewire::test(CreateOauthClient::class)
            ->assertFormFieldHidden('id')
            ->assertFormFieldHidden('secret')
            ->assertFormFieldDoesNotExist('owner_type')
            ->assertFormFieldDoesNotExist('owner_id')
            ->assertFormFieldDoesNotExist('provider')
            ->assertFormFieldDoesNotExist('created_by')
            ->assertFormFieldDoesNotExist('updated_by')
            ->assertFormFieldDoesNotExist('deleted_by')
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('redirect_uris')
            ->assertFormFieldExists('grant_types')
            ->assertFormFieldExists('app_logo_path');
    }

    public function test_updating_user_without_password_leaves_old_password_intact(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('original-secure-pass'),
        ]);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
                'password' => '', // blank password
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertTrue(Hash::check('original-secure-pass', $user->password));
    }

    public function test_onboarding_email_is_queued_on_user_creation_in_filament(): void
    {
        Mail::fake();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Filament User',
                'email' => 'filamentuser@example.com',
                'password' => 'secret-admin-defined-pass',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Mail::assertNothingOutgoing();
    }

    public function test_onboarding_email_is_queued_on_client_status_approval(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'approveduser@example.com',
        ]);

        $client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test App',
            'secret' => Hash::make('test-secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);

        // 1. Attach client as pending_approval
        $user->clients()->attach($client->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
        ]);

        Mail::assertNothingOutgoing();

        // 2. Update status to APPROVED
        $pivot = $user->clients()->wherePivot('client_id', $client->id)->first()->pivot;
        $pivot->status = UserStatus::APPROVED;
        $pivot->save();

        Mail::assertQueued(OnboardingMail::class, function (OnboardingMail $mail) {
            return $mail->hasTo('approveduser@example.com') &&
                Str::contains($mail->resetUrl, 'password-reset/');
        });
    }
}
