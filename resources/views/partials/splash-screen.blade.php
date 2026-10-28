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
        -webkit-transform: translate3d(0, 0, 0);
        will-change: opacity, transform;
        transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        -webkit-transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), -webkit-transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        -webkit-tap-highlight-color: transparent;
    }

    #zanika-mobile-splash.splash-dismissed {
        opacity: 0 !important;
        transform: scale(1.06) !important;
        -webkit-transform: scale(1.06) !important;
        pointer-events: none !important;
    }

    /* Halo ambient pulsant derrière le grand Z */
    .zanika-splash-ambient-glow {
        position: absolute;
        top: 38%;
        left: 50%;
        width: 320px;
        height: 320px;
        transform: translate(-50%, -50%) scale(0.85);
        -webkit-transform: translate(-50%, -50%) scale(0.85);
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 45, 109, 0.32) 0%, rgba(255, 45, 109, 0.08) 45%, transparent 70%);
        filter: blur(28px);
        -webkit-filter: blur(28px);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.6s ease-out;
        -webkit-transition: opacity 0.6s ease-out;
        will-change: transform, opacity;
    }

    .zanika-splash-ambient-glow.is-active {
        opacity: 1;
        animation: zanikaGlowBreathe 3.2s ease-in-out infinite alternate;
        -webkit-animation: zanikaGlowBreathe 3.2s ease-in-out infinite alternate;
    }

    /* Conteneur global */
    .zanika-splash-center {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 0 16px;
        margin-top: -20px;
        width: 100%;
        max-width: 320px;
    }

    /* ========================================================
       1. LE GRAND 'Z' INITIAL (Effet d'ouverture signature Netflix)
       ======================================================== */
    .zanika-splash-icon-wrap {
        position: relative;
        width: 122px;
        height: 122px;
        margin-bottom: 22px;
        opacity: 0;
        transform: scale(0.55) translate3d(0, 24px, 0);
        -webkit-transform: scale(0.55) translate3d(0, 24px, 0);
        will-change: transform, opacity;
        filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.8)) drop-shadow(0 0 30px rgba(255, 45, 109, 0.35));
        -webkit-filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.8)) drop-shadow(0 0 30px rgba(255, 45, 109, 0.35));
    }

    .zanika-splash-icon-wrap.animate-icon {
        animation: zanikaBigZBloom 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards,
                   zanikaSubtleFloat 3.2s ease-in-out 0.85s infinite alternate;
        -webkit-animation: zanikaBigZBloom 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards,
                           zanikaSubtleFloat 3.2s ease-in-out 0.85s infinite alternate;
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

    /* ========================================================
       2. ÉCRITURE SÉQUENTIELLE LETTRE PAR LETTRE 'Z - A - N - I - K - A'
       ======================================================== */
    .zanika-letters-container {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        width: 100%;
        max-width: 224px;
        height: 38px;
        margin-bottom: 10px;
    }

    .zanika-letter-item {
        display: flex;
        align-items: flex-end;
        opacity: 0;
        transform: scale(0.65) translate3d(0, 14px, 0);
        -webkit-transform: scale(0.65) translate3d(0, 14px, 0);
        will-change: transform, opacity;
        filter: drop-shadow(0 3px 8px rgba(0, 0, 0, 0.6));
        -webkit-filter: drop-shadow(0 3px 8px rgba(0, 0, 0, 0.6));
    }

    /* Proportions exactes du logo de marque */
    .letter-0 { width: 16.5%; } /* Z */
    .letter-1 { width: 20.4%; } /* A */
    .letter-2 { width: 18.2%; } /* N */
    .letter-3 { width: 6.4%; }  /* I */
    .letter-4 { width: 16.7%; } /* K */
    .letter-5 { width: 18.9%; } /* A */

    .zanika-letter-item img {
        width: 100%;
        height: auto;
        display: block;
        user-select: none;
        -webkit-user-drag: none;
    }

    /* Déclenchement séquentiel : 1 lettre toutes les 140ms */
    .animate-letters .letter-0 {
        animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 0.52s forwards;
        -webkit-animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 0.52s forwards;
    }
    .animate-letters .letter-1 {
        animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 0.66s forwards;
        -webkit-animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 0.66s forwards;
    }
    .animate-letters .letter-2 {
        animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 0.80s forwards;
        -webkit-animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 0.80s forwards;
    }
    .animate-letters .letter-3 {
        /* Le 'i' avec son point rose néon éclatant */
        animation: zanikaLetterSnapGlow 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.35) 0.94s forwards;
        -webkit-animation: zanikaLetterSnapGlow 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.35) 0.94s forwards;
    }
    .animate-letters .letter-4 {
        animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 1.08s forwards;
        -webkit-animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 1.08s forwards;
    }
    .animate-letters .letter-5 {
        animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 1.22s forwards;
        -webkit-animation: zanikaLetterSnap 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25) 1.22s forwards;
    }

    /* ========================================================
       3. LE SLOGAN (Glissement subtil vers le haut)
       ======================================================== */
    .zanika-splash-tagline-wrap {
        width: 100%;
        max-width: 200px;
        opacity: 0;
        transform: translate3d(0, 10px, 0);
        -webkit-transform: translate3d(0, 10px, 0);
        will-change: transform, opacity;
    }

    .animate-letters .zanika-splash-tagline-wrap {
        animation: zanikaTaglineFade 0.65s cubic-bezier(0.16, 1, 0.3, 1) 1.42s forwards;
        -webkit-animation: zanikaTaglineFade 0.65s cubic-bezier(0.16, 1, 0.3, 1) 1.42s forwards;
    }

    .zanika-splash-tagline-img {
        width: 100%;
        height: auto;
        display: block;
        user-select: none;
        -webkit-user-drag: none;
    }

    /* Barre d'accueil iPhone */
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

    /* KEYFRAMES HAUTE PERFORMANCE GPU METAL / 120HZ */
    @keyframes zanikaBigZBloom {
        0% {
            opacity: 0;
            transform: scale(0.55) translate3d(0, 24px, 0);
            -webkit-transform: scale(0.55) translate3d(0, 24px, 0);
        }
        65% {
            opacity: 1;
            transform: scale(1.05) translate3d(0, -3px, 0);
            -webkit-transform: scale(1.05) translate3d(0, -3px, 0);
        }
        100% {
            opacity: 1;
            transform: scale(1) translate3d(0, 0, 0);
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
    }

    @-webkit-keyframes zanikaBigZBloom {
        0% {
            opacity: 0;
            -webkit-transform: scale(0.55) translate3d(0, 24px, 0);
        }
        65% {
            opacity: 1;
            -webkit-transform: scale(1.05) translate3d(0, -3px, 0);
        }
        100% {
            opacity: 1;
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
    }

    @keyframes zanikaLetterSnap {
        0% {
            opacity: 0;
            transform: scale(0.65) translate3d(0, 14px, 0);
            -webkit-transform: scale(0.65) translate3d(0, 14px, 0);
        }
        70% {
            opacity: 1;
            transform: scale(1.08) translate3d(0, -2px, 0);
            -webkit-transform: scale(1.08) translate3d(0, -2px, 0);
        }
        100% {
            opacity: 1;
            transform: scale(1) translate3d(0, 0, 0);
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
    }

    @-webkit-keyframes zanikaLetterSnap {
        0% {
            opacity: 0;
            -webkit-transform: scale(0.65) translate3d(0, 14px, 0);
        }
        70% {
            opacity: 1;
            -webkit-transform: scale(1.08) translate3d(0, -2px, 0);
        }
        100% {
            opacity: 1;
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
    }

    @keyframes zanikaLetterSnapGlow {
        0% {
            opacity: 0;
            transform: scale(0.5) translate3d(0, 16px, 0);
            -webkit-transform: scale(0.5) translate3d(0, 16px, 0);
            filter: drop-shadow(0 0 15px #FF2D6D);
        }
        70% {
            opacity: 1;
            transform: scale(1.18) translate3d(0, -3px, 0);
            -webkit-transform: scale(1.18) translate3d(0, -3px, 0);
            filter: drop-shadow(0 0 25px #FF2D6D);
        }
        100% {
            opacity: 1;
            transform: scale(1) translate3d(0, 0, 0);
            -webkit-transform: scale(1) translate3d(0, 0, 0);
            filter: drop-shadow(0 0 8px rgba(255, 45, 109, 0.4));
        }
    }

    @-webkit-keyframes zanikaLetterSnapGlow {
        0% {
            opacity: 0;
            -webkit-transform: scale(0.5) translate3d(0, 16px, 0);
        }
        70% {
            opacity: 1;
            -webkit-transform: scale(1.18) translate3d(0, -3px, 0);
        }
        100% {
            opacity: 1;
            -webkit-transform: scale(1) translate3d(0, 0, 0);
        }
    }

    @keyframes zanikaTaglineFade {
        0% {
            opacity: 0;
            transform: translate3d(0, 10px, 0);
            -webkit-transform: translate3d(0, 10px, 0);
        }
        100% {
            opacity: 1;
            transform: translate3d(0, 0, 0);
            -webkit-transform: translate3d(0, 0, 0);
        }
    }

    @-webkit-keyframes zanikaTaglineFade {
        0% {
            opacity: 0;
            -webkit-transform: translate3d(0, 10px, 0);
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

    @keyframes zanikaGlowBreathe {
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
    
    <div class="zanika-splash-center" id="splashCenter">
        <!-- 1. Le grand Z initial -->
        <div class="zanika-splash-icon-wrap" id="splashIconWrap">
            <img src="data:image/webp;base64,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" 
                 alt="Zanika Z" 
                 class="zanika-splash-icon-img"
                 width="240"
                 height="240">
        </div>

        <!-- 2. Les lettres écrites une par une : Z - A - N - I - K - A -->
        <div class="zanika-letters-container">
            <div class="zanika-letter-item letter-0"><img src="data:image/webp;base64,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" alt="Z"></div>
            <div class="zanika-letter-item letter-1"><img src="data:image/webp;base64,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" alt="A"></div>
            <div class="zanika-letter-item letter-2"><img src="data:image/webp;base64,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" alt="N"></div>
            <div class="zanika-letter-item letter-3"><img src="data:image/webp;base64,UklGRjYDAABXRUJQVlA4WAoAAAAQAAAAGQAAQAAAQUxQSIwAAAABcFpr25t8NNVV7yqKOgCHDTgoFB7VB2AIJsDj2Y5f8X55ThJ0REyAiffw+2POIDtCfG8gxJcHOsAGOAKnRBwcD2nnMPmumb6XzCu87L9+BnHrCPoaCNIKMKABTFImAlaJmCYiAiaJ6APNRDSAMlD6a8pAA2gC5UQ0gT4QAVNgBUyBpuMrmaOhlaSp4VZQOCCEAgAAcA4AnQEqGgBBAD4ZDIRBIQWbAQAEAGEoAuzM0RIkWyEnk/hn8kMAz/oG8gcAB+oHWk+Sg780sgVSL/Y+np/KfcB0oH+f4AD9Kwuz3T+WT0A1Jc8a4Yf7Vd5vpZC0/JklneV80WLX7IIVr/nJ+akjzSk1R+DnNch8m7cJcAD+/9H/nImjcfTEbsH0AFOgq3OoaWLwjFV4D/7L6cpB1zJrjG2O3KzLb7SDjTvH3N1+HbiZes61Kd077BELdlW6CE/2T/xpR74/+3dzye+X+0dj//rsip/hmUo5r0atB+/PcPJr/x0flD/2q9N2qvWrQ7FzX3p+qPvtif/v5+C4/HrkGbUOfeUL2NzIzjDVhMuaTl9PWRitbEWb8m/+3/vAtaY1LGo+S/85ggb/jpRLl/68YY11f64PBWAh3+hK//2PM4qc1/7jUHc/dN9vslJ5rat4jec/9qI9Z50z+b/7P4A92tKceXV1S8FL8tM9K5JACgJLzS/5CEB/M5+MDc2qKvmVUArQOPJBuwpoKm9PEn+le/lmouTkTk/uLVam8h9qcNzvqIedFsi9Ef37ZK6jrrok5lt+Z5q62pYrVNEcMisTFfvq7QjQTBU5NKAmSGotIuw+Je4u2jdlIsX5zLSKVtn05oaN+tMcB28pXMnHYnf79zVNf3WH+23+0I+XGGVsj98QlqLUf/7kp/ptWu1//df5Jrm9XCeP4pXnw636uSV1eTDrmNWMA4xW/vVot/WSN12tO++GvxHoT/Ju60dV/+Iau//U2f9bHcFdM+GcwPJYhOkqSWgArPxaDh4V//+yYFNZTq+If0vZ82EPaaTun59/jHwowGugX9sekZkfZxgkUcsAAAA=" alt="I"></div>
            <div class="zanika-letter-item letter-4"><img src="data:image/webp;base64,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" alt="K"></div>
            <div class="zanika-letter-item letter-5"><img src="data:image/webp;base64,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" alt="A"></div>
        </div>

        <!-- 3. Le slogan -->
        <div class="zanika-splash-tagline-wrap">
            <img src="data:image/webp;base64,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" 
                 alt="Le commerce à votre manière." 
                 class="zanika-splash-tagline-img"
                 width="406"
                 height="34">
        </div>
    </div>

    <div class="zanika-splash-home-bar"></div>
</div>

<script>
    (function () {
        var splash = document.getElementById('zanika-mobile-splash');
        var glow = document.getElementById('splashGlow');
        var iconWrap = document.getElementById('splashIconWrap');
        var center = document.getElementById('splashCenter');
        if (!splash || !iconWrap || !center) return;

        // Si désactivé (desktop ou déjà vu lors de la session)
        if (document.documentElement.classList.contains('no-splash')) {
            splash.style.display = 'none';
            return;
        }

        try {
            sessionStorage.setItem('zanika_mobile_splash_seen', 'true');
        } catch (e) {}

        // Déclencher la séquence Netflix (Grand Z -> lettres Z-A-N-I-K-A -> slogan)
        setTimeout(function() {
            if (glow) glow.classList.add('is-active');
            iconWrap.classList.add('animate-icon');
            center.classList.add('animate-letters');
        }, 60);

        // Transition de sortie fluide après frappe complète des lettres + temps d'admiration
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
            }, 650);
        }

        // Maintien jusqu'à 3,2 secondes (entrée complète des lettres + 1,7s d'affichage complet)
        var timer = setTimeout(dismissSplash, 3200);

        // Possibilité de toucher l'écran pour passer directement si pressé
        splash.addEventListener('click', function () {
            clearTimeout(timer);
            dismissSplash();
        }, { passive: true });
    })();
</script>
