<?php

namespace Tests\Feature;

use App\Models\Earning;
use App\Models\Subscription;
use App\Models\User;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 12:00:00');
    }

    public function test_unsubscribed_listener_is_sent_to_the_premium_page(): void
    {
        $listener = User::factory()->create();

        $this->actingAs($listener)
            ->get(route('dashboard.user'))
            ->assertRedirect(route('subscription.index'));
    }

    public function test_subscribing_records_the_earning_and_sets_the_end_date(): void
    {
        $listener = User::factory()->create();
        $plan = Subscription::factory()->create(['cost' => 149.00, 'months' => 3]);

        $this->actingAs($listener)
            ->post(route('subscription.store'), ['plan_id' => $plan->id])
            ->assertRedirect(route('dashboard.user'));

        $this->assertDatabaseHas(Earning::class, [
            'amount' => 149.00,
            'subscription' => $plan->id,
            'comision' => null,
        ]);

        $listener->refresh();
        $this->assertTrue($listener->is_suscribed);
        $this->assertSame($plan->id, $listener->subscription_id);
        $this->assertSame('2027-01-19', $listener->fin_subscription->toDateString());
    }

    public function test_renewing_an_active_subscription_extends_from_its_end_date(): void
    {
        $listener = User::factory()->create([
            'is_suscribed' => true,
            'fin_subscription' => '2026-11-05',
        ]);
        $plan = Subscription::factory()->create(['months' => 1]);

        $this->actingAs($listener)
            ->post(route('subscription.store'), ['plan_id' => $plan->id]);

        $this->assertSame('2026-12-05', $listener->refresh()->fin_subscription->toDateString());
    }

    public function test_expired_subscription_is_cleared_on_the_next_request(): void
    {
        $plan = Subscription::factory()->create();
        $listener = User::factory()->create([
            'is_suscribed' => true,
            'fin_subscription' => '2026-10-01',
            'subscription_id' => $plan->id,
        ]);

        $this->actingAs($listener)
            ->get(route('dashboard.user'))
            ->assertRedirect(route('subscription.index'));

        $listener->refresh();
        $this->assertFalse($listener->is_suscribed);
        $this->assertNull($listener->fin_subscription);
        $this->assertNull($listener->subscription_id);
    }
}
