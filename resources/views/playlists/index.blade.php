<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Melodic - Mis Playlists</title>
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
    <script type="module">
        import * as Turbo from 'https://esm.sh/@hotwired/turbo@7.3.0';
    </script>
</head>
<body class="bg-gradient-to-b from-black via-purple-900 to-black text-white min-h-screen">

<header class="flex items-center justify-between px-8 py-4 bg-opacity-30 bg-black backdrop-blur-md shadow-lg">
    <h1 class="text-3xl font-bold text-pink-500">Melodic</h1>
    <nav class="flex gap-6 text-lg">
        @php
            $dashboardRoute = match(auth()->user()->role) {
                'artist' => 'dashboard.artist',
                'label'  => 'dashboard.label',
                default  => 'dashboard.user',
            };
        @endphp
        <a href="{{ route($dashboardRoute) }}" class="hover:text-pink-400 transition">Inicio</a>
        <a href="{{ route('explore') }}" class="hover:text-pink-400 transition">Explorar</a>
        <a href="{{ route('playlists.index') }}" class="text-pink-400 font-bold transition border-b-2 border-pink-500">Mis Playlists</a>
    </nav>
    <div>
        <div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf <button type="submit" class="text-sm text-gray-300 hover:text-pink-500 transition">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>

<main class="px-8 py-10 max-w-7xl mx-auto">

    <div class="flex items-center justify-between mb-8">
        <h2 class="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-pink-500">
            Tu Biblioteca
        </h2>
        <span class="text-gray-400">{{ $playlists->count() }} colecciones</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

        <div class="bg-purple-900 bg-opacity-20 border-2 border-dashed border-purple-600 hover:border-pink-500 p-6 rounded-xl transition group flex flex-col items-center justify-center cursor-pointer h-80 relative"
             onclick="document.getElementById('createPlaylistForm').classList.remove('hidden'); this.classList.add('hidden')">

            <div class="text-center">
                <div class="w-16 h-16 rounded-full bg-purple-800 flex items-center justify-center mb-4 mx-auto group-hover:bg-pink-600 transition">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </div>
                <h3 class="font-bold text-lg text-gray-300 group-hover:text-white">Crear Nueva Playlist</h3>
            </div>
        </div>

        <div id="createPlaylistForm" class="hidden bg-purple-900 bg-opacity-40 border border-pink-500 p-6 rounded-xl h-80 flex flex-col justify-center">
            <h3 class="font-bold text-lg text-white mb-3 text-center">Nueva Playlist</h3>

            <form method="POST" action="{{ route('playlist.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf

                <input type="text" name="name" placeholder="Nombre de la playlist..." class="w-full p-2 bg-black bg-opacity-50 rounded border border-purple-500 focus:outline-none text-sm text-white placeholder-gray-400" required>

                <div>
                    <label class="block text-xs text-gray-300 mb-1">Portada (Opcional)</label>
                    <input type="file" name="cover" class="w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-2 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-pink-600 file:text-white hover:file:bg-pink-700">
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="document.getElementById('createPlaylistForm').classList.add('hidden'); document.querySelector('.group').classList.remove('hidden')" class="w-1/2 py-1 bg-gray-600 rounded text-sm font-bold hover:bg-gray-500">Cancelar</button>
                    <button type="submit" class="w-1/2 py-1 bg-pink-600 rounded text-sm font-bold hover:bg-pink-700">Crear</button>
                </div>
            </form>
        </div>

        @foreach ($playlists as $playlist)
            <a href="{{ route('playlist.show', $playlist) }}" class="bg-black bg-opacity-40 border border-purple-900 hover:border-orange-500 p-4 rounded-xl transition group h-80 flex flex-col">

                <div class="flex-grow rounded-lg mb-4 flex items-center justify-center relative overflow-hidden bg-gradient-to-br from-purple-800 to-black">
                    @if ($playlist->path)
                        <img src="{{ asset('storage/' . $playlist->path) }}" alt="{{ $playlist->name }}" class="w-full h-full object-cover">
                    @else
                        <svg class="w-12 h-12 text-purple-400 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12-3c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>
                    @endif

                    <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                        <span class="px-4 py-2 bg-orange-500 rounded-full text-sm font-bold text-white">Ver canciones</span>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-lg truncate text-white group-hover:text-orange-400 transition">{{ $playlist->name }}</h3>
                    <p class="text-sm text-gray-400">{{ $playlist->songs_count }} canciones</p>
                </div>
            </a>
        @endforeach

    </div>

    @include('components.player')
</main>
</body>
</html>
