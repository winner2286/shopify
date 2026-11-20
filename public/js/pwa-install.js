(() => {
    const userAgent = navigator.userAgent;
    const isIOS = /iPhone|iPad|iPod/.test(userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isMobile = isIOS || /Android/.test(userAgent);
    const showMobileBanner = document.currentScript.hasAttribute('data-mobile-install');
    const hasDesktopGuide = !isMobile && /Chrome\/|Edg\//.test(userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)');

    let installPrompt;
    let supportsWebInstall = typeof navigator.install === 'function';
    let installed = standalone.matches || navigator.standalone === true;
    let installPending = false;
    let updateControls = () => {};

    window.addEventListener('beforeinstallprompt', (event) => {
        if (installed || (isMobile && !showMobileBanner)) return;

        event.preventDefault();
        installPrompt = event;
        updateControls();
    });

    window.addEventListener('appinstalled', () => {
        installPrompt = undefined;
        installed = true;
        updateControls();
    });

    document.addEventListener('DOMContentLoaded', () => {
        const banner = document.getElementById('pwa-floating-banner');
        const bannerButton = document.getElementById('pwa-banner-install');
        const modal = document.getElementById('install-guide-modal');
        const nativeInstall = document.getElementById('pwa-native-install-wrap');
        const tabs = modal.querySelector('.install-tabs-nav');
        const closeButton = modal.querySelector('.install-modal-close');
        const guideButtons = document.querySelectorAll('[data-pwa-guide]');
        const installButtons = document.querySelectorAll('[data-pwa-install]');
        let overflowBeforeGuide;
        let guideTrigger;
        let bannerDismissed = false;
        let mobileDelayElapsed = false;

        try {
            bannerDismissed = sessionStorage.getItem('zanika_pwa_dismissed') === '1';
        } catch {}

        if (!isMobile) {
            document.getElementById('pwa-banner-description').textContent =
                'Accédez à Zanika depuis votre bureau';
        }

        updateControls = () => {
            const canInstall = !installed && (supportsWebInstall || Boolean(installPrompt));
            const showBanner = isMobile
                ? showMobileBanner && (canInstall || mobileDelayElapsed)
                : canInstall || hasDesktopGuide;

            banner.style.display = !installed && !bannerDismissed && showBanner ? 'flex' : 'none';
            bannerButton.textContent = canInstall || isMobile ? 'Installer' : 'Voir comment';
            nativeInstall.style.display = canInstall ? 'block' : 'none';
            installButtons.forEach((button) => {
                button.disabled = installPending;
            });

            if (installed) {
                guideButtons.forEach((button) => {
                    button.style.display = 'none';
                });
                closeGuide();
            }
        };

        function selectPlatform(platform) {
            tabs.querySelectorAll('[data-pwa-platform]').forEach((tab) => {
                const isSelected = tab.dataset.pwaPlatform === platform;
                tab.classList.toggle('active', isSelected);
                tab.setAttribute('aria-selected', String(isSelected));
            });
            modal.querySelectorAll('.install-tab-content').forEach((panel) => {
                panel.hidden = panel.id !== `install-content-${platform}`;
            });
        }

        function openGuide(trigger) {
            if (modal.style.display === 'flex') return;

            if (document.getElementById('mobile-plus-sheet')?.classList.contains('open')) {
                closeMobilePlusMenu();
            }
            guideTrigger = trigger;
            tabs.style.display = isMobile ? '' : 'none';
            selectPlatform(isMobile ? (isIOS ? 'ios' : 'android') : 'desktop');
            overflowBeforeGuide = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            modal.style.display = 'flex';
            closeButton.focus();
        }

        function closeGuide() {
            if (modal.style.display !== 'flex') return;

            modal.style.display = 'none';
            document.body.style.overflow = overflowBeforeGuide;
            guideTrigger?.focus();
        }

        async function install(event) {
            if (installed || installPending) return;

            const trigger = event.currentTarget;
            if (!supportsWebInstall && !installPrompt) {
                openGuide(trigger);
                return;
            }

            installPending = true;
            updateControls();
            try {
                if (supportsWebInstall) {
                    await navigator.install();
                    installed = true;
                } else {
                    const prompt = installPrompt;
                    installPrompt = undefined;
                    await prompt.prompt();
                    const choice = await prompt.userChoice;
                    installed = installed || choice.outcome === 'accepted';
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    supportsWebInstall = false;
                    openGuide(trigger);
                }
            } finally {
                installPending = false;
                updateControls();
            }
        }

        installButtons.forEach((button) => button.addEventListener('click', install));
        guideButtons.forEach((button) => {
            button.addEventListener('click', () => openGuide(button));
        });
        modal.querySelectorAll('[data-pwa-close]').forEach((button) => {
            button.addEventListener('click', closeGuide);
        });
        tabs.querySelectorAll('[data-pwa-platform]').forEach((tab) => {
            tab.addEventListener('click', () => selectPlatform(tab.dataset.pwaPlatform));
        });
        banner.querySelector('#pwa-banner-dismiss').addEventListener('click', () => {
            bannerDismissed = true;
            try {
                sessionStorage.setItem('zanika_pwa_dismissed', '1');
            } catch {}
            updateControls();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeGuide();
        });

        if (isMobile && showMobileBanner && !installed) {
            setTimeout(() => {
                mobileDelayElapsed = true;
                updateControls();
            }, 2500);
        }
        updateControls();
    });
})();
