<?php

namespace Tests\Feature;

use App\Livewire\UserProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders_with_stats(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Change Password')
            ->assertSee('Favourite sport');
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        Livewire::actingAs($user)
            ->test(UserProfile::class)
            ->set('current_password', 'old-password')
            ->set('new_password', 'new-password-123')
            ->set('new_password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSee('Password updated successfully.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected_and_throttled(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $component = Livewire::actingAs($user)->test(UserProfile::class)
            ->set('new_password', 'new-password-123')
            ->set('new_password_confirmation', 'new-password-123');

        for ($i = 0; $i < 5; $i++) {
            $component->set('current_password', 'wrong')->call('updatePassword')
                ->assertSee('The current password is incorrect.');
        }

        $component->set('current_password', 'old-password')->call('updatePassword')
            ->assertSee('Too many attempts');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_profile_update_shows_success_message(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UserProfile::class)
            ->set('name', 'New Name')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertSee('Profile updated successfully.');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_changing_email_requires_the_password(): void
    {
        $user = User::factory()->create(['email' => 'old@test.com', 'password' => 'secret-pass']);

        Livewire::actingAs($user)
            ->test(UserProfile::class)
            ->set('email', 'attacker@test.com')
            ->call('updateProfile')
            ->assertHasErrors('email_password');
        $this->assertSame('old@test.com', $user->fresh()->email);

        Livewire::actingAs($user)
            ->test(UserProfile::class)
            ->set('email', 'new@test.com')
            ->set('email_password', 'secret-pass')
            ->call('updateProfile')
            ->assertHasNoErrors();
        $this->assertSame('new@test.com', $user->fresh()->email);
    }
}
