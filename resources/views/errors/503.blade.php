<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance en cours • Zanika</title>
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
            --accent: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.25);
            --warning: #f59e0b;
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
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(99, 102, 241, 0.15), transparent 45%),
                radial-gradient(circle at 80% 80%, rgba(245, 158, 11, 0.08), transparent 35%);
        }
        .container {
            max-width: 620px;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, #6366f1, #f59e0b, #6366f1);
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(245, 158, 11, 0.12);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            background: #f59e0b;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        .icon-wrapper {
            width: 72px;
            height: 72px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: #818cf8;
        }
        h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
            color: #ffffff;
        }
        p.lead {
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--text-secondary);
            margin-bottom: 1.75rem;
        }
        .custom-message-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            color: #e2e8f0;
            line-height: 1.6;
            text-align: left;
        }
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .footer-note {
            margin-top: 2.5rem;
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $infoFile = storage_path('framework/maintenance_info.json');
        $info = file_exists($infoFile) ? json_decode(file_get_contents($infoFile), true) : [];
        $customMessage = $info['message'] ?? 'Nous effectuons actuellement une amélioration technique planifiée de la plateforme pour optimiser la rapidité et la fiabilité de vos boutiques.';
        $estimatedTime = $info['estimated_time'] ?? null;
    @endphp

    <div class="container">
        <div class="badge-status">
            <span class="pulse-dot"></span>
            <span>Maintenance Technique en Cours</span>
        </div>

        <div class="icon-wrapper">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
        </div>

        <h1>Zanika revient très vite</h1>

        <p class="lead">
            Notre équipe technique effectue des mises à niveau pour vous garantir une expérience optimale.
        </p>

        @if(!empty($customMessage))
            <div class="custom-message-box">
                <div style="font-weight: 600; margin-bottom: 0.35rem; color: #cbd5e1;">Message de l'administrateur :</div>
                <div>{{ $customMessage }}</div>
                @if(!empty($estimatedTime))
                    <div style="margin-top: 0.65rem; color: #fbbf24; font-weight: 600; display:flex; align-items:center; gap:0.4rem;">
                        <span>⏳ Durée estimée :</span>
                        <span>{{ $estimatedTime }}</span>
                    </div>
                @endif
            </div>
        @endif

        <div class="actions">
            <button onclick="window.location.reload()" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                </svg>
                Actualiser la page
            </button>
            <a href="https://wa.me/22890000000" target="_blank" rel="noopener" class="btn btn-secondary">
                Assistance WhatsApp
            </a>
        </div>

        <div class="footer-note">
            Zanika • Plateforme e-commerce propulsée par l'IA
        </div>
    </div>
</body>
</html>
