<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Connexion | {{ config('app.name', 'Hanout Manager') }}</title>
        <style>
            :root {
                color-scheme: light;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: radial-gradient(circle at top right, #d3efe9, transparent 42%), #eef5f8;
                color: #1d2938;
                display: grid;
                place-items: center;
                padding: 18px;
            }

            .login-card {
                width: min(430px, 100%);
                background: #ffffff;
                border: 1px solid #d6e2ec;
                border-radius: 16px;
                box-shadow: 0 18px 40px rgba(20, 56, 84, 0.16);
                padding: 22px;
            }

            h1 {
                margin: 0;
                font-size: 1.4rem;
            }

            p {
                margin: 8px 0 0;
                color: #5f6f85;
                font-size: 0.95rem;
            }

            form {
                margin-top: 18px;
                display: grid;
                gap: 12px;
            }

            label {
                display: grid;
                gap: 6px;
                font-size: 0.92rem;
                font-weight: 600;
            }

            input {
                width: 100%;
                min-height: 48px;
                border: 1px solid #c6d5e3;
                border-radius: 10px;
                padding: 10px 12px;
                font-size: 0.95rem;
            }

            input:focus {
                outline: none;
                border-color: #2e7db3;
                box-shadow: 0 0 0 3px rgba(46, 125, 179, 0.2);
            }

            button {
                border: 0;
                min-height: 48px;
                border-radius: 10px;
                background: linear-gradient(140deg, #2e7db3, #1c5d88);
                color: #ffffff;
                font-size: 0.96rem;
                font-weight: 700;
                cursor: pointer;
            }

            .login-error {
                margin-top: 14px;
                border-radius: 10px;
                border: 1px solid rgba(192, 57, 43, 0.35);
                background: #fdecea;
                color: #8f2d24;
                padding: 10px 12px;
                font-size: 0.88rem;
            }

            .login-hint {
                margin-top: 14px;
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px dashed #c6d5e3;
                background: #f8fbff;
                color: #4a6078;
                font-size: 0.84rem;
            }
        </style>
    </head>
    <body>
        <section class="login-card" aria-label="Page de connexion">
            <h1>Connexion administrateur</h1>
            <p>Entrez vos identifiants pour acceder au dashboard.</p>

            @if ($errors->any())
                <div class="login-error" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.attempt') }}" method="POST">
                @csrf

                <label>
                    Username
                    <input
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        autocomplete="username"
                        required
                    >
                </label>

                <label>
                    Mot de passe
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <button type="submit">Se connecter</button>
            </form>

            <div class="login-hint">
                Acces reserve aux utilisateurs autorises.
            </div>
        </section>
    </body>
</html>
