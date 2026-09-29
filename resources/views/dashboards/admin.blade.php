<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Melodic - Administración</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Chart.js para las gráficas -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --dark: #0A0310;
            --purple: #49007E;
            --pink: #FF005B;
            --orange: #FF7D10;
            --yellow: #FFB238;
        }
        /* Personalización de scrollbar para un look más limpio */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0A0310;
        }
        ::-webkit-scrollbar-thumb {
            background: #49007E;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #FF005B;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen font-sans flex overflow-hidden">

<!-- SIDEBAR -->
<aside class="w-64 bg-black border-r border-purple-900 flex-shrink-0 hidden md:flex flex-col transition-all duration-300">
    <div class="p-6 flex items-center gap-3">
        <!-- Logo simple -->
        <div class="w-8 h-8 bg-pink-500 rounded-full flex items-center justify-center font-bold text-black">M</div>
        <div>
            <h1 class="text-xl font-bold text-white tracking-wider">MELODIC</h1>
            <span class="text-xs text-pink-500 font-semibold tracking-widest uppercase">Admin Panel</span>
        </div>
    </div>

    <nav class="flex-1 px-4 space-y-4 mt-4">
        <div>
            <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Finanzas</p>
            <a href="#" class="flex items-center gap-3 px-4 py-3 bg-purple-900 bg-opacity-40 text-pink-400 rounded-lg transition border border-purple-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                <span class="font-medium">Dashboard</span>
            </a>
        </div>

        <div>
            <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Gestión</p>
            <a href="#labels-section" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span class="font-medium">Disqueras (Labels)</span>
            </a>
        </div>
    </nav>

    <div class="p-4 border-t border-purple-900 bg-gray-900 bg-opacity-50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs">AD</div>
            <div class="text-sm">
                <p class="font-semibold text-white">Administrador</p>
                <p class="text-xs text-gray-500">Super Usuario</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="w-full py-2 text-xs font-bold text-center text-red-400 border border-red-900/30 bg-red-900/10 rounded hover:bg-red-900/30 transition">
                CERRAR SESIÓN
            </button>
        </form>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="flex-1 overflow-x-hidden overflow-y-auto bg-black p-8 relative">
    <!-- Fondo decorativo -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-purple-900 rounded-full mix-blend-screen filter blur-3xl opacity-20 pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 w-96 h-96 bg-pink-900 rounded-full mix-blend-screen filter blur-3xl opacity-20 pointer-events-none"></div>

    <div class="relative z-10 max-w-7xl mx-auto space-y-8">

        <!-- ENCABEZADO -->
        <div>
            <h2 class="text-3xl font-bold text-white">Resumen Financiero</h2>
            <p class="text-gray-400 mt-1">Estado actual de la plataforma y proyecciones.</p>
        </div>

        <!-- KPIs (INDICADORES CLAVE) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Ingresos Totales -->
            <div class="bg-gray-900 border border-gray-800 p-6 rounded-xl shadow-lg relative overflow-hidden group hover:border-green-500/50 transition">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Ingresos Brutos</p>
                        <h3 class="text-2xl font-bold text-white mt-1">${{ number_format($totalEarnings, 2) }}</h3>
                    </div>
                    <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">Total recaudado (Suscripciones + % de Plays)</div>
            </div>

            <!-- Egresos -->
            <div class="bg-gray-900 border border-gray-800 p-6 rounded-xl shadow-lg relative overflow-hidden group hover:border-red-500/50 transition">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Pagos a Artistas</p>
                        <h3 class="text-2xl font-bold text-white mt-1">${{ number_format($totalExpenses, 2) }}</h3>
                    </div>
                    <div class="p-2 bg-red-500/10 rounded-lg text-red-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">Regalías pagadas a creadores</div>
            </div>

            <!-- Ganancia Neta -->
            <div class="bg-gray-900 border border-purple-500 p-6 rounded-xl shadow-lg relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-purple-900/20 to-transparent pointer-events-none"></div>
                <div class="flex justify-between items-start relative z-10">
                    <div>
                        <p class="text-xs font-bold text-purple-400 uppercase tracking-wide">Ganancia Neta</p>
                        <h3 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-white to-purple-200 mt-1">
                            ${{ number_format($netProfit, 2) }}
                        </h3>
                    </div>
                    <div class="p-2 bg-purple-500/20 rounded-lg text-purple-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-xs text-purple-300">Beneficio real tras pagos</div>
            </div>

            <!-- Proyección -->
            <div class="bg-gradient-to-br from-gray-900 to-black border border-gray-800 p-6 rounded-xl shadow-lg relative overflow-hidden group hover:border-orange-500/50 transition">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-orange-400 uppercase tracking-wide">Proyección (30d)</p>
                        <h3 class="text-2xl font-bold text-white mt-1">+ ${{ number_format($projectedRevenue, 2) }}</h3>
                    </div>
                    <div class="p-2 bg-orange-500/10 rounded-lg text-orange-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">Estimado basado en tendencia actual</div>
            </div>
        </div>

        <!-- SECCIÓN PRINCIPAL: GRÁFICA Y GESTIÓN -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- GRÁFICA DE INGRESOS -->
            <div class="lg:col-span-2 bg-gray-900 border border-gray-800 p-6 rounded-xl shadow-lg">
                <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                    <span class="w-2 h-6 bg-pink-500 rounded-full"></span>
                    Evolución de Ingresos (6 Meses)
                </h3>
                <div class="h-80 w-full">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- CREAR NUEVA LABEL -->
            <div id="labels-section" class="bg-gray-900 border border-gray-800 p-6 rounded-xl shadow-lg">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                    <span class="w-2 h-6 bg-orange-500 rounded-full"></span>
                    Registrar Label
                </h3>
                <p class="text-sm text-gray-400 mb-6">Crea accesos para nuevas disqueras asociadas a la plataforma.</p>

                <form action="{{ route('admin.create_label') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nombre Comercial</label>
                        <input type="text" name="username" class="w-full bg-black border border-gray-700 rounded-lg p-3 text-white focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500 transition" placeholder="Ej. Sony Music" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email Corporativo</label>
                        <input type="email" name="email" class="w-full bg-black border border-gray-700 rounded-lg p-3 text-white focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500 transition" placeholder="contacto@label.com" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Contraseña Inicial</label>
                        <input type="password" name="password" class="w-full bg-black border border-gray-700 rounded-lg p-3 text-white focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500 transition" placeholder="******" required>
                    </div>

                    <button class="w-full py-3 bg-gradient-to-r from-pink-600 to-purple-600 rounded-lg font-bold text-white hover:opacity-90 transition shadow-lg mt-2">
                        Crear Cuenta de Label
                    </button>
                </form>

                <!-- Mensajes de éxito -->
                @if(session('success'))
                    <div class="mt-4 p-3 bg-green-500/20 border border-green-500/50 rounded-lg text-green-400 text-sm text-center font-semibold">
                        {{ session('success') }}
                    </div>
                @endif
            </div>
        </div>

        <!-- TABLA DE LABELS RECIENTES -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-800">
                <h3 class="text-lg font-bold text-white">Disqueras Registradas</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-gray-400">
                    <thead class="bg-black bg-opacity-50">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider">Nombre</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider">Email</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider">Fecha Registro</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                    @forelse ($labelsList as $label)
                        <tr class="hover:bg-gray-800/50 transition">
                            <td class="px-6 py-4 font-semibold text-white">{{ $label->username }}</td>
                            <td class="px-6 py-4">{{ $label->email }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ Str::limit($label->user_id, 8) }}</td>
                            <td class="px-6 py-4 text-sm">{{ $label->created_at->format('d M, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                No hay disqueras registradas aún.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<!-- Script para Chart.js -->
<script>
    const ctx = document.getElementById('revenueChart').getContext('2d');

    // Gradiente para el gráfico
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 0, 91, 0.5)');   // Color rosa (#FF005B) con opacidad
    gradient.addColorStop(1, 'rgba(255, 0, 91, 0.0)');

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                label: 'Ingresos Mensuales',
                data: @json($chartData),
                borderColor: '#FF005B',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#0A0310',
                pointBorderColor: '#FF005B',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                fill: true,
                tension: 0.4 // Suavizado de curva
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // Ocultamos leyenda para diseño más limpio
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#334155',
                        borderDash: [5, 5]
                    },
                    ticks: {
                        color: '#94a3b8',
                        callback: function(value) { return '$' + value; }
                    },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' },
                    border: { display: false }
                }
            }
        }
    });
</script>

</body>
</html>
