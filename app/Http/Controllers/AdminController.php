<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Earning;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Muestra el Dashboard principal del Administrador.
     */
    public function index()
    {
        $totalEarnings = Earning::sum('amount');

        $totalExpenses = Expense::sum('amount');

        $netProfit = $totalEarnings - $totalExpenses;

        $monthlyData = Earning::select(
            DB::raw('sum(amount) as sum'),
            DB::raw("DATE_FORMAT(at, '%Y-%m') as month")
        )
            ->where('at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = [];
        $data = [];

        foreach ($monthlyData as $row) {
            $labels[] = Carbon::createFromFormat('Y-m', $row->month)->format('F Y');
            $data[] = $row->sum;
        }

        $last30DaysEarnings = Earning::where('at', '>=', Carbon::now()->subDays(30))->sum('amount');

        $dailyAverage = $last30DaysEarnings / 30;

        $projectedRevenue = $dailyAverage * 30;

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
    public function storeLabel(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
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
