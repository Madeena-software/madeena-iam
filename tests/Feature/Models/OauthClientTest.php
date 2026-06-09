<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\UserStatus;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OauthClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_client_has_users_relationship(): void
    {
        $user = User::factory()->create();

        $client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'ERP Client',
            'redirect_uris' => 'https://erp.local/callback',
            'grant_types' => 'authorization_code',
            'revoked' => false,
            'is_active' => true,
        ]);

        $client->users()->attach($user->id, [
            'status' => UserStatus::PENDING_APPROVAL->value,
            'is_blocked' => false,
        ]);

        $this->assertTrue($client->users->contains($user));
        $this->assertCount(1, $client->users);
        $this->assertEquals(UserStatus::PENDING_APPROVAL, $client->users->first()->pivot->status);
    }
}
