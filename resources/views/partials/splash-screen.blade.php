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
    /* Invalidation immédiate sur desktop ou visites suivantes */
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
        will-change: opacity, transform;
        transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1), transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        -webkit-tap-highlight-color: transparent;
    }

    #zanika-mobile-splash.splash-dismissed {
        opacity: 0 !important;
        transform: scale(1.04) !important;
        pointer-events: none !important;
    }

    /* Halo ambient pulsant subtil derrière l'emblème */
    .zanika-splash-ambient-glow {
        position: absolute;
        top: 40%;
        left: 50%;
        width: 280px;
        height: 280px;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 45, 109, 0.24) 0%, rgba(255, 45, 109, 0.06) 45%, transparent 70%);
        filter: blur(22px);
        pointer-events: none;
        animation: zanikaSplashPulse 2.4s ease-in-out infinite alternate;
    }

    /* Bloc logo centré */
    .zanika-splash-emblem-wrap {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 0 16px;
        margin-top: -15px;
        transform-origin: center center;
        animation: zanikaSplashEntrance 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .zanika-splash-emblem {
        width: 220px;
        max-width: 66vw;
        height: auto;
        display: block;
        user-select: none;
        -webkit-user-drag: none;
        filter: drop-shadow(0 18px 36px rgba(0, 0, 0, 0.75)) drop-shadow(0 0 25px rgba(255, 45, 109, 0.22));
    }

    /* Barre home indicator style iOS (rappel raffiné de 09_mockup_telephone.jpg) */
    .zanika-splash-home-bar {
        position: absolute;
        bottom: calc(env(safe-area-inset-bottom, 14px) + 6px);
        left: 50%;
        transform: translateX(-50%);
        width: 134px;
        height: 5px;
        background: rgba(255, 255, 255, 0.35);
        border-radius: 100px;
        pointer-events: none;
    }

    /* Animations fluides haute performance */
    @keyframes zanikaSplashEntrance {
        0% {
            opacity: 0;
            transform: scale(0.85);
            filter: blur(4px) drop-shadow(0 10px 20px rgba(0, 0, 0, 0.8));
        }
        60% {
            opacity: 1;
            filter: blur(0px) drop-shadow(0 18px 36px rgba(0, 0, 0, 0.75)) drop-shadow(0 0 35px rgba(255, 45, 109, 0.35));
        }
        100% {
            opacity: 1;
            transform: scale(1);
            filter: drop-shadow(0 18px 36px rgba(0, 0, 0, 0.75)) drop-shadow(0 0 25px rgba(255, 45, 109, 0.22));
        }
    }

    @keyframes zanikaSplashPulse {
        0% {
            transform: translate(-50%, -50%) scale(0.92);
            opacity: 0.75;
        }
        100% {
            transform: translate(-50%, -50%) scale(1.1);
            opacity: 1;
        }
    }
</style>

<div id="zanika-mobile-splash" aria-hidden="true" role="presentation">
    <div class="zanika-splash-ambient-glow"></div>
    <div class="zanika-splash-emblem-wrap">
        <img src="{{ asset('images/brand/splash_emblem.png') }}" alt="Zanika" class="zanika-splash-emblem" width="440" height="390" fetchpriority="high">
    </div>
    <div class="zanika-splash-home-bar"></div>
</div>

<script>
    (function () {
        var splash = document.getElementById('zanika-mobile-splash');
        if (!splash) return;

        // Si la classe no-splash est active, ne rien faire
        if (document.documentElement.classList.contains('no-splash')) {
            splash.style.display = 'none';
            return;
        }

        // Marquer comme vu pour toute la durée de la session
        try {
            sessionStorage.setItem('zanika_mobile_splash_seen', 'true');
        } catch (e) {}

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
            }, 550);
        }

        // Maintien d'environ 1,75 seconde puis transition fluide vers la page principale
        var timer = setTimeout(dismissSplash, 1750);

        // Possibilité de toucher pour passer immédiatement
        splash.addEventListener('click', function () {
            clearTimeout(timer);
            dismissSplash();
        }, { passive: true });
    })();
</script>
