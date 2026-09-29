<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Melodic - Explorar</title>
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
        <a href="{{ route('explore') }}" class="text-pink-400 font-bold transition border-b-2 border-pink-500">Explorar</a>
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

<main class="px-8 py-10 max-w-7xl mx-auto">

    <section class="text-center mb-12">
        <h2 class="text-4xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-pink-500">
            Descubre nueva música
        </h2>

        <form method="GET" action="{{ route('explore') }}" class="max-w-2xl mx-auto relative">
            <input type="text"
                   name="q"
                   value="{{ $query ?? '' }}"
                   placeholder="Buscar por canción o artista..."
                   class="w-full py-4 px-6 bg-purple-900 bg-opacity-40 border border-purple-600 rounded-full text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent text-lg shadow-lg transition"
            >
            <button type="submit" class="absolute right-2 top-2 bottom-2 px-6 bg-pink-600 hover:bg-pink-700 rounded-full font-semibold transition flex items-center">
                Buscar
            </button>
        </form>
    </section>

    <section>
        @if(isset($query) && $query)
            <h3 class="text-xl mb-6 text-gray-300">Resultados para: <span class="text-yellow-400">"{{ $query }}"</span></h3>
        @else
            <h3 class="text-xl mb-6 text-gray-300">Novedades recientes</h3>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

            @forelse ($songs as $song)
                <div class="bg-purple-900 bg-opacity-20 border border-purple-900 hover:border-pink-500 p-4 rounded-xl transition group hover:bg-opacity-40">
                    <div class="aspect-w-1 aspect-h-1 w-full bg-black bg-opacity-50 rounded-lg mb-4 flex items-center justify-center overflow-hidden relative h-48">
                        @if ($song->image_path)
                            <img src="{{ asset('storage/' . $song->image_path) }}" class="w-full h-full object-cover rounded-lg">
                        @elseif ($song->album->path)
                            <img src="{{ asset('storage/' . $song->album->path) }}" class="w-full h-full object-cover rounded-lg">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-500">No Image</div>
                        @endif

                        <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                            <button
                                onclick="playSong(
                                    '{{ asset('storage/' . $song->file_path) }}',
                                    '{{ addslashes($song->name) }}',
                                    '{{ addslashes($song->album->artist->username ?? 'Desconocido') }}',
                                    '{{ $song->image_path ? asset('storage/' . $song->image_path) : ($song->album->path ? asset('storage/' . $song->album->path) : '') }}',
                                    '{{ $song->song_id }}'
                                )"
                                class="w-12 h-12 bg-pink-600 rounded-full flex items-center justify-center hover:scale-110 transition shadow-lg"
                            >
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-lg truncate">{{ $song->name }}</h4>
                        <a href="#" class="text-sm text-gray-400 hover:text-orange-400 transition">
                            {{ $song->album->artist->username ?? 'Artista desconocido' }}
                        </a>
                    </div>

                    <div class="mt-4 border-t border-purple-800 pt-3">
                        <p class="text-xs text-gray-500 text-right">Album: {{ $song->album->title }}</p>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12">
                    <p class="text-gray-400 text-xl">No encontramos canciones que coincidan con tu búsqueda.</p>
                    <a href="{{ route('explore') }}" class="text-pink-400 hover:underline mt-2 block">Ver todo</a>
                </div>
            @endforelse

        </div>
    </section>

</main>

@include('components.player')
</body>
</html>
