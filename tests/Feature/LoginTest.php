<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_login_attempt_within_a_minute_is_throttled(): void
    {
        $this->failLoginAttempts(5);

        $this->post(route('login.submit'), $this->invalidCredentials())
            ->assertTooManyRequests();
    }

    public function test_login_throttling_does_not_block_registration(): void
    {
        $this->failLoginAttempts(5);

        $this->post(route('register'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@melodic.test',
            'password' => 'secret123',
            'account_type' => 'user',
        ])->assertRedirect(route('dashboard.user'));
    }

    private function failLoginAttempts(int $attempts): void
    {
        foreach (range(1, $attempts) as $attempt) {
            $this->post(route('login.submit'), $this->invalidCredentials())
                ->assertSessionHasErrors('email');
        }
    }

    private function invalidCredentials(): array
    {
        return [
            'email' => 'nadie@melodic.test',
            'password' => 'incorrecta',
        ];
    }
}
