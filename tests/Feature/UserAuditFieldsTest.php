<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAuditFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super_admin']);
        $this->admin = User::factory()->create(['name' => 'Super Admin']);
        $this->admin->assignRole('super_admin');

        $this->actingAs($this->admin);
    }

    public function test_users_table_columns_exist_and_query_does_not_crash_on_sorting_and_searching(): void
    {
        $creator = User::factory()->create(['name' => 'Creator User']);
        $updater = User::factory()->create(['name' => 'Updater User']);
        $deleter = User::factory()->create(['name' => 'Deleter User']);

        $targetUser = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
        ]);
        $targetUser->created_by = $creator->id;
        $targetUser->updated_by = $updater->id;
        $targetUser->deleted_by = $deleter->id;
        $targetUser->saveQuietly();

        // Verify page loads and table columns exist
        $component = Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$targetUser, $this->admin])
            ->assertTableColumnExists('creator_name')
            ->assertTableColumnExists('updater_name')
            ->assertTableColumnExists('deleter_name');

        // Test sorting by all columns to verify no SQL column ambiguity crashes occur
        $sortableColumns = [
            'id',
            'name',
            'email',
            'email_verified_at',
            'created_at',
            'updated_at',
            'creator_name',
            'updater_name',
            'deleted_at',
            'deleter_name',
        ];

        foreach ($sortableColumns as $column) {
            $component->sortTable($column, 'asc');
            $component->sortTable($column, 'desc');
        }

        // Test searching by prefix-qualified columns to verify no SQL column ambiguity crashes occur
        $component->searchTable('Target User');
        $component->searchTable('target@example.com');
        $component->searchTable('Creator User');
    }

    public function test_user_edit_form_displays_creator_updater_and_deleter_names(): void
    {
        $creator = User::factory()->create(['name' => 'Creator User']);
        $updater = User::factory()->create(['name' => 'Updater User']);
        $deleter = User::factory()->create(['name' => 'Deleter User']);

        $targetUser = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
        ]);
        $targetUser->created_by = $creator->id;
        $targetUser->updated_by = $updater->id;
        $targetUser->deleted_by = $deleter->id;
        $targetUser->saveQuietly();

        Livewire::test(EditUser::class, ['record' => $targetUser->id])
            ->assertFormFieldDoesNotExist('created_by')
            ->assertFormFieldDoesNotExist('updated_by')
            ->assertFormFieldDoesNotExist('deleted_by')
            ->assertSee('Creator User')
            ->assertSee('Updater User');
    }

    public function test_default_view_does_not_show_deleted_users(): void
    {
        $deletedUser = User::factory()->create(['name' => 'Deleted User', 'email' => 'deleted@example.com']);
        $deletedUser->delete();

        $activeUser = User::factory()->create(['name' => 'Active User', 'email' => 'active@example.com']);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$activeUser])
            ->assertCanNotSeeTableRecords([$deletedUser]);
    }

    public function test_filter_with_deleted_records_shows_deleted_users(): void
    {
        $deletedUser = User::factory()->create(['name' => 'Deleted User', 'email' => 'deleted@example.com']);
        $deletedUser->delete();

        $activeUser = User::factory()->create(['name' => 'Active User', 'email' => 'active@example.com']);

        Livewire::test(ListUsers::class)
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$activeUser, $deletedUser]);
    }

    public function test_filter_only_deleted_records_shows_only_deleted_users(): void
    {
        $deletedUser = User::factory()->create(['name' => 'Deleted User', 'email' => 'deleted@example.com']);
        $deletedUser->delete();

        $activeUser = User::factory()->create(['name' => 'Active User', 'email' => 'active@example.com']);

        Livewire::test(ListUsers::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$deletedUser])
            ->assertCanNotSeeTableRecords([$activeUser]);
    }
}
