<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshMongoDatabase;

    #[DataProvider('dashboardsByRole')]
    public function test_login_redirects_each_role_to_its_dashboard(string $role, string $dashboard): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route($dashboard));

        $this->assertAuthenticatedAs($user);
    }

    public function test_listener_cannot_open_the_artist_dashboard(): void
    {
        $listener = User::factory()->subscribed()->create();

        $this->actingAs($listener)
            ->get(route('dashboard.artist'))
            ->assertForbidden();
    }

    public static function dashboardsByRole(): array
    {
        return [
            'user' => ['user', 'dashboard.user'],
            'artist' => ['artist', 'dashboard.artist'],
            'label' => ['label', 'dashboard.label'],
            'admin' => ['admin', 'dashboard.admin'],
        ];
    }

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
