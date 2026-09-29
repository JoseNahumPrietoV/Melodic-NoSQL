<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Melodic - Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root { --dark: #0A0310; --purple: #49007E; --pink: #FF005B; }
    </style>
</head>
<body class="bg-gradient-to-b from-black via-purple-900 to-black text-white min-h-screen flex items-center justify-center">

<div class="max-w-6xl mx-auto px-8 text-center">

    <h1 class="text-5xl font-bold mb-4 text-pink-500">Pásate a Premium</h1>
    <p class="text-xl text-gray-300 mb-12">Disfruta de música sin límites, crea playlists y apoya a tus artistas favoritos.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        @foreach ($plans as $plan)
            <div class="bg-black bg-opacity-50 border-2 border-purple-800 p-8 rounded-2xl hover:border-pink-500 transition transform hover:-translate-y-2 duration-300 shadow-2xl relative overflow-hidden">

                @if($plan->months == 12)
                    <div class="absolute top-0 right-0 bg-yellow-400 text-black text-xs font-bold px-3 py-1 rounded-bl-lg">MEJOR VALOR</div>
                @endif

                <h2 class="text-3xl font-bold mb-2">{{ $plan->name }}</h2>
                <div class="text-4xl font-bold text-pink-500 mb-6">${{ $plan->cost }} <span class="text-lg text-gray-400 font-normal">/MXN</span></div>

                <ul class="text-left text-gray-300 space-y-3 mb-8 mx-auto max-w-xs">
                    <li class="flex items-center"><svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Música sin anuncios</li>
                    <li class="flex items-center"><svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Acceso ilimitado</li>
                    <li class="flex items-center"><svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Alta calidad de audio</li>
                </ul>

                <form method="POST" action="{{ route('subscription.store') }}">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button class="w-full py-3 bg-gradient-to-r from-purple-600 to-pink-600 rounded-lg font-bold text-lg hover:opacity-90 transition">
                        Suscribirse Ahora
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="mt-12">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-gray-400 hover:text-white underline">Cerrar sesión y volver después</button>
        </form>
    </div>
</div>

</body>
</html>
