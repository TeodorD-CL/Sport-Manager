<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Str;

// Emails are stored lowercase (see User::email()), so admin logins must match that.
class Login extends BaseLogin
{
    protected function getCredentialsFromFormData(array $data): array
    {
        return ['email' => Str::lower(trim($data['email']))] + parent::getCredentialsFromFormData($data);
    }
}
