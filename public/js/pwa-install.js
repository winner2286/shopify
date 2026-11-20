(() => {
    const userAgent = navigator.userAgent;
    const isIOS = /iPhone|iPad|iPod/.test(userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isMobile = isIOS || /Android/.test(userAgent);
    const showMobileBanner = document.currentScript.hasAttribute('data-mobile-install');
    const standalone = window.matchMedia('(display-mode: standalone)');
    const dismissalKey = isMobile ? 'zanika_pwa_dismissed' : 'zanika_pwa_desktop_dismissed';

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
        const bannerDescription = document.getElementById('pwa-banner-description');
        const bannerInstallButton = document.getElementById('pwa-banner-install');
        const modal = document.getElementById('install-guide-modal');
        const nativeInstall = document.getElementById('pwa-native-install-wrap');
        const tabs = modal.querySelector('.install-tabs-nav');
        const closeButton = modal.querySelector('.install-modal-close');
        const menuInstallButtons = document.querySelectorAll('[data-pwa-install-menu]');
        const installButtons = document.querySelectorAll('[data-pwa-install]');
        let overflowBeforeGuide;
        let guideTrigger;
        let bannerDismissed = false;
        let installedInBrowser = false;
        let mobileDelayElapsed = false;

        try {
            bannerDismissed = sessionStorage.getItem(dismissalKey) === '1';
        } catch {}

        if (!isMobile) {
            bannerDescription.textContent = 'Accédez à Zanika depuis votre bureau';
        }

        updateControls = () => {
            const canInstall = !installed && (supportsWebInstall || Boolean(installPrompt));
            const showBanner = !isMobile || (showMobileBanner && (canInstall || mobileDelayElapsed));
            const canOpen = installedInBrowser && supportsWebInstall;

            banner.style.display = !installed && !bannerDismissed && showBanner ? 'flex' : 'none';
            bannerInstallButton.style.display = installedInBrowser && !canOpen ? 'none' : '';
            const actionLabel = canOpen ? 'Ouvrir' : 'Installer';
            bannerInstallButton.textContent = installPending ? 'En cours…' : actionLabel;
            nativeInstall.style.display = canInstall ? 'block' : 'none';
            installButtons.forEach((button) => {
                button.disabled = installPending;
            });
            menuInstallButtons.forEach((button) => {
                button.style.display = installed || installedInBrowser ? 'none' : '';
            });

            if (installed) closeGuide();
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
            selectPlatform(isIOS ? 'ios' : 'android');
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

        function waitForInstallPrompt() {
            return new Promise((resolve) => {
                const finish = () => {
                    clearTimeout(timeout);
                    window.removeEventListener('beforeinstallprompt', finish);
                    resolve();
                };
                // Chromium conserve l’activation du clic pendant au plus cinq secondes.
                const timeout = setTimeout(finish, 4500);
                window.addEventListener('beforeinstallprompt', finish);
            });
        }

        function showInstallUnavailable(trigger) {
            if (isMobile) {
                openGuide(trigger);
                return;
            }

            const message = installedInBrowser
                ? 'Le navigateur n’a pas pu ouvrir Zanika.'
                : 'Le navigateur n’a pas pu lancer l’installation.';
            window.BoutiqToast?.(message, 'warning');
        }

        async function install(event) {
            if (installed || installPending || (installedInBrowser && !supportsWebInstall)) return;

            const trigger = event.currentTarget;
            installPending = true;
            updateControls();
            try {
                // Le premier clic peut déclencher beforeinstallprompt sur les versions Chromium antérieures.
                if (!isMobile && !supportsWebInstall && !installPrompt
                    && 'onbeforeinstallprompt' in window && navigator.userActivation?.isActive) {
                    await waitForInstallPrompt();
                }
                if (installed || (installedInBrowser && !supportsWebInstall)) return;

                if (supportsWebInstall) {
                    await navigator.install();
                    installed = true;
                } else if (installPrompt && navigator.userActivation?.isActive !== false) {
                    const prompt = installPrompt;
                    installPrompt = undefined;
                    await prompt.prompt();
                    const choice = await prompt.userChoice;
                    installed = installed || choice.outcome === 'accepted';
                } else {
                    showInstallUnavailable(trigger);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    supportsWebInstall = false;
                    showInstallUnavailable(trigger);
                } else if (!isMobile) {
                    const message = installedInBrowser
                        ? 'L’ouverture de Zanika n’a pas abouti.'
                        : 'L’installation n’a pas abouti.';
                    window.BoutiqToast?.(message, 'info');
                }
            } finally {
                installPending = false;
                updateControls();
            }
        }

        installButtons.forEach((button) => button.addEventListener('click', install));
        modal.querySelectorAll('[data-pwa-close]').forEach((button) => {
            button.addEventListener('click', closeGuide);
        });
        tabs.querySelectorAll('[data-pwa-platform]').forEach((tab) => {
            tab.addEventListener('click', () => selectPlatform(tab.dataset.pwaPlatform));
        });
        banner.querySelector('#pwa-banner-dismiss').addEventListener('click', () => {
            bannerDismissed = true;
            try {
                sessionStorage.setItem(dismissalKey, '1');
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

        if (!isMobile && !installed && typeof navigator.getInstalledRelatedApps === 'function') {
            const appId = new URL('/', window.location.href).href;
            navigator.getInstalledRelatedApps().then((apps) => {
                installedInBrowser = apps.some((app) => app.platform === 'webapp' && app.id === appId);
                if (installedInBrowser) {
                    bannerDescription.textContent = 'Zanika est déjà installée dans ce navigateur.';
                    updateControls();
                }
            }).catch(() => {});
        }

        updateControls();
    });
})();
