<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Melodic - {{ $album->title }}</title>
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

<header class="flex items-center justify-between px-8 py-4 bg-opacity-30 bg-black backdrop-blur-md shadow-lg">
    <a href="/" class="text-3xl font-bold text-pink-500">Melodic</a>
    <nav class="flex gap-6 text-lg">

        @php
            $dashboardRoute = 'dashboard.user'; // Ruta por defecto
            if (auth()->user()->role === 'artist') {
                $dashboardRoute = 'dashboard.artist';
            } elseif (auth()->user()->role === 'label') {
                $dashboardRoute = 'dashboard.label';
            }
        @endphp
        <a href="{{ route($dashboardRoute) }}" class="hover:text-pink-400 transition">Mi Dashboard</a>

        <a href="#" class="hover:text-pink-400 transition">Explorar</a>
    </nav>
    <div>
        <a href="/logout" class="text-sm text-gray-300 hover:text-pink-500 transition">Cerrar sesión</a>
    </div>
</header>

<main class="px-8 py-10 max-w-6xl mx-auto">

    <section class="flex flex-col md:flex-row gap-8 items-center mb-12">
        <div class="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 rounded-lg shadow-lg">
            @if ($album->path)
                <img src="{{ asset('storage/' . $album->path) }}" alt="{{ $album->title }}" class="w-full h-full object-cover rounded-lg">
            @else
                <div class="w-full h-full bg-purple-900 bg-opacity-40 rounded-lg flex items-center justify-center text-gray-400">Portada</div>
            @endif
        </div>

        <div class="text-center md:text-left">
            <p class="text-sm font-semibold text-yellow-400 uppercase">Álbum</p>
            <h1 class="text-4xl md:text-6xl font-bold mt-1">{{ $album->title }}</h1>

            <h2 class="text-xl text-gray-300 mt-2">
                Un álbum de
                <span class="font-bold text-orange-400">{{ $album->artist->username }}</span>
            </h2>

            <p class="text-gray-400 mt-1">{{ $album->songs->count() }} canciones</p>

            <button class="mt-6 px-8 py-3 bg-pink-600 hover:bg-pink-700 rounded-lg font-semibold text-lg">
                Reproducir Álbum
            </button>
        </div>
    </section>

    <section class="bg-black bg-opacity-40 p-6 rounded-xl shadow-lg border border-purple-800">
        <h2 class="text-2xl font-semibold mb-4 text-yellow-400">Canciones</h2>

        <div class="space-y-2">
            @forelse ($album->songs as $index => $song)
                <div class="flex items-center justify-between p-3 rounded-lg hover:bg-purple-900 bg-opacity-40 transition group">
                    <div class="flex items-center gap-4">
                        <span class="text-lg text-gray-400">{{ $index + 1 }}</span>
                        <div>
                            <p class="text-lg font-semibold text-white">{{ $song->name }}</p>
                            <p class="text-sm text-gray-300">{{ $album->artist->username }}</p>
                        </div>
                    </div>
                    <button class="px-4 py-1 bg-pink-600 hover:bg-pink-700 rounded-md opacity-0 group-hover:opacity-100 transition-opacity">
                        Play
                    </button>
                </div>
            @empty
                <p class="text-gray-400 p-3">Este álbum aún no tiene canciones.</p>
            @endforelse
        </div>
    </section>

</main>
</body>
</html>
