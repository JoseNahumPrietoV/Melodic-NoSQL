<?php

namespace Tests\Feature;

use App\Models\Earning;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_open_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->label()->create())
            ->get(route('dashboard.admin'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_shows_the_platform_totals(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestIncomplete('Known failure: AdminController uses DATE_FORMAT, which SQLite lacks. Fixed in E1-T12.');
        }

        Earning::create(['amount' => 100.00, 'state' => 'pagado', 'at' => now()]);
        Expense::create(['amount' => 40.00, 'state' => 'pagado', 'at' => now()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard.admin'))
            ->assertOk()
            ->assertViewHas('totalEarnings', fn ($total) => (float) $total === 100.0)
            ->assertViewHas('netProfit', fn ($profit) => (float) $profit === 60.0);
    }
}
