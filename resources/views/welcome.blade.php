<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Statsio') }} API</title>
        <meta name="description" content="L'API de Statsio n'est pas ouverte au public pour le moment.">
        <meta name="robots" content="noindex, nofollow">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

        <style>
            :root {
                --color-primary: #8b5cf6;
                --color-secondary: #dccaf8;
                --color-accent: #3b82f6;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                font-family: 'Manrope', system-ui, sans-serif;
                color: #0f172a;
                background: #ffffff;
            }

            .glow {
                position: fixed;
                top: -220px;
                right: -220px;
                width: 600px;
                height: 600px;
                border-radius: 9999px;
                background: color-mix(in srgb, var(--color-primary) 10%, transparent);
                filter: blur(80px);
                pointer-events: none;
            }

            .dots {
                position: fixed;
                inset: 0;
                background-image: radial-gradient(circle, rgba(139, 92, 246, 0.08) 1px, transparent 1px);
                background-size: 28px 28px;
                pointer-events: none;
            }

            header {
                position: relative;
                display: flex;
                align-items: center;
                gap: 0.625rem;
                padding: 1.75rem 2rem;
            }

            header .logo { width: 28px; height: 28px; }

            header .name {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.8125rem;
                font-weight: 500;
                letter-spacing: 0.02em;
                color: #475569;
            }

            main {
                position: relative;
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
                text-align: center;
            }

            .content { max-width: 34rem; }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.375rem 0.875rem;
                border-radius: 9999px;
                border: 1px solid color-mix(in srgb, var(--color-primary) 20%, transparent);
                background: color-mix(in srgb, var(--color-primary) 6%, transparent);
                color: var(--color-primary);
                font-size: 0.6875rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                margin-bottom: 1.75rem;
            }

            .badge .dot {
                width: 6px;
                height: 6px;
                border-radius: 9999px;
                background: var(--color-primary);
                animation: pulse 2s ease-in-out infinite;
            }

            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.4; }
            }

            h1 {
                margin: 0 0 1rem;
                font-size: 2.25rem;
                line-height: 1.2;
                font-weight: 700;
            }

            h1 .gradient {
                background: linear-gradient(120deg, var(--color-primary), var(--color-accent));
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
            }

            p.subtitle {
                margin: 0 auto 2.25rem;
                max-width: 38ch;
                font-size: 1rem;
                line-height: 1.6;
                color: #64748b;
            }

            .ctas {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                min-height: 2.75rem;
                padding: 0 1.5rem;
                border-radius: 9999px;
                font-size: 0.875rem;
                font-weight: 600;
                text-decoration: none;
                transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            }

            .btn:hover { transform: translateY(-1px); }

            .btn-primary {
                background: var(--color-primary);
                color: #ffffff;
                box-shadow: 0 12px 20px color-mix(in srgb, var(--color-primary) 24%, transparent);
            }

            .btn-primary:hover { opacity: 0.92; }

            .btn-secondary {
                background: #ffffff;
                color: #0f172a;
                border: 1px solid #e2e8f0;
            }

            .btn-secondary:hover { background: #f8fafc; }

            footer {
                position: relative;
                padding: 1.5rem;
                text-align: center;
                font-size: 0.75rem;
                color: #94a3b8;
            }

            footer a { color: inherit; }
        </style>
    </head>
    <body>
        <div class="glow" aria-hidden="true"></div>
        <div class="dots" aria-hidden="true"></div>

        <header>
            <svg class="logo" width="200" height="200" viewBox="13 13 94 94" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <style>
                        .blue-dark     { fill: #3B82F6; }
                        .purple-medium { fill: #8B5CF6; }
                        .purple-light  { fill: #dccaf8; }
                        .diag-line     { stroke: #955ae0; stroke-width: 2.8; stroke-linecap: round; }
                    </style>
                    <clipPath id="clip-right" clipPathUnits="userSpaceOnUse">
                        <path d="M104 14 L121 14 L121 121 L14 121 L14 106 Z"/>
                    </clipPath>
                    <clipPath id="clip-left" clipPathUnits="userSpaceOnUse">
                        <path d="M14 106 L14 -1 L121 -1 L106 14 Z"/>
                    </clipPath>
                </defs>
                <g clip-path="url(#clip-left)">
                    <circle class="purple-medium" cx="60" cy="60" r="40"/>
                    <path class="purple-medium" d="M60 60 L20 60 A40 40 0 0 1 60 20 Z"/>
                    <path class="purple-light" d="M60 60 L20 60 A40 40 0 0 0 60 100 Z"/>
                    <path class="blue-dark" d="M60 60 L60 20 A40 40 0 0 1 93.5 35.5 Z"/>
                </g>
                <g clip-path="url(#clip-right)">
                    <rect class="blue-dark"     x="30" y="69" width="12" height="24" transform="rotate(-45 38 81)"/>
                    <rect class="purple-medium" x="46" y="43" width="12" height="48" transform="rotate(-45 54 67)"/>
                    <rect class="blue-dark"     x="62" y="9"  width="12" height="88" transform="rotate(-45 70 53)"/>
                    <rect class="purple-medium" x="80" y="25" width="12" height="24" transform="rotate(-45 86 39)"/>
                </g>
                <line class="diag-line" x1="15" y1="105" x2="105" y2="15"/>
            </svg>
            <span class="name">STATSIO API</span>
        </header>

        <main>
            <div class="content">
                <span class="badge">
                    <span class="dot" aria-hidden="true"></span>
                    Accès restreint
                </span>

                <h1><span class="gradient">Cette API n'est pas encore ouverte au public</span></h1>

                <p class="subtitle">
                    Cette interface est réservée à l'usage interne de la plateforme Statsio.
                    Si vous cherchez le site, rendez-vous sur Statsio.fr pour découvrir nos analyses,
                    sondages et StatsData.
                </p>

                <div class="ctas">
                    <a class="btn btn-primary" href="https://statsio.fr">Aller sur Statsio.fr</a>
                    <a class="btn btn-secondary" href="https://statsio.fr/contact">Nous contacter</a>
                </div>
            </div>
        </main>

        <footer>
            &copy; {{ date('Y') }} Statsio — <a href="https://statsio.fr">statsio.fr</a>
        </footer>
    </body>
</html>
