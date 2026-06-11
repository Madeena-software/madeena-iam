<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Filament\Resources\OauthClients\Pages\CreateOauthClient;
use App\Filament\Resources\OauthClients\Pages\ListOauthClients;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\ClientsRelationManager;
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
        $this->assertStringStartsWith('eyJpdiI6', $client->getRawOriginal('secret'));
        $this->assertEquals($client->plainSecret, $client->secret);
        $this->assertEquals(['password', 'authorization_code'], $client->grant_types);
        $this->assertEquals('Company', $client->owner_type);
        $this->assertNotNull($client->owner_id);
        $this->assertEquals('PT Madeena Karya Indonesia', $client->owner->name);
    }

    public function test_oauth_client_create_form_layout_and_fields(): void
    {
        Livewire::test(CreateOauthClient::class)
            ->assertFormFieldHidden('id')
            ->assertFormFieldHidden('secret')
            ->assertFormFieldExists('owner_type')
            ->assertFormFieldExists('owner_id')
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

    public function test_default_view_does_not_show_deleted_oauth_clients(): void
    {
        $deletedClient = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Deleted Client',
            'secret' => Hash::make('test-secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);
        $deletedClient->delete();

        $activeClient = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Active Client',
            'secret' => Hash::make('test-secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);

        Livewire::test(ListOauthClients::class)
            ->assertCanSeeTableRecords([$activeClient])
            ->assertCanNotSeeTableRecords([$deletedClient]);
    }

    public function test_filter_with_deleted_records_shows_deleted_oauth_clients(): void
    {
        $deletedClient = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Deleted Client',
            'secret' => Hash::make('test-secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);
        $deletedClient->delete();

        $activeClient = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Active Client',
            'secret' => Hash::make('test-secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);

        Livewire::test(ListOauthClients::class)
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$activeClient, $deletedClient]);
    }

    public function test_filter_only_deleted_records_shows_only_deleted_oauth_clients(): void
    {
        $deletedClient = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Deleted Client',
            'secret' => Hash::make('test-secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);
        $deletedClient->delete();

        $activeClient = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Active Client',
            'secret' => Hash::make('test-secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);

        Livewire::test(ListOauthClients::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$deletedClient])
            ->assertCanNotSeeTableRecords([$activeClient]);
    }

    public function test_user_clients_relation_manager_attach_and_edit_pivot(): void
    {
        $user = User::factory()->create();

        $client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'IT Client',
            'redirect_uris' => 'https://it.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);

        // Test custom attach action with manual input client_id
        Livewire::test(ClientsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => EditUser::class,
        ])
            ->callTableAction('attach', data: [
                'client_id' => $client->id,
                'status' => UserStatus::PENDING_APPROVAL->value,
                'access' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($user->clients()->where('client_id', $client->id)->exists());

        // Test editing pivot attributes via relation manager edit action
        Livewire::test(ClientsRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => EditUser::class,
        ])
            ->mountTableAction('edit', record: $client->id)
            ->setTableActionData([
                'status' => UserStatus::APPROVED->value,
                'is_blocked' => false, // false on Access toggle maps to is_blocked = true in DB
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $pivot = $user->clients()->wherePivot('client_id', $client->id)->first()->pivot;
        $this->assertEquals(UserStatus::APPROVED, $pivot->status);
        $this->assertTrue($pivot->is_blocked);
    }

    public function test_client_user_allows_nullable_client_app_user_id(): void
    {
        $user = User::factory()->create();

        $client1 = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Auto Generate Client',
            'redirect_uris' => 'https://it.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);

        $client2 = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Manual Override Client',
            'redirect_uris' => 'https://it.local/callback',
            'grant_types' => ['password', 'authorization_code'],
            'revoked' => false,
            'is_active' => true,
        ]);

        // 1. Verify null when client_app_user_id is not specified
        $user->clients()->attach($client1->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
        ]);

        $pivot1 = $user->clients()->wherePivot('client_id', $client1->id)->first()->pivot;
        $this->assertNull($pivot1->client_app_user_id);

        // 2. Verify override is preserved when client_app_user_id is explicitly passed
        $user->clients()->attach($client2->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
            'client_app_user_id' => 'custom-external-id-123',
        ]);

        $pivot2 = $user->clients()->wherePivot('client_id', $client2->id)->first()->pivot;
        $this->assertEquals('custom-external-id-123', $pivot2->client_app_user_id);
    }
}
