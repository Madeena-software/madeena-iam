<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Mail\OnboardingMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->record;

        $token = Password::createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        Mail::to($user->email)->queue(new OnboardingMail($user, $url));
    }
}
