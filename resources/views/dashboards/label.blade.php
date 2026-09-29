<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Melodic - Panel Disquera</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --dark: #0A0310;
            --purple: #49007E;
            --pink: #FF005B;
            --orange: #FF7D10;
            --yellow: #FFB238;
        }
    </style>
</head>
<body class="bg-gradient-to-b from-black via-purple-900 to-black text-white min-h-screen">

<!-- Header -->
<header class="flex items-center justify-between px-8 py-4 bg-opacity-30 bg-black backdrop-blur-md shadow-lg">
    <h1 class="text-3xl font-bold text-pink-500">Melodic <span class="text-sm text-gray-300 font-normal">for Labels</span></h1>
    <nav class="flex gap-6 text-lg">
        <a href="#" class="text-pink-400 font-bold transition border-b-2 border-pink-500">Gestión</a>
    </nav>
    <div class="flex items-center gap-4">
        <span class="text-gray-200">{{ $label->username }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-300 hover:text-pink-500 transition">Cerrar sesión</button>
        </form>
    </div>
</header>

<main class="px-8 py-10 max-w-7xl mx-auto space-y-12">

    <!-- Alertas -->
    @if(session('success'))
        <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-100 px-4 py-3 rounded relative">
            <strong class="font-bold">¡Éxito!</strong> <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-100 px-4 py-3 rounded relative">
            <strong class="font-bold">Error:</strong> <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- SECCIÓN 1: FINANZAS -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Tarjeta Saldo (CORREGIDA) -->
        <div class="bg-black bg-opacity-40 border border-purple-800 p-6 rounded-xl shadow-lg">
            <h3 class="text-gray-400 font-semibold uppercase text-sm">Saldo Disponible</h3>
            <div class="mt-2 flex items-baseline gap-2">
                <!-- AQUÍ ESTABA EL ERROR: Cambiado $totalEarnings por $currentBalance -->
                <span class="text-4xl font-bold text-green-400">${{ number_format($currentBalance, 2) }}</span>
                <span class="text-sm text-gray-500">MXN</span>
            </div>
            <p class="text-sm text-gray-400 mt-2">Basado en {{ number_format($totalPlays) }} reproducciones totales.</p>
        </div>

        <!-- Tarjeta Artistas -->
        <div class="bg-black bg-opacity-40 border border-purple-800 p-6 rounded-xl shadow-lg">
            <h3 class="text-gray-400 font-semibold uppercase text-sm">Artistas Firmados</h3>
            <div class="mt-2 text-4xl font-bold text-yellow-400">{{ $artists->count() }}</div>
            <p class="text-sm text-gray-400 mt-2">Talentos activos en tu sello.</p>
        </div>

        <!-- Tarjeta Retiro -->
        <div class="bg-purple-900 bg-opacity-20 border border-pink-500 p-6 rounded-xl shadow-lg flex flex-col justify-between">
            <div>
                <h3 class="text-pink-500 font-semibold uppercase text-sm">Retirar Fondos</h3>
                <p class="text-xs text-gray-300 mt-1">Comisión de plataforma: 30%</p>
            </div>

            @if($currentBalance > 0)
                <button onclick="openWithdrawModal()" class="w-full py-2 bg-pink-600 hover:bg-pink-700 rounded-lg font-bold mt-4 transition">
                    Solicitar Retiro
                </button>
            @else
                <button disabled class="w-full py-2 bg-gray-700 text-gray-400 rounded-lg font-bold mt-4 cursor-not-allowed">
                    Sin fondos
                </button>
            @endif
        </div>
    </section>

    <!-- SECCIÓN 2: GESTIÓN DE ARTISTAS -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- COLUMNA IZQUIERDA: LISTA DE ARTISTAS -->
        <section class="lg:col-span-2 bg-black bg-opacity-40 p-8 rounded-xl shadow-lg border border-purple-800">
            <h2 class="text-2xl font-semibold mb-6 text-white">Tus Artistas</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                    <tr class="text-gray-400 border-b border-purple-800">
                        <th class="pb-3">Nombre</th>
                        <th class="pb-3">Email</th>
                        <th class="pb-3">Álbumes</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-purple-900/50">
                    @forelse ($artists as $artist)
                        <tr class="group hover:bg-purple-900/20 transition">
                            <td class="py-4 font-semibold text-white">{{ $artist->username }}</td>
                            <td class="py-4 text-gray-400 text-sm">{{ $artist->email }}</td>
                            <td class="py-4 text-gray-300">{{ $artist->albums->count() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-gray-500">
                                Aún no has firmado a ningún artista. ¡Registra al primero!
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- COLUMNA DERECHA: FORMULARIO CREAR ARTISTA -->
        <section class="bg-purple-900 bg-opacity-20 p-8 rounded-xl shadow-lg border border-purple-600 h-fit">
            <h2 class="text-xl font-bold mb-4 text-orange-400">Firmar Nuevo Artista</h2>
            <p class="text-sm text-gray-400 mb-6">Crea un perfil para que tu artista pueda iniciar sesión y subir música.</p>

            <form method="POST" action="{{ route('label.create_artist') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-1">Nombre Artístico</label>
                    <input type="text" name="username" class="w-full p-3 bg-black bg-opacity-50 rounded-lg border border-purple-500 focus:outline-none focus:ring-1 focus:ring-pink-500" placeholder="Ej. Bad Bunny 2" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-1">Correo Electrónico</label>
                    <input type="email" name="email" class="w-full p-3 bg-black bg-opacity-50 rounded-lg border border-purple-500 focus:outline-none focus:ring-1 focus:ring-pink-500" placeholder="artista@label.com" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-300 mb-1">Contraseña Provisional</label>
                    <input type="password" name="password" class="w-full p-3 bg-black bg-opacity-50 rounded-lg border border-purple-500 focus:outline-none focus:ring-1 focus:ring-pink-500" placeholder="******" required>
                </div>

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-orange-500 to-pink-600 rounded-lg font-bold text-white hover:opacity-90 transition mt-2">
                    Crear Perfil
                </button>
            </form>

            @if ($errors->any())
                <div class="mt-4 p-3 bg-red-500 bg-opacity-20 border border-red-500 rounded text-red-200 text-sm">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

    </div>

</main>

<!-- MODAL DE DESGLOSE DE RETIRO -->
<div id="withdraw-modal" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden flex items-center justify-center backdrop-blur-sm">
    <div class="bg-gray-900 border border-purple-600 rounded-xl p-8 max-w-md w-full shadow-2xl relative">

        <h2 class="text-2xl font-bold text-white mb-4">Detalle del Retiro</h2>

        <div class="space-y-4 mb-6">
            <div class="flex justify-between items-center text-gray-400">
                <span>Saldo Bruto:</span>
                <span class="font-semibold text-white" id="modal-total"></span>
            </div>
            <div class="flex justify-between items-center text-red-400">
                <span>Comisión Plataforma (30%):</span>
                <span class="font-semibold" id="modal-fee"></span>
            </div>
            <div class="h-px bg-gray-700 my-2"></div>
            <div class="flex justify-between items-center text-xl">
                <span class="text-green-400 font-bold">Tú recibes:</span>
                <span class="font-bold text-green-400" id="modal-payout"></span>
            </div>
        </div>

        <p class="text-xs text-gray-500 mb-6 text-center">
            Al confirmar, los fondos serán transferidos y el saldo se reiniciará a $0.00.
        </p>

        <div class="flex gap-4">
            <button onclick="document.getElementById('withdraw-modal').classList.add('hidden')" class="w-1/2 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold transition">
                Cancelar
            </button>
            <!-- Formulario apunta a la ruta compartida de Wallet -->
            <form action="{{ route('wallet.withdraw') }}" method="POST" class="w-1/2">
                @csrf
                <button type="submit" class="w-full py-2 bg-pink-600 hover:bg-pink-700 rounded-lg font-bold transition">
                    Confirmar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Pasamos la variable correcta desde PHP
    const currentBalance = {{ $currentBalance ?? 0 }};

    function openWithdrawModal() {
        const fee = currentBalance * 0.30;
        const payout = currentBalance * 0.70;
        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

        document.getElementById('modal-total').innerText = formatter.format(currentBalance);
        document.getElementById('modal-fee').innerText = "-" + formatter.format(fee);
        document.getElementById('modal-payout').innerText = formatter.format(payout);

        document.getElementById('withdraw-modal').classList.remove('hidden');
    }
</script>

</body>
</html>
