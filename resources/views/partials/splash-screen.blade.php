<script>
    (function () {
        try {
            var isMobile = (window.innerWidth <= 768) || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            var alreadySeen = sessionStorage.getItem('zanika_mobile_splash_seen');
            if (!isMobile || alreadySeen) {
                document.documentElement.classList.add('no-splash');
            } else {
                document.documentElement.classList.add('has-splash');
            }
        } catch (e) {}
    })();
</script>

<style>
    /* Invalidation immédiate sur desktop ou visites ultérieures */
    .no-splash #zanika-mobile-splash {
        display: none !important;
    }

    @media (min-width: 769px) {
        #zanika-mobile-splash {
            display: none !important;
        }
    }

    html.has-splash,
    html.has-splash body {
        overflow: hidden !important;
        background-color: #07080C !important;
    }

    #zanika-mobile-splash {
        position: fixed;
        inset: 0;
        z-index: 99999999;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        background-color: #07080C;
        background-image: url('{{ asset("images/brand/splash_bg.svg") }}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin: 0;
        padding: 0;
        pointer-events: auto;
        opacity: 1;
        transform: translate3d(0, 0, 0);
        -webkit-transform: translate3d(0, 0, 0);
        will-change: opacity, transform;
        transition: opacity 0.55s cubic-bezier(0.4, 0, 0.2, 1), transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
        -webkit-transition: opacity 0.55s cubic-bezier(0.4, 0, 0.2, 1), -webkit-transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
        -webkit-tap-highlight-color: transparent;
    }

    #zanika-mobile-splash.splash-dismissed {
        opacity: 0 !important;
        transform: scale(1.05) !important;
        -webkit-transform: scale(1.05) !important;
        pointer-events: none !important;
    }

    /* Halo ambient pulsant subtil */
    .zanika-splash-ambient-glow {
        position: absolute;
        top: 40%;
        left: 50%;
        width: 290px;
        height: 290px;
        transform: translate(-50%, -50%) scale(0.85);
        -webkit-transform: translate(-50%, -50%) scale(0.85);
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 45, 109, 0.28) 0%, rgba(255, 45, 109, 0.08) 45%, transparent 70%);
        filter: blur(24px);
        -webkit-filter: blur(24px);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.6s ease-out;
        -webkit-transition: opacity 0.6s ease-out;
        will-change: transform, opacity;
    }

    .zanika-splash-ambient-glow.is-active {
        opacity: 1;
        animation: zanikaSplashPulse 2.6s ease-in-out infinite alternate;
        -webkit-animation: zanikaSplashPulse 2.6s ease-in-out infinite alternate;
    }

    /* Conteneur global du logo centré */
    .zanika-splash-center {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 0 16px;
        margin-top: -15px;
        width: 100%;
        max-width: 320px;
    }

    /* 1. L'Icône Squircle : entrée avec ressort physique tactile iOS */
    .zanika-splash-icon-wrap {
        position: relative;
        width: 120px;
        height: 120px;
        margin-bottom: 22px;
        opacity: 0;
        transform: scale(0.68) translate3d(0, 18px, 0);
        -webkit-transform: scale(0.68) translate3d(0, 18px, 0);
        will-change: transform, opacity;
        filter: drop-shadow(0 18px 36px rgba(0, 0, 0, 0.75)) drop-shadow(0 0 25px rgba(255, 45, 109, 0.3));
        -webkit-filter: drop-shadow(0 18px 36px rgba(0, 0, 0, 0.75)) drop-shadow(0 0 25px rgba(255, 45, 109, 0.3));
    }

    .zanika-splash-icon-wrap.animate-icon {
        animation: zanikaIconSpring 0.9s cubic-bezier(0.175, 0.885, 0.32, 1.15) forwards,
                   zanikaSubtleFloat 3.2s ease-in-out 0.9s infinite alternate;
        -webkit-animation: zanikaIconSpring 0.9s cubic-bezier(0.175, 0.885, 0.32, 1.15) forwards,
                           zanikaSubtleFloat 3.2s ease-in-out 0.9s infinite alternate;
    }

    .zanika-splash-icon-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        border-radius: 22%;
        user-select: none;
        -webkit-user-drag: none;
    }

    /* 2. Le texte ZANIKA + Slogan : apparition en cascade (staggered delay 220ms) */
    .zanika-splash-wordmark-wrap {
        position: relative;
        width: 100%;
        max-width: 220px;
        opacity: 0;
        transform: translate3d(0, 16px, 0);
        -webkit-transform: translate3d(0, 16px, 0);
        will-change: transform, opacity;
        filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.6));
        -webkit-filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.6));
    }

    .zanika-splash-wordmark-wrap.animate-wordmark {
        animation: zanikaWordmarkEntrance 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.22s forwards;
        -webkit-animation: zanikaWordmarkEntrance 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.22s forwards;
    }

    .zanika-splash-wordmark-img {
        width: 100%;
        height: auto;
        display: block;
        user-select: none;
        -webkit-user-drag: none;
    }

    /* Barre d'accueil iPhone native */
    .zanika-splash-home-bar {
        position: absolute;
        bottom: calc(env(safe-area-inset-bottom, 14px) + 6px);
        left: 50%;
        transform: translateX(-50%);
        -webkit-transform: translateX(-50%);
        width: 134px;
        height: 5px;
        background: rgba(255, 255, 255, 0.35);
        border-radius: 100px;
        pointer-events: none;
    }

    /* KEYFRAMES OPTIMISÉS 100% GPU METAL / PROMOTION 120HZ */
    @keyframes zanikaIconSpring {
        0% {
            opacity: 0;
            transform: scale(0.68) translate3d(0, 18px, 0);
            -webkit-transform: scale(0.68) translate3d(0, 18px, 0);
        }
        70% {
            opacity: 1;
            transform: scale(1.03) translate3d(0, -2px, 0);
            -webkit-transform: scale(1.03) translate3d(0, -2px, 0);
        }
        100% {
            opacity: 1;
            transform: scale(1) translate3d(0, 0, 0);
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
    }

    @-webkit-keyframes zanikaIconSpring {
        0% {
            opacity: 0;
            -webkit-transform: scale(0.68) translate3d(0, 18px, 0);
        }
        70% {
            opacity: 1;
            -webkit-transform: scale(1.03) translate3d(0, -2px, 0);
        }
        100% {
            opacity: 1;
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
    }

    @keyframes zanikaWordmarkEntrance {
        0% {
            opacity: 0;
            transform: translate3d(0, 16px, 0);
            -webkit-transform: translate3d(0, 16px, 0);
        }
        100% {
            opacity: 1;
            transform: translate3d(0, 0, 0);
            -webkit-transform: translate3d(0, 0, 0);
        }
    }

    @-webkit-keyframes zanikaWordmarkEntrance {
        0% {
            opacity: 0;
            -webkit-transform: translate3d(0, 16px, 0);
        }
        100% {
            opacity: 1;
            -webkit-transform: translate3d(0, 0, 0);
        }
    }

    @keyframes zanikaSubtleFloat {
        0% {
            transform: scale(1) translate3d(0, 0, 0);
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
        100% {
            transform: scale(1.025) translate3d(0, -3px, 0);
            -webkit-transform: scale(1.025) translate3d(0, -3px, 0);
        }
    }

    @keyframes zanikaSplashPulse {
        0% {
            transform: translate(-50%, -50%) scale(0.9);
            -webkit-transform: translate(-50%, -50%) scale(0.9);
            opacity: 0.75;
        }
        100% {
            transform: translate(-50%, -50%) scale(1.12);
            -webkit-transform: translate(-50%, -50%) scale(1.12);
            opacity: 1;
        }
    }
</style>

<div id="zanika-mobile-splash" aria-hidden="true" role="presentation">
    <div class="zanika-splash-ambient-glow" id="splashGlow"></div>
    <div class="zanika-splash-center">
        <div class="zanika-splash-icon-wrap" id="splashIconWrap">
            <img src="{{ asset('images/brand/splash_icon.webp') }}" 
                 alt="Zanika Icon" 
                 class="zanika-splash-icon-img" 
                 id="splashIconImg" 
                 width="240" 
                 height="240" 
                 fetchpriority="high">
        </div>
        <div class="zanika-splash-wordmark-wrap" id="splashWordmarkWrap">
            <img src="{{ asset('images/brand/splash_wordmark.webp') }}" 
                 alt="Zanika" 
                 class="zanika-splash-wordmark-img" 
                 id="splashWordmarkImg" 
                 width="406" 
                 height="102" 
                 fetchpriority="high">
        </div>
    </div>
    <div class="zanika-splash-home-bar"></div>
</div>

<script>
    (function () {
        var splash = document.getElementById('zanika-mobile-splash');
        var glow = document.getElementById('splashGlow');
        var iconWrap = document.getElementById('splashIconWrap');
        var wordmarkWrap = document.getElementById('splashWordmarkWrap');
        var iconImg = document.getElementById('splashIconImg');
        var wordmarkImg = document.getElementById('splashWordmarkImg');
        if (!splash || !iconWrap || !wordmarkWrap || !iconImg) return;

        // Si désactivé (desktop ou déjà vu)
        if (document.documentElement.classList.contains('no-splash')) {
            splash.style.display = 'none';
            return;
        }

        // Mémoriser la session
        try {
            sessionStorage.setItem('zanika_mobile_splash_seen', 'true');
        } catch (e) {}

        var started = false;
        function startStaggeredAnimation() {
            if (started) return;
            started = true;

            // 1. Activer le halo lumineux
            if (glow) glow.classList.add('is-active');

            // 2. Déclencher l'entrée cinématographique en cascade
            iconWrap.classList.add('animate-icon');
            wordmarkWrap.classList.add('animate-wordmark');

            // 3. Maintien de 2.0 secondes complètes pour profiter du visuel
            setTimeout(dismissSplash, 2100);
        }

        var dismissed = false;
        function dismissSplash() {
            if (dismissed) return;
            dismissed = true;

            splash.classList.add('splash-dismissed');
            document.documentElement.classList.remove('has-splash');

            setTimeout(function () {
                if (splash && splash.parentNode) {
                    splash.parentNode.removeChild(splash);
                }
            }, 600);
        }

        // Décodage immédiat en mémoire GPU pour garantir un départ d'animation net et fluide
        var p1 = iconImg.decode ? iconImg.decode().catch(function(){}) : Promise.resolve();
        var p2 = (wordmarkImg && wordmarkImg.decode) ? wordmarkImg.decode().catch(function(){}) : Promise.resolve();

        Promise.all([p1, p2]).then(function() {
            // Pause de 50ms pour laisser le temps au moteur iOS de peindre le fond
            setTimeout(startStaggeredAnimation, 50);
        }).catch(startStaggeredAnimation);

        // Sécurité réseau
        setTimeout(startStaggeredAnimation, 250);

        // Clic tactile pour passer directement si pressé
        splash.addEventListener('click', dismissSplash, { passive: true });
    })();
</script>
