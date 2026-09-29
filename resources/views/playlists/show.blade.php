<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Melodic - {{ $playlist->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <script type="module">
        import * as Turbo from 'https://esm.sh/@hotwired/turbo@7.3.0';
    </script>

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
<body class="bg-gradient-to-b from-black via-purple-900 to-black text-white min-h-screen pb-24">

<header class="flex items-center justify-between px-8 py-4 bg-opacity-30 bg-black backdrop-blur-md shadow-lg">
    <a href="{{ route('dashboard.user') }}" class="text-3xl font-bold text-pink-500">Melodic</a>
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
        <a href="{{ route('playlists.index') }}" class="hover:text-pink-400 transition">Mis Playlists</a>
    </nav>
    <div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-300 hover:text-pink-500 transition">
                Cerrar sesión
            </button>
        </form>
    </div>
</header>

<main class="px-8 py-10 max-w-6xl mx-auto space-y-12">

    @php
        // Creamos un array limpio para JavaScript
        // Usamos values() al final para asegurar que sea un array JSON [..] y no un objeto {..}
        $playlistSongsJson = $playlist->songs->map(function($song) {
            return [
                'id' => $song->song_id,
                'url' => asset('storage/' . $song->file_path),
                'title' => addslashes($song->name),
                'artist' => addslashes($song->album->artist->username ?? 'Desconocido'),
                'image' => $song->image_path ? asset('storage/' . $song->image_path) : ($song->album->path ? asset('storage/' . $song->album->path) : null)
            ];
        })->values();
    @endphp

    <script>
        window.currentPlaylistData = @json($playlistSongsJson);
    </script>

    <section class="flex flex-col md:flex-row gap-8 items-center">
        <div class="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 rounded-lg shadow-lg bg-purple-900 bg-opacity-40 flex items-center justify-center overflow-hidden relative group">
            @if ($playlist->path)
                <img src="{{ asset('storage/' . $playlist->path) }}" alt="{{ $playlist->name }}" class="w-full h-full object-cover">
            @else
                <svg class="w-24 h-24 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12-3c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>
            @endif

            <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 cursor-pointer">
                <button onclick="playAll(window.currentPlaylistData)" class="w-16 h-16 bg-pink-600 rounded-full flex items-center justify-center hover:scale-110 transition shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </button>
            </div>
        </div>

        <div class="text-center md:text-left">
            <p class="text-sm font-semibold text-yellow-400 uppercase">Playlist</p>
            <h1 class="text-4xl md:text-6xl font-bold mt-1">{{ $playlist->name }}</h1>

            <h2 class="text-xl text-gray-300 mt-2">
                Creada por <span class="font-bold text-orange-400">{{ $playlist->user->username }}</span>
            </h2>

            <p class="text-gray-400 mt-1">{{ $playlist->songs->count() }} canciones</p>
        </div>
    </section>

    <section class="bg-black bg-opacity-40 p-6 rounded-xl shadow-lg border border-purple-800">
        <h2 class="text-2xl font-semibold mb-4 text-yellow-400">Contenido</h2>

        <div class="space-y-2">
            @forelse ($playlist->songs as $index => $song)
                <div class="flex items-center justify-between p-3 rounded-lg hover:bg-purple-900 bg-opacity-40 transition group">
                    <div class="flex items-center gap-4">
                        <span class="text-lg text-gray-400 w-6 text-center">{{ $index + 1 }}</span>
                        <div>
                            <p class="text-lg font-semibold text-white">{{ $song->name }}</p>
                            <p class="text-sm text-gray-300">{{ $song->album->artist->username ?? 'Artista desconocido' }}</p>
                        </div>
                    </div>

                    <button
                        onclick="playSong(
                            '{{ asset('storage/' . $song->file_path) }}',
                            '{{ addslashes($song->name) }}',
                            '{{ addslashes($song->album->artist->username ?? 'Artista') }}',
                            '{{ $song->image_path ? asset('storage/' . $song->image_path) : ($song->album->path ? asset('storage/' . $song->album->path) : '') }}'
                        )"
                        class="px-4 py-1 bg-pink-600 hover:bg-pink-700 rounded-md text-white font-semibold transition opacity-0 group-hover:opacity-100"
                    >
                        Play
                    </button>
                </div>
            @empty
                <p class="text-gray-400 p-3">Tu playlist está vacía.</p>
            @endforelse
        </div>
    </section>

    <section class="bg-black bg-opacity-40 p-6 rounded-xl shadow-lg border border-purple-800">
        <h2 class="text-2xl font-semibold mb-4 text-orange-400">Añadir canciones</h2>

        <form method="GET" action="{{ route('playlist.show', $playlist) }}" class="mb-6 flex gap-2">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar canción o artista..." class="w-full p-3 bg-purple-900 bg-opacity-40 rounded-lg outline-none focus:ring-2 focus:ring-pink-500 border border-purple-600 placeholder-gray-400 text-white">
            <button type="submit" class="px-6 bg-pink-600 hover:bg-pink-700 rounded-lg font-bold">Buscar</button>
            @if(isset($search))
                <a href="{{ route('playlist.show', $playlist) }}" class="px-4 flex items-center bg-gray-600 hover:bg-gray-500 rounded-lg">X</a>
            @endif
        </form>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            @if(isset($availableSongs) && $availableSongs->count() > 0)
                @foreach ($availableSongs as $song)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-purple-900 bg-opacity-40 transition group border border-transparent hover:border-purple-600">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 flex-shrink-0">
                                @if ($song->image_path)
                                    <img src="{{ asset('storage/' . $song->image_path) }}" class="w-full h-full object-cover rounded">
                                @elseif($song->album->path)
                                    <img src="{{ asset('storage/' . $song->album->path) }}" class="w-full h-full object-cover rounded">
                                @else
                                    <div class="w-full h-full rounded bg-purple-800 flex items-center justify-center text-pink-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12-3c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>
                                    </div>
                                @endif
                            </div>
                            <div>
                                <p class="text-lg font-semibold text-white">{{ $song->name }}</p>
                                <p class="text-sm text-gray-300">{{ $song->album->artist->username ?? 'Artista desconocido' }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="playSong('{{ asset('storage/' . $song->file_path) }}', '{{ addslashes($song->name) }}', '{{ addslashes($song->album->artist->username ?? '') }}', '')" class="p-2 text-pink-500 hover:text-white">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('playlist.addSong', $playlist) }}">
                                @csrf
                                <input type="hidden" name="song_id" value="{{ $song->song_id }}">
                                <button type="submit" class="px-4 py-2 bg-pink-600 hover:bg-pink-700 rounded-md font-semibold text-sm">Añadir</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-8 text-gray-400">
                    <p>{{ isset($search) ? 'No se encontraron canciones.' : 'Utiliza el buscador para encontrar canciones.' }}</p>
                </div>
            @endif
        </div>
    </section>

</main>

@include('components.player')
</body>
</html>
