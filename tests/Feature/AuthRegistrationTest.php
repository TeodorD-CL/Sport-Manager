<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_require_panel_user_role(): void
    {
        $this->assertDatabaseMissing('roles', [
            'name' => 'panel_user',
            'guard_name' => 'web',
        ]);

        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'new-user@test.com',
            'phone' => '070123456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/');

        $user = User::where('email', 'new-user@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->roles()->where('name', 'user')->exists());
        $this->assertFalse($user->roles()->where('name', 'panel_user')->exists());
    }
}
