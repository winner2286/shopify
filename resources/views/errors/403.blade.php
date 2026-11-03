<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès Non Autorisé (403) • Zanika</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <style>
        :root {
            --bg-primary: #0a0e1a;
            --bg-card: rgba(18, 24, 38, 0.85);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image: radial-gradient(circle at 50% 20%, rgba(245, 158, 11, 0.15), transparent 45%);
        }
        .container {
            max-width: 580px;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        .code-badge {
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #fbbf24;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 0.35rem 0.9rem;
            border-radius: 9999px;
            margin-bottom: 1.25rem;
            letter-spacing: 0.5px;
        }
        h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.45);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <span class="code-badge">ACCÈS RESTREINT (403)</span>
        <h1>Accès réservé</h1>
        <p>{{ $exception->getMessage() ?: "Vous n'avez pas l'autorisation d'accéder à cette ressource. Veuillez configurer votre boutique ou vous connecter avec le compte approprié." }}</p>
        
        <div class="actions">
            <a href="{{ url('/tableau-de-bord') }}" class="btn btn-primary">
                ← Tableau de bord
            </a>
            <a href="{{ url('/') }}" class="btn btn-secondary">
                Accueil Zanika
            </a>
        </div>
    </div>
</body>
</html>
