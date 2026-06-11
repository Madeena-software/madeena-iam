<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\NewUserRegistrationAdminMail;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_contains_sticky_footer(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Madeena. All rights reserved.');
        $response->assertSee('v'.config('app.version'));
    }

    public function test_email_template_footer_contains_copyright_and_version(): void
    {
        $user = User::factory()->create();
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
        $html = $mailable->render();

        $this->assertStringContainsString('Madeena. All rights reserved.', $html);
        $this->assertStringContainsString('v'.config('app.version'), $html);
    }
}
