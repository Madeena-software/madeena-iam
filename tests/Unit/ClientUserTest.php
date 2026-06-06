<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserStatus;
use App\Models\ClientUser;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_user_pivot_belongs_to_user_and_client(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();

        $client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'IT Client',
            'redirect_uris' => 'https://it.local/callback',
            'grant_types' => 'authorization_code',
            'revoked' => false,
            'is_active' => true,
        ]);

        $user->clients()->attach($client->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
        ]);

        $pivot = ClientUser::where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->first();

        $this->assertInstanceOf(ClientUser::class, $pivot);
        $this->assertEquals($user->id, $pivot->user->id);
        $this->assertEquals($client->id, $pivot->client->id);
        $this->assertEquals(UserStatus::PENDING_APPROVAL, $pivot->status);

        // Test updating status triggers booted hooks for approved_at and approved_by
        $this->actingAs($admin);
        $pivot->status = UserStatus::APPROVED;
        $pivot->save();

        $pivot->refresh();
        $this->assertEquals(UserStatus::APPROVED, $pivot->status);
        $this->assertNotNull($pivot->approved_at);
        $this->assertEquals($admin->id, $pivot->approved_by);
        $this->assertEquals($admin->id, $pivot->approvedBy->id);
    }
}
