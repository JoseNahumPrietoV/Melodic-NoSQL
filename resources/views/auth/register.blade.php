<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registrarse — Melodic</title>
    <style>
        :root{
            --bg: #0A0310; /* fondo principal */
            --accent-1: #49007E; /* morado */
            --accent-2: #FF005B; /* rojo/pink */
            --accent-3: #FF7D10; /* naranja */
            --accent-4: #FFB238; /* amarillo */
            --card: rgba(255,255,255,0.03);
            --muted: rgba(255,255,255,0.6);
            --glass: rgba(255,255,255,0.04);
            --input-bg: rgba(255,255,255,0.02);
            --radius: 12px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
        }

        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            background: radial-gradient(1200px 600px at 10% 10%, rgba(73,0,126,0.14), transparent),
            radial-gradient(900px 500px at 90% 90%, rgba(255,125,16,0.07), transparent),
            var(--bg);
            color:#fff;
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:32px;
        }

        .container{
            width:100%;
            max-width:980px;
            display:grid;
            grid-template-columns: 1fr 420px;
            gap:28px;
            align-items:center;
        }

        .hero{
            padding:36px;
            border-radius:var(--radius);
            background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
            box-shadow: 0 10px 30px rgba(3,2,6,0.6);
            min-height:420px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            gap:18px;
            overflow:hidden;
        }

        .logo{
            display:flex;
            gap:12px;
            align-items:center;
            font-weight:700;
            letter-spacing:0.6px;
            font-size:20px;
        }

        .logo-mark{
            width:44px;
            height:44px;
            border-radius:10px;
            display:inline-grid;
            place-items:center;
            background: linear-gradient(135deg, var(--accent-1), var(--accent-2));
            box-shadow: 0 6px 20px rgba(73,0,126,0.24);
        }

        .logo-mark svg{width:22px;height:22px}

        h1{margin:0;font-size:34px}
        p.lead{margin:0;color:var(--muted);max-width:54ch}

        .features{
            display:flex;
            gap:12px;
            margin-top:8px;
            flex-wrap:wrap;
        }

        .pill{
            background:linear-gradient(90deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
            padding:8px 12px;border-radius:999px;font-size:13px;color:var(--muted);
            display:inline-flex;gap:8px;align-items:center;border:1px solid rgba(255,255,255,0.03)
        }

        .card{
            padding:28px;
            border-radius:14px;
            background:var(--card);
            border:1px solid rgba(255,255,255,0.04);
            box-shadow: 0 8px 30px rgba(3,2,6,0.5);
        }

        form{display:flex;flex-direction:column;gap:14px}

        label{font-size:13px;color:var(--muted);display:block;margin-bottom:6px}
        input[type="text"], input[type="email"], input[type="password"]{
            width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);
            background:var(--input-bg);color:#fff;font-size:15px;outline:none;
            transition:box-shadow .12s, border-color .12s
        }
        input:focus{box-shadow:0 6px 24px rgba(73,0,126,0.12);border-color:var(--accent-1)}

        .row{display:flex;gap:12px}
        .row > *{flex:1}

        .btn{
            padding:12px 14px;border-radius:10px;border:0;font-weight:600;font-size:15px;cursor:pointer;
            background:linear-gradient(90deg,var(--accent-2),var(--accent-1));color:#fff;box-shadow:0 8px 30px rgba(73,0,126,0.18)
        }

        .btn.secondary{
            background:transparent;border:1px solid rgba(255,255,255,0.06);color:var(--muted)
        }

        .small{font-size:13px;color:var(--muted)}

        .socials{display:flex;gap:10px}
        .social-btn{flex:1;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:var(--muted);display:flex;align-items:center;justify-content:center;gap:8px}

        .legal{font-size:13px;color:var(--muted);text-align:center;margin-top:6px}

        .error{color:#FF7D10;font-size:13px;margin-top:6px}

        @media (max-width:900px){
            .container{grid-template-columns:1fr;max-width:680px}
            .hero{order:2}
        }

        /* subtle music waves decoration */
        .waves{position:absolute;right:-80px;top:-40px;opacity:0.06;filter:blur(6px)}
    </style>
</head>
<body>
<div class="container">

    <section class="hero">
        <div class="logo">
            <div class="logo-mark" aria-hidden>
                <!-- simple music note -->
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
                    <path d="M9 17.5C9 18.8807 7.88071 20 6.5 20C5.11929 20 4 18.8807 4 17.5C4 16.1193 5.11929 15 6.5 15C7.88071 15 9 16.1193 9 17.5Z" fill="white" opacity="0.95"/>
                    <path d="M16 6V14.1C15.63 13.98 15.25 13.92 14.86 13.92C12.79 13.92 11.21 15.09 11.21 16.5C11.21 17.91 12.79 19.08 14.86 19.08C16.93 19.08 18.51 17.91 18.51 16.5V7.5L16 6Z" fill="white" opacity="0.95"/>
                </svg>
            </div>
            <div>
                Melodic
                <div style="font-size:12px;color:var(--muted);">música online • descubre y comparte</div>
            </div>
        </div>

        <div style="margin-top:18px">
            <h1>Bienvenido a Melodic</h1>
            <p class="lead">Crea tu cuenta para guardar playlists, seguir artistas y descubrir música hecha para tu estado de ánimo.</p>

            <div class="features" style="margin-top:18px">
                <span class="pill">🎧 Recomendaciones personalizadas</span>
                <span class="pill">✨ Mixes exclusivos</span>
                <span class="pill">📁 Playlists en la nube</span>
            </div>
        </div>

        <svg class="waves" width="420" height="180" viewBox="0 0 420 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
            <path d="M0 100 C60 40, 120 160, 200 100 C280 40, 340 160, 420 100 L420 180 L0 180 Z" fill="url(#g)" />
            <defs>
                <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
                    <stop offset="0" stop-color="#49007E" stop-opacity="0.7"/>
                    <stop offset="1" stop-color="#FF7D10" stop-opacity="0.6"/>
                </linearGradient>
            </defs>
        </svg>

    </section>

    <aside class="card">
        <h2 style="margin-top:0;margin-bottom:6px">Crear cuenta</h2>
        <p class="small">Regístrate como usuario o artista independiente.</p>

        <button id="toggleArtist" class="btn secondary" type="button" style="margin-bottom:14px;text-align:center;">¿Eres artista?</button>

        <div id="formsContainer" style="position:relative;overflow:hidden;">

            <!-- FORMULARIO USUARIO -->
            <form id="userForm" method="POST" action="{{ route('register') }}" style="display:flex;flex-direction:column;gap:14px;transition:transform .4s ease, opacity .4s ease;">
                @csrf
                <h3 style="margin:0 0 6px 0">Registro de Usuario</h3>

                <div>
                    <label for="name">Nombre completo</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" autofocus />
                    @error('name')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" />
                    @error('email')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="row">
                    <div>
                        <label for="password">Contraseña</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" />
                        @error('password')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="password_confirmation">Confirmar contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                    </div>
                </div>

                <input type="hidden" name="account_type" value="user">

                <button class="btn" type="submit">Crear cuenta</button>
            </form>

            <!-- FORMULARIO ARTISTA -->
            <form id="artistForm" method="POST" action="{{ route('register') }}" style="position:absolute;top:0;left:0;width:100%;opacity:0;transform:translateX(40px);pointer-events:none;display:flex;flex-direction:column;gap:14px;transition:transform .4s ease, opacity .4s ease;">
                @csrf
                <h3 style="margin:0 0 6px 0;color:var(--accent-3)">Registro de Artista Independiente</h3>

                <div>
                    <label for="artist_name">Nombre artístico</label>
                    <input id="artist_name" name="name" type="text" value="{{ old('name') }}" required />
                </div>

                <div>
                    <label for="artist_email">Correo electrónico</label>
                    <input id="artist_email" name="email" type="email" value="{{ old('email') }}" required />
                </div>

                <div class="row">
                    <div>
                        <label for="artist_password">Contraseña</label>
                        <input id="artist_password" name="password" type="password" required />
                    </div>

                    <div>
                        <label for="artist_password_confirmation">Confirmar contraseña</label>
                        <input id="artist_password_confirmation" name="password_confirmation" type="password" required />
                    </div>
                </div>

                <input type="hidden" name="account_type" value="artist">

                <button class="btn" type="submit" style="background:linear-gradient(90deg,var(--accent-3),var(--accent-2));">Registrar artista</button>
            </form>

        </div>

        <script>
            const toggleBtn = document.getElementById('toggleArtist');
            const userForm = document.getElementById('userForm');
            const artistForm = document.getElementById('artistForm');
            let artistMode = false;

            toggleBtn.addEventListener('click', () => {
                artistMode = !artistMode;

                if(artistMode){
                    toggleBtn.textContent = "Volver a usuario";
                    document.body.style.background = "linear-gradient(140deg, #FF7D10 0%, #FF005B 100%)";

                    userForm.style.opacity = 0;
                    userForm.style.transform = "translateX(-40px)";
                    userForm.style.pointerEvents = "none";

                    artistForm.style.opacity = 1;
                    artistForm.style.transform = "translateX(0)";
                    artistForm.style.pointerEvents = "auto";

                } else {
                    toggleBtn.textContent = "¿Eres artista?";
                    document.body.style.background = "radial-gradient(1200px 600px at 10% 10%, rgba(73,0,126,0.14), transparent), radial-gradient(900px 500px at 90% 90%, rgba(255,125,16,0.07), transparent), #0A0310";

                    artistForm.style.opacity = 0;
                    artistForm.style.transform = "translateX(40px)";
                    artistForm.style.pointerEvents = "none";

                    userForm.style.opacity = 1;
                    userForm.style.transform = "translateX(0)";
                    userForm.style.pointerEvents = "auto";
                }
            });
        </script>

    </aside>

</div>
</body>
</html>
