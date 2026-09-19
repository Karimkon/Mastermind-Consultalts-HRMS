<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A retired account must actually be unable to sign in.
 *
 * `users.status` existed and the API checked it, but the website never did — so
 * deactivating somebody was a label on a row, not a gate. Retiring the leftover
 * demo accounts would have changed nothing about what they could still do.
 */
class InactiveLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $status): User
    {
        return User::factory()->create([
            'email' => $status.'@example.test',
            'password' => Hash::make('correct-horse-battery'),
            'status' => $status,
        ]);
    }

    public function test_an_active_user_can_sign_in(): void
    {
        $user = $this->user('active');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_inactive_user_cannot_sign_in(): void
    {
        $user = $this->user('inactive');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_suspended_user_cannot_sign_in(): void
    {
        $user = $this->user('suspended');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** The reason must not tell an attacker which addresses exist and are live. */
    public function test_the_refusal_does_not_confirm_the_password_was_right(): void
    {
        $user = $this->user('inactive');

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}
