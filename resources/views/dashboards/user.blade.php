<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Meta CSRF OBLIGATORIO para que funcione el registro de plays -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Melodic - Dashboard</title>
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
        <a href="#" class="hover:text-pink-400 transition">Inicio</a>
        <a href="{{ route('explore') }}" class="hover:text-pink-400 transition">Explorar</a>
        <a href="{{ route('playlists.index') }}" class="hover:text-pink-400 transition">Mis Playlists</a>
    </nav>
    <div class="flex items-center gap-4">
        <span class="text-gray-200">Hola, {{ auth()->user()->username }}</span>
        <div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-300 hover:text-pink-500 transition">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>

<main class="px-8 py-6 grid grid-cols-1 lg:grid-cols-3 gap-8">

    <section class="lg:col-span-2 bg-black bg-opacity-40 p-6 rounded-xl shadow-lg border border-purple-800">
        <h2 class="text-2xl font-semibold mb-4 text-yellow-400">Música para ti</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            @forelse ($songs as $song)
                <div class="bg-purple-900 bg-opacity-40 p-4 rounded-lg shadow-md hover:scale-105 transition group border border-transparent hover:border-pink-500">

                    <!-- IMAGEN -->
                    <div class="h-40 bg-black bg-opacity-50 rounded mb-3 flex items-center justify-center overflow-hidden relative">
                        @if ($song->image_path)
                            <img src="{{ asset('storage/' . $song->image_path) }}" class="w-full h-full object-cover">
                        @elseif($song->album->path)
                            <img src="{{ asset('storage/' . $song->album->path) }}" class="w-full h-full object-cover">
                        @else
                            <div class="text-gray-500">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12-3c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>
                            </div>
                        @endif

                        <!-- Botón Play Overlay (CORREGIDO) -->
                        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer">
                            <button
                                onclick="playSong(
                                    '{{ asset('storage/' . $song->file_path) }}',
                                    '{{ addslashes($song->name) }}',
                                    '{{ addslashes($song->album->artist->username ?? 'Desconocido') }}',
                                    '{{ $song->image_path ? asset('storage/' . $song->image_path) : ($song->album->path ? asset('storage/' . $song->album->path) : '') }}',
                                    '{{ $song->song_id }}'  /* <--- ¡ESTE ERA EL CAMPO QUE FALTABA! */
                                )"
                                class="w-12 h-12 bg-pink-600 rounded-full flex items-center justify-center hover:scale-110 transition shadow-lg"
                            >
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- INFO -->
                    <h3 class="font-bold text-lg truncate">{{ $song->name }}</h3>
                    <p class="text-sm text-gray-300 truncate">{{ $song->album->artist->username ?? 'Artista desconocido' }}</p>

                    <!-- Botón Play Texto (Este ya estaba bien) -->
                    <button
                        onclick="playSong(
                            '{{ asset('storage/' . $song->file_path) }}',
                            '{{ addslashes($song->name) }}',
                            '{{ addslashes($song->album->artist->username ?? 'Desconocido') }}',
                            '{{ $song->image_path ? asset('storage/' . $song->image_path) : ($song->album->path ? asset('storage/' . $song->album->path) : '') }}',
                            '{{ $song->song_id }}'
                        )"
                        class="mt-3 w-full py-1 bg-pink-600 hover:bg-pink-700 rounded-md text-sm font-semibold transition"
                    >
                        Reproducir
                    </button>
                </div>
            @empty
                <p class="text-gray-400 lg:col-span-3 text-center py-10">
                    Aún no hay música disponible. ¡Sé el primero en subir algo!
                </p>
            @endforelse

        </div>
    </section>

    <!-- Sección Playlists -->
    <section class="bg-black bg-opacity-40 p-6 rounded-xl shadow-lg border border-purple-800">
        <h2 class="text-2xl font-semibold mb-4 text-orange-400">Tus Playlists</h2>

        <div class="space-y-4">
            @forelse ($playlists as $playlist)
                <a href="{{ route('playlist.show', $playlist) }}" class="block p-4 bg-purple-900 bg-opacity-40 rounded-lg hover:bg-purple-800 transition cursor-pointer">
                    <h3 class="font-semibold">{{ $playlist->name }}</h3>
                    <p class="text-sm text-gray-300">
                        {{ $playlist->songs_count }} canciones
                    </p>
                </a>
            @empty
                <p class="text-gray-400">Aún no has creado ninguna playlist.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('playlist.store') }}" class="mt-6 space-y-3">
            @csrf
            <div>
                <label for="playlist_name" class="block text-sm font-semibold text-gray-300">
                    Nombre de la nueva playlist
                </label>
                <input type="text" id="playlist_name" name="name"
                       class="w-full p-2 mt-1 bg-purple-900 bg-opacity-40 rounded-lg outline-none focus:ring-2 focus:ring-pink-500"
                       placeholder="Mis éxitos..." required>
            </div>

            <button type="submit" class="w-full bg-pink-600 hover:bg-pink-700 py-2 rounded-lg font-semibold">
                Crear Playlist
            </button>
        </form>
    </section>

</main>

@include('components.player')

</body>
</html>
