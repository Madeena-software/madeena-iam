<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Mail\NewUserRegistrationAdminMail;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewUserRegistrationAdminMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailable_has_correct_subject_and_content(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $client = OauthClient::create([
            'id' => Str::uuid()->toString(),
            'name' => 'My Client App',
            'secret' => Hash::make('secret'),
            'redirect_uris' => 'https://testapp.local/callback',
            'grant_types' => 'password,authorization_code',
            'revoked' => false,
            'is_active' => true,
        ]);

        $mailable = new NewUserRegistrationAdminMail($user, $client);

        // Assert Envelope
        $this->assertEquals('New User Registration: John Doe', $mailable->envelope()->subject);

        // Assert rendering contents
        $mailable->assertSeeInHtml('New User Registration');
        $mailable->assertSeeInHtml('John Doe');
        $mailable->assertSeeInHtml('john@example.com');
        $mailable->assertSeeInHtml('My Client App');
        $mailable->assertSeeInHtml(route('filament.admin.resources.users.edit', ['record' => $user]));
    }
}
