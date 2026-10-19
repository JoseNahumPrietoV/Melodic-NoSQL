<?php

namespace Tests\Feature;

use App\Models\Earning;
use App\Models\Expense;
use App\Models\Royalty;
use App\Models\User;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class WithdrawTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_artist_withdrawal_pays_seventy_percent_and_keeps_thirty_as_commission(): void
    {
        $artist = User::factory()->artist()->create();
        $royalty = Royalty::factory()->create(['recipient_id' => $artist->user_id, 'amount' => 10.00]);

        $this->actingAs($artist)
            ->post(route('wallet.withdraw'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas(Expense::class, [
            'amount' => 7.00,
            'user_id' => $artist->user_id,
            'from' => $royalty->royalties_id,
        ]);
        $this->assertDatabaseHas(Earning::class, [
            'amount' => 3.00,
            'comision' => $royalty->royalties_id,
        ]);
        $this->assertBalanceIsEmpty($royalty);
    }

    public function test_withdrawal_without_funds_creates_no_movements(): void
    {
        $artist = User::factory()->artist()->create();
        Royalty::factory()->create(['recipient_id' => $artist->user_id, 'amount' => 0]);

        $this->actingAs($artist)
            ->post(route('wallet.withdraw'))
            ->assertSessionHas('error');

        $this->assertSame(0, Expense::count());
        $this->assertSame(0, Earning::count());
    }

    public function test_label_withdrawal_pays_seventy_percent_and_empties_the_balance(): void
    {
        $label = User::factory()->label()->create();
        $royalty = Royalty::factory()->create(['recipient_id' => $label->user_id, 'amount' => 20.00]);

        $this->actingAs($label)
            ->post(route('label.withdraw'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas(Expense::class, [
            'amount' => 14.00,
            'user_id' => $label->user_id,
            'from' => $royalty->royalties_id,
        ]);
        $this->assertDatabaseHas(Earning::class, ['amount' => 6.00, 'comision' => $royalty->royalties_id]);
        $this->assertBalanceIsEmpty($royalty);
    }

    public function test_withdrawal_split_adds_up_to_the_exact_balance(): void
    {
        $artist = User::factory()->artist()->create();
        Royalty::factory()->create(['recipient_id' => $artist->user_id, 'amount' => '45.45']);

        $this->actingAs($artist)->post(route('wallet.withdraw'));

        $this->assertSame('31.82', Expense::firstOrFail()->amount);
        $this->assertSame('13.63', Earning::firstOrFail()->amount);
    }

    private function assertBalanceIsEmpty(Royalty $royalty): void
    {
        $royalty->refresh();

        $this->assertEqualsWithDelta(0, (float) $royalty->amount, 0.001);
        $this->assertNotNull($royalty->last_payment);
    }
}
