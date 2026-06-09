<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Sessions\Pages\ListSessions;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\SessionsRelationManager;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentSessionResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super_admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->regularUser = User::factory()->create();
    }

    public function test_non_admin_cannot_access_sessions_resource(): void
    {
        $this->actingAs($this->regularUser);

        $response = $this->get('/admin/sessions');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_sessions_resource_and_see_sessions(): void
    {
        $this->actingAs($this->admin);

        // Insert a session
        $session = Session::create([
            'id' => 'sess_admin_test',
            'user_id' => $this->regularUser->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/123.0.0',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $response = $this->get('/admin/sessions');
        $response->assertStatus(200);

        Livewire::test(ListSessions::class)
            ->assertCanSeeTableRecords([$session])
            ->assertTableColumnExists('user.name')
            ->assertTableColumnExists('ip_address')
            ->assertTableColumnExists('device_details.description')
            ->assertTableColumnExists('last_activity');
    }

    public function test_admin_can_terminate_session_via_standalone_resource(): void
    {
        $this->actingAs($this->admin);

        $session = Session::create([
            'id' => 'sess_to_delete',
            'user_id' => $this->regularUser->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'sess_to_delete']);

        Livewire::test(ListSessions::class)
            ->callTableAction('delete', $session)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'sess_to_delete']);
    }

    public function test_admin_can_terminate_session_via_relation_manager(): void
    {
        $this->actingAs($this->admin);

        $session = Session::create([
            'id' => 'sess_relation_to_delete',
            'user_id' => $this->regularUser->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'sess_relation_to_delete']);

        Livewire::test(SessionsRelationManager::class, [
            'ownerRecord' => $this->regularUser,
            'pageClass' => EditUser::class,
        ])
            ->assertCanSeeTableRecords([$session])
            ->callTableAction('delete', $session)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'sess_relation_to_delete']);
    }
}
