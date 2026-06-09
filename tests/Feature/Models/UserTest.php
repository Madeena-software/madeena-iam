<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\UserStatus;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_clients_relationship(): void
    {
        $user = User::factory()->create();

        $client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Workspace Client',
            'redirect_uris' => 'https://workspace.local/callback',
            'grant_types' => 'authorization_code',
            'revoked' => false,
            'is_active' => true,
        ]);

        $user->clients()->attach($client->id, [
            'status' => UserStatus::APPROVED->value,
            'is_blocked' => false,
        ]);

        $this->assertTrue($user->clients->contains($client));
        $this->assertCount(1, $user->clients);
        $this->assertEquals(UserStatus::APPROVED, $user->clients->first()->pivot->status);
    }
}
