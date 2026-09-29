<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Melodic - Dashboard Artista</title>
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
    <h1 class="text-3xl font-bold text-pink-500">Melodic</h1>
    <nav class="flex gap-6 text-lg">
        <a href="#" class="hover:text-pink-400 transition">Inicio</a>
        <a href="#subir" class="hover:text-pink-400 transition">Subir Canción</a>
        <a href="#albumes" class="hover:text-pink-400 transition">Álbumes</a>
        <!-- Mostramos el link de Admin solo si es independiente -->
        @if ($artist->label === null)
            <a href="#gestion" class="hover:text-pink-400 transition">Administración</a>
        @endif
    </nav>
    <div class="flex items-center gap-4">
        <span class="text-gray-200">Hola, {{ $artist->username }}</span>
        <div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf <button type="submit" class="text-sm text-gray-300 hover:text-pink-500 transition">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>

<main class="px-8 py-10 space-y-16">

    @if ($errors->any())
        <div class="bg-red-500 bg-opacity-20 border border-red-500 text-red-200 px-4 py-3 rounded mb-6">
            <strong class="font-bold">Error:</strong>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Subir canción -->
    <section id="subir" class="bg-black bg-opacity-40 p-8 rounded-xl shadow-lg border border-purple-800 max-w-4xl mx-auto">
        <h2 class="text-3xl font-semibold mb-6 text-yellow-400">Subir nueva canción</h2>

        <!-- FORMULARIO 1: SUBIR CANCIÓN -->
        <form method="POST" action="{{ route('song.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf <!-- ¡IMPORTANTE! -->

            <div>
                <label class="block mb-1 font-semibold">Título de la canción</label>
                <input type="text" name="name" class="w-full p-3 bg-purple-900 bg-opacity-40 rounded-lg outline-none" required />
            </div>

            <!-- CAMBIO IMPORTANTE: Necesitamos saber a qué álbum pertenece -->
            <div>
                <label class="block mb-1 font-semibold">Álbum</label>
                <select name="album_id" class="w-full p-3 bg-purple-900 bg-opacity-40 rounded-lg outline-none">
                    <option value="">-- Crear como Single (Nuevo Álbum) --</option>
                    @foreach ($albums as $album)
                        <option value="{{ $album->album_id }}">{{ $album->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block mb-1 font-semibold">Archivo de audio (.mp3, .wav)</label>
                <input type="file" name="audio_file" class="w-full text-gray-300" required />
            </div>

            <div>
                <label class="block mb-1 font-semibold">Portada de la canción (opcional)</label>
                <input type="file" name="cover_file" class="w-full text-gray-300" />
            </div>

            <button class="px-6 py-2 bg-pink-600 hover:bg-pink-700 rounded-lg font-semibold w-full">Subir Canción</button>
        </form>
    </section>

    <!-- Crear álbum y listar álbumes -->
    <section id="albumes" class="max-w-6xl mx-auto space-y-10">
        <div class="bg-black bg-opacity-40 p-8 rounded-xl shadow-lg border border-purple-800">
            <h2 class="text-3xl font-semibold mb-6 text-orange-400">Crear nuevo álbum</h2>

            <!-- FORMULARIO 2: CREAR ÁLBUM -->
            <form method="POST" action="{{ route('album.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf <!-- ¡IMPORTANTE! -->

                <div>
                    <label class="block mb-1 font-semibold">Nombre del álbum</label>
                    <input type="text" name="title" class="w-full p-3 bg-purple-900 bg-opacity-40 rounded-lg outline-none" required />
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Portada del álbum (opcional)</label>
                    <input type="file" name="cover" class="w-full text-gray-300" />
                </div>

                <button class="px-6 py-2 bg-pink-600 hover:bg-pink-700 rounded-lg font-semibold w-full">Crear Álbum</button>
            </form>
        </div>

        <div class="bg-black bg-opacity-40 p-8 rounded-xl shadow-lg border border-purple-800">
            <h2 class="text-3xl font-semibold mb-6 text-yellow-400">Tus Álbumes</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- BUCLE PARA MOSTRAR ÁLBUMES -->
                @forelse ($albums as $album)
                    <div class="bg-purple-900 bg-opacity-40 p-5 rounded-lg shadow-md hover:scale-105 transition cursor-pointer">
                        <div class="h-40 bg-black bg-opacity-50 rounded mb-3 flex items-center justify-center text-gray-400">
                            @if ($album->path)
                                <img src="{{ asset('storage/' . $album->path) }}" alt="{{ $album->title }}" class="w-full h-full object-cover rounded">
                            @else
                                Portada
                            @endif
                        </div>
                        <h3 class="font-bold text-lg">{{ $album->title }}</h3>
                        <p class="text-sm text-gray-300">{{ $album->songs_count }} canciones</p>
                        <a href="{{ route('album.show', $album) }}"
                           class="block text-center mt-3 w-full py-1 bg-pink-600 hover:bg-pink-700 rounded-md">
                            Ver Álbum
                        </a>
                    </div>
                @empty
                    <p class="text-gray-400 md:col-span-3">Aún no has creado ningún álbum. ¡Crea uno!</p>
                @endforelse
            </div>
        </div>
    </section>

    <!-- REGLA DE NEGOCIO: MOSTRAR SOLO SI 'label' ES NULL -->
    <!-- Administración (solo artistas independientes) -->
    @if ($artist->label === null)
        <section id="gestion" class="bg-black bg-opacity-40 p-8 rounded-xl shadow-lg border border-purple-800 max-w-4xl mx-auto">
            <h2 class="text-3xl font-semibold mb-6 text-yellow-400">Administración del Artista</h2>
            <p class="text-gray-300 mb-6">Aquí puedes ver tus ganancias y realizar retiros.</p>

            <div class="flex justify-between items-center bg-purple-900 bg-opacity-40 p-5 rounded-lg mb-6 border border-purple-600">
                <div>
                    <p class="text-lg font-semibold text-gray-300">Saldo Disponible:</p>
                    <p class="text-3xl font-bold text-green-400">${{ number_format($currentBalance, 2) }} MXN</p>
                </div>

                @if($currentBalance > 0)
                    <button onclick="openWithdrawModal()" class="px-6 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold shadow-lg transition transform hover:scale-105">
                        Retirar Fondos
                    </button>
                @else
                    <button disabled class="px-6 py-2 bg-gray-600 text-gray-400 rounded-lg font-semibold cursor-not-allowed">
                        Sin Fondos
                    </button>
                @endif
            </div>

            <h3 class="text-xl font-semibold text-orange-400 mb-3">Últimos retiros</h3>
            <ul class="space-y-2 mb-6">
                @forelse ($withdrawals as $withdrawal)
                    <li class="bg-purple-900 bg-opacity-40 p-3 rounded-lg flex justify-between items-center">
                        <span class="text-gray-300">Retiro completado</span>
                        <div class="text-right">
                            <span class="block font-bold text-green-400">+ ${{ number_format($withdrawal->amount, 2) }}</span>
                            <span class="text-xs text-gray-500">{{ $withdrawal->at->format('d/m/Y H:i') }}</span>
                        </div>
                    </li>
                @empty
                    <li class="bg-purple-900 bg-opacity-20 p-3 rounded-lg text-gray-500 text-center">
                        No hay historial de retiros aún.
                    </li>
                @endforelse
            </ul>
        </section>
    @endif

</main>
<!-- MODAL DE RETIRO (Reutilizable) -->
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
    // Pasamos la variable de PHP a JS, si es null ponemos 0
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
