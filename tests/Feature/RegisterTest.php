<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_listener_registration_creates_a_user_account(): void
    {
        $response = $this->post(route('register'), $this->registrationData('user'));

        $response->assertRedirect(route('dashboard.user'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas(User::class, ['email' => 'nuevo@melodic.test', 'role' => 'user']);
    }

    public function test_artist_registration_creates_an_artist_account(): void
    {
        $response = $this->post(route('register'), $this->registrationData('artist'));

        $response->assertRedirect(route('dashboard.artist'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas(User::class, ['email' => 'nuevo@melodic.test', 'role' => 'artist']);
    }

    #[DataProvider('privilegedAccountTypes')]
    public function test_registration_rejects_privileged_account_types(string $accountType): void
    {
        $response = $this->post(route('register'), $this->registrationData($accountType));

        $response->assertSessionHasErrors('account_type');
        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, ['email' => 'nuevo@melodic.test']);
    }

    public function test_sixth_registration_attempt_within_a_minute_is_throttled(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('register'), $this->registrationData('admin'))
                ->assertSessionHasErrors('account_type');
        }

        $this->post(route('register'), $this->registrationData('admin'))
            ->assertTooManyRequests();
    }

    public static function privilegedAccountTypes(): array
    {
        return [
            'admin' => ['admin'],
            'label' => ['label'],
        ];
    }

    private function registrationData(string $accountType): array
    {
        return [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@melodic.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'account_type' => $accountType,
        ];
    }
}
