<?php

namespace Tests\Feature;

use App\Models\Earning;
use App\Models\Expense;
use App\Models\User;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_non_admin_users_cannot_open_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->label()->create())
            ->get(route('dashboard.admin'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_shows_the_platform_totals(): void
    {
        Earning::create(['amount' => 100.00, 'state' => 'pagado', 'at' => now()]);
        Expense::create(['amount' => 40.00, 'state' => 'pagado', 'at' => now()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard.admin'))
            ->assertOk()
            ->assertViewHas('totalEarnings', fn ($total) => (float) $total === 100.0)
            ->assertViewHas('netProfit', fn ($profit) => (float) $profit === 60.0);
    }

    public function test_admin_chart_groups_the_last_six_months_of_earnings(): void
    {
        $this->travelTo('2026-10-19 12:00:00');
        Earning::create(['amount' => 99.00, 'state' => 'pagado', 'at' => '2026-08-03 10:00:00']);
        Earning::create(['amount' => 49.50, 'state' => 'pagado', 'at' => '2026-08-20 10:00:00']);
        Earning::create(['amount' => 13.59, 'state' => 'pagado', 'at' => '2026-10-18 10:00:00']);
        Earning::create(['amount' => 500.00, 'state' => 'pagado', 'at' => '2026-03-01 10:00:00']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard.admin'))
            ->assertOk()
            ->assertViewHas('chartLabels', ['August 2026', 'October 2026'])
            ->assertViewHas('chartData', [148.5, 13.59])
            ->assertViewHas('projectedRevenue', '13.59');
    }
}
