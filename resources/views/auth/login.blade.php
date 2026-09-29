<!-- resources/views/auth/login.blade.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Melodic — Login</title>
    <style>
        :root {
            --bg-1: #0A0310;
            --accent-1: #49007E;
            --accent-2: #FF005B;
            --accent-3: #FF7D10;
            --accent-4: #FFB238;
            --radius-xl: 18px;
        }

        body {
            margin: 0;
            font-family: "Inter", sans-serif;
            background: radial-gradient(1200px 600px at 10% 10%, rgba(73,0,126,0.14), transparent),
            radial-gradient(900px 500px at 90% 90%, rgba(255,125,16,0.07), transparent),
            var(--bg-1);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .card {
            width: 95%;
            max-width: 420px;
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: var(--radius-xl);
            padding: 28px;
            box-shadow: 0 0 40px rgba(0,0,0,0.45);
        }

        h2 {
            margin: 0 0 6px 0;
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--accent-4), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.small {
            opacity: 0.7;
            font-size: 0.9rem;
            margin-top: 0;
        }

        label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        input {
            width: 100%;
            margin-top: 4px;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.22);
            background: rgba(255,255,255,0.06);
            color: #fff;
            font-size: 1rem;
        }

        input:focus {
            outline: none;
            border-color: var(--accent-2);
        }

        .btn {
            background: linear-gradient(90deg, var(--accent-1), var(--accent-2));
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 12px;
            border-radius: 14px;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s ease;
            margin-top: 4px;
        }

        .btn:hover {
            transform: scale(1.03);
        }

        .link {
            color: var(--accent-4);
            text-decoration: none;
            font-weight: 600;
        }

        .link:hover {
            text-decoration: underline;
        }

        .error {
            color: #FF4D4D;
            font-size: 0.85rem;
            margin-top: 4px;
        }
    </style>
</head>
<body>
<section class="card">
    <h2>Bienvenido a Melodic</h2>
    <p class="small">Ingresa para continuar tu experiencia musical.</p>

    <form method="POST" action="{{ route('login') }}" style="display:flex;flex-direction:column;gap:16px;">
        @csrf

        <div>
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}" />
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" />
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>

        <button class="btn" type="submit">Ingresar</button>
    </form>

    <p style="text-align:center;margin-top:18px;font-size:0.9rem;opacity:0.8;">
        ¿No tienes cuenta? <a href="{{ route('register') }}" class="link">Regístrate aquí</a>
    </p>
</section>
</body>
</html>
