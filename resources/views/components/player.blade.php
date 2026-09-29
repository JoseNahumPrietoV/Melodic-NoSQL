<!-- REPRODUCTOR FIJO PERSISTENTE -->
<!-- ID y data-turbo-permanent son vitales para que no se corte la música al navegar -->
<div id="music-player" data-turbo-permanent class="fixed bottom-0 left-0 right-0 bg-black bg-opacity-90 backdrop-blur-lg border-t border-purple-800 px-4 py-3 hidden transition-transform duration-300 transform translate-y-full z-50">

    <audio id="audio-element"></audio>

    <div class="max-w-7xl mx-auto flex items-center justify-between">

        <!-- Info Canción -->
        <div class="flex items-center gap-4 w-1/3">
            <div id="player-cover-container" class="w-14 h-14 bg-purple-900 rounded overflow-hidden flex-shrink-0">
                <img id="player-cover" src="" class="w-full h-full object-cover hidden">
                <div id="player-cover-placeholder" class="w-full h-full flex items-center justify-center text-pink-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12-3c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"></path></svg>
                </div>
            </div>
            <div class="truncate">
                <h4 id="player-title" class="text-white font-bold text-sm truncate">Selecciona una canción</h4>
                <p id="player-artist" class="text-gray-400 text-xs truncate">...</p>
            </div>
        </div>

        <!-- Controles Centrales -->
        <div class="flex flex-col items-center w-1/3">
            <div class="flex items-center gap-6">
                <button onclick="playPrevious()" class="text-gray-400 hover:text-white"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg></button>

                <button id="player-play-btn" onclick="togglePlay()" class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:scale-105 transition text-black">
                    <svg id="icon-play" class="w-5 h-5 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <svg id="icon-pause" class="w-5 h-5 hidden" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </button>

                <button onclick="playNextInQueue()" class="text-gray-400 hover:text-white"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg></button>
            </div>
            <div class="w-full mt-2 bg-gray-700 rounded-full h-1 cursor-pointer group" id="progress-container">
                <div id="player-progress" class="bg-pink-500 h-1 rounded-full w-0 group-hover:bg-pink-400 transition-all relative">
                    <div class="absolute right-0 top-1/2 transform -translate-y-1/2 w-3 h-3 bg-white rounded-full hidden group-hover:block"></div>
                </div>
            </div>
        </div>

        <!-- Volumen -->
        <div class="w-1/3 flex justify-end items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></path></svg>
            <input type="range" class="w-24 h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer" oninput="document.getElementById('audio-element').volume = this.value / 100">
        </div>
    </div>

    <!-- LÓGICA JAVASCRIPT DEL REPRODUCTOR -->
    <script>
        const audio = document.getElementById('audio-element');
        const player = document.getElementById('music-player');
        const playerTitle = document.getElementById('player-title');
        const playerArtist = document.getElementById('player-artist');
        const playerCover = document.getElementById('player-cover');
        const playerPlaceholder = document.getElementById('player-cover-placeholder');
        const progressBar = document.getElementById('player-progress');
        const iconPlay = document.getElementById('icon-play');
        const iconPause = document.getElementById('icon-pause');

        let currentQueue = [];
        let currentIndex = 0;

        // 1. Reproducir UNA canción (Limpiando cola)
        // AHORA ACEPTA UN QUINTO ARGUMENTO: songId
        window.playSong = function(url, title, artist, image, songId) {
            currentQueue = [{ url, title, artist, image, id: songId }];
            currentIndex = 0;
            loadAndPlay(url, title, artist, image, songId);
        }

        // 2. Reproducir LISTA (Queue)
        window.playAll = function(songs) {
            if (!songs || songs.length === 0) return;
            currentQueue = songs;
            currentIndex = 0;
            playNextInQueue();
        }

        window.playNextInQueue = function() {
            if (currentIndex >= currentQueue.length - 1) return; // Fin de la lista
            currentIndex++;
            const song = currentQueue[currentIndex];
            // Usamos song.id o song.song_id según como venga en el JSON
            const id = song.id || song.song_id;
            loadAndPlay(song.url, song.title, song.artist, song.image, id);
        }

        window.playPrevious = function() {
            if (currentIndex <= 0) return;
            currentIndex--;
            const song = currentQueue[currentIndex];
            const id = song.id || song.song_id;
            loadAndPlay(song.url, song.title, song.artist, song.image, id);
        }

        // FUNCIÓN PRINCIPAL DE CARGA
        function loadAndPlay(url, title, artist, image, songId) {
            // UI
            player.classList.remove('hidden');
            setTimeout(() => player.classList.remove('translate-y-full'), 10);
            playerTitle.innerText = title;
            playerArtist.innerText = artist;

            if(image) {
                playerCover.src = image;
                playerCover.classList.remove('hidden');
                playerPlaceholder.classList.add('hidden');
            } else {
                playerCover.classList.add('hidden');
                playerPlaceholder.classList.remove('hidden');
            }

            // Audio
            audio.src = url;
            audio.play();
            updatePlayButton(true);

            // ============================================================
            // CORRECCIÓN DEL ERROR 419: REGISTRO DE LA REPRODUCCIÓN ($)
            // ============================================================
            if (songId) {
                // Obtenemos el token CSRF del meta tag
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                if (csrfToken) {
                    fetch(`/song/${songId}/play`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken // <-- ¡AQUÍ ESTÁ LA CLAVE!
                        }
                    })
                        .then(response => {
                            if (!response.ok) console.error("Error registrando play:", response.status);
                            else console.log("Play registrado ($$$)");
                        })
                        .catch(err => console.error("Error de red:", err));
                } else {
                    console.warn("No se encontró el meta tag CSRF. El cobro no se registrará.");
                }
            }
        }

        window.togglePlay = function() {
            if (audio.paused) audio.play();
            else audio.pause();
            updatePlayButton(!audio.paused);
        }

        function updatePlayButton(isPlaying) {
            if(isPlaying) {
                iconPlay.classList.add('hidden');
                iconPause.classList.remove('hidden');
            } else {
                iconPlay.classList.remove('hidden');
                iconPause.classList.add('hidden');
            }
        }

        audio.addEventListener('timeupdate', () => {
            if(audio.duration) {
                const percent = (audio.currentTime / audio.duration) * 100;
                progressBar.style.width = percent + '%';
            }
        });

        audio.addEventListener('ended', () => {
            if (currentQueue.length > 0 && currentIndex < currentQueue.length - 1) {
                playNextInQueue();
            } else {
                updatePlayButton(false);
            }
        });
    </script>
</div>
