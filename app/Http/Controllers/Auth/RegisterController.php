<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Mail\NewUserRegistrationAdminMail;
use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegistrationForm(Request $request): View
    {
        return view('auth.register');
    }

    /**
     * Handle a registration request for the application.
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $clientId = $request->input('client_id');
        if ($clientId) {
            $client = OauthClient::where('id', $clientId)
                ->where('is_active', true)
                ->first();

            if ($client) {
                // Attach user to client with pending approval status
                $user->clients()->attach($client->id, [
                    'status' => UserStatus::PENDING_APPROVAL->value,
                    'is_blocked' => false,
                    'client_app_user_id' => $request->input('client_app_user_id'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Notify super admins
                $roleExists = Role::where('name', 'super_admin')->exists();
                $admins = $roleExists ? User::role('super_admin')->get() : collect();
                if ($admins->isNotEmpty()) {
                    Mail::to($admins)->queue(new NewUserRegistrationAdminMail($user, $client));
                }
            }
        }

        // Log the user in via the web guard
        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        if ($clientId) {
            // Re-route back to OAuth authorization endpoint with the original OAuth params
            $oauthParams = $request->only([
                'client_id',
                'redirect_uri',
                'response_type',
                'state',
                'scope',
                'code_challenge',
                'code_challenge_method',
            ]);

            return redirect()->route('passport.authorizations.authorize', $oauthParams);
        }

        return redirect('/');
    }
}
