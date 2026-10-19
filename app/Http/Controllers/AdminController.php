<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Earning;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Muestra el Dashboard principal del Administrador.
     */
    public function index()
    {
        $totalEarnings = $this->sumAmount(Earning::class);

        $totalExpenses = $this->sumAmount(Expense::class);

        $netProfit = bcsub($totalEarnings, $totalExpenses, 2);

        $monthlyData = $this->aggregate(Earning::class, [
            ['$match' => ['at' => ['$gte' => new UTCDateTime(Carbon::now()->subMonths(6))]]],
            ['$group' => ['_id' => ['$dateToString' => ['format' => '%Y-%m', 'date' => '$at']], 'sum' => ['$sum' => '$amount']]],
            ['$sort' => ['_id' => 1]],
        ]);

        $labels = [];
        $data = [];

        foreach ($monthlyData as $row) {
            $labels[] = Carbon::createFromFormat('!Y-m', $row['_id'])->format('F Y');
            $data[] = (float) (string) $row['sum'];
        }

        $last30DaysEarnings = $this->sumAmount(Earning::class, ['at' => ['$gte' => new UTCDateTime(Carbon::now()->subDays(30))]]);

        $dailyAverage = bcdiv($last30DaysEarnings, '30', 4);

        $projectedRevenue = bcmul($dailyAverage, '30', 2);

        $labelsList = User::where('role', 'label')->latest()->take(10)->get();

        return view('dashboards.admin', [
            'totalEarnings'    => $totalEarnings,
            'totalExpenses'    => $totalExpenses,
            'netProfit'        => $netProfit,
            'projectedRevenue' => $projectedRevenue,
            'chartLabels'      => $labels,
            'chartData'        => $data,
            'labelsList'       => $labelsList
        ]);
    }

    /**
     * Almacena una nueva Label (Disquera) en la base de datos.
     */
    private function sumAmount(string $model, array $match = []): string
    {
        $result = $this->aggregate($model, [
            ['$match' => (object) $match],
            ['$group' => ['_id' => null, 'total' => ['$sum' => '$amount']]],
        ]);

        return bcadd((string) ($result[0]['total'] ?? '0'), '0', 2);
    }

    private function aggregate(string $model, array $pipeline): array
    {
        return $model::query()->toBase()->raw(
            fn (Collection $collection) => $collection->aggregate($pipeline, ['typeMap' => ['root' => 'array', 'document' => 'array']])
        )->toArray();
    }

    public function storeLabel(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique(User::class, 'email')],
            'password' => 'required|min:6',
        ]);

        User::create([
            'username'     => $request->username,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'role'         => 'label',
            'is_suscribed' => true,
            'label'        => null,
        ]);

        return back()->with('success', '¡Label creada exitosamente!');
    }
}
