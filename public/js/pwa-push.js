/**
 * Zanika PWA - Module Web Push Notifications
 * Supporte Chrome, Safari iOS 16.4+, Firefox, Edge & PWA Desktop/Mobile
 */
(function() {
    'use strict';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    const ZanikaPush = {
        isSupported: function() {
            return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
        },

        getPermission: function() {
            if (!this.isSupported()) return 'unsupported';
            return Notification.permission;
        },

        getVapidKey: async function() {
            const res = await fetch('/push/vapid-key', {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Impossible de récupérer la clé VAPID publique.');
            const data = await res.json();
            return data.publicKey;
        },

        getRegistration: async function() {
            if (!this.isSupported()) return null;
            return await navigator.serviceWorker.ready;
        },

        getSubscription: async function() {
            const reg = await this.getRegistration();
            if (!reg) return null;
            return await reg.pushManager.getSubscription();
        },

        isSubscribed: async function() {
            const sub = await this.getSubscription();
            return !!sub;
        },

        subscribe: async function() {
            if (!this.isSupported()) {
                alert('Les notifications push ne sont pas supportées par votre navigateur actuel.');
                return false;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.warn('[ZanikaPush] Permission refusée par l\'utilisateur.');
                this.updateUI();
                return false;
            }

            try {
                const reg = await this.getRegistration();
                if (!reg) throw new Error('Service Worker non disponible.');

                const publicKey = await this.getVapidKey();
                const applicationServerKey = urlBase64ToUint8Array(publicKey);

                let subscription = await reg.pushManager.getSubscription();
                if (!subscription) {
                    subscription = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: applicationServerKey
                    });
                }

                // Enregistrer sur le serveur Laravel
                const subJson = subscription.toJSON();
                const response = await fetch('/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        keys: subJson.keys,
                        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0]
                    })
                });

                if (!response.ok) {
                    throw new Error('Erreur serveur lors de l\'enregistrement de l\'abonnement push.');
                }

                const result = await response.json();
                console.info('[ZanikaPush] Abonné avec succès :', result);

                this.updateUI();
                window.dispatchEvent(new CustomEvent('zanika:push:subscribed', { detail: result }));
                return true;
            } catch (err) {
                console.error('[ZanikaPush] Erreur d\'activation :', err);
                alert('Erreur lors de l\'activation des notifications : ' + err.message);
                return false;
            }
        },

        unsubscribe: async function() {
            try {
                const subscription = await this.getSubscription();
                if (subscription) {
                    const endpoint = subscription.endpoint;
                    await subscription.unsubscribe();

                    await fetch('/push/unsubscribe', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        body: JSON.stringify({ endpoint: endpoint })
                    });
                }

                this.updateUI();
                window.dispatchEvent(new CustomEvent('zanika:push:unsubscribed'));
                return true;
            } catch (err) {
                console.error('[ZanikaPush] Erreur désabonnement :', err);
                return false;
            }
        },

        testNotification: async function() {
            try {
                const response = await fetch('/push/test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await response.json();
                if (!response.ok) {
                    alert(data.message || 'Erreur lors du test de notification.');
                    return false;
                }

                return data;
            } catch (err) {
                alert('Erreur réseau lors du test : ' + err.message);
                return false;
            }
        },

        updateUI: async function() {
            const hasSub = await this.isSubscribed();
            const perm = this.getPermission();

            document.querySelectorAll('[data-push-state]').forEach(el => {
                if (hasSub) {
                    el.textContent = 'Notifications actives';
                    el.classList.add('push-active');
                    el.classList.remove('push-inactive');
                } else {
                    el.textContent = perm === 'denied' ? 'Bloquées dans le navigateur' : 'Activer les alertes push';
                    el.classList.add('push-inactive');
                    el.classList.remove('push-active');
                }
            });

            document.querySelectorAll('[data-push-action="enable"]').forEach(btn => {
                btn.style.display = hasSub ? 'none' : 'inline-flex';
            });
            document.querySelectorAll('[data-push-action="disable"]').forEach(btn => {
                btn.style.display = hasSub ? 'inline-flex' : 'none';
            });
            document.querySelectorAll('[data-push-action="test"]').forEach(btn => {
                btn.style.display = hasSub ? 'inline-flex' : 'none';
            });
        },

        init: function() {
            if (!this.isSupported()) {
                console.log('[ZanikaPush] Push notifications non supportées sur ce terminal.');
                return;
            }

            // Si la permission est déjà accordée, assurer la synchronisation silencieuse
            if (Notification.permission === 'granted') {
                this.getRegistration().then(reg => {
                    if (reg) {
                        reg.pushManager.getSubscription().then(sub => {
                            if (sub) {
                                // Re-sync silencieuse sur le serveur
                                const subJson = sub.toJSON();
                                fetch('/push/subscribe', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': getCsrfToken()
                                    },
                                    body: JSON.stringify({
                                        endpoint: sub.endpoint,
                                        keys: subJson.keys,
                                        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0]
                                    })
                                }).catch(() => {});
                            }
                            this.updateUI();
                        });
                    }
                });
            } else {
                this.updateUI();
            }

            // Délégation des clics sur les boutons push
            document.addEventListener('click', (e) => {
                const target = e.target.closest('[data-push-action]');
                if (!target) return;

                const action = target.getAttribute('data-push-action');
                if (action === 'enable') {
                    e.preventDefault();
                    target.disabled = true;
                    this.subscribe().finally(() => {
                        target.disabled = false;
                    });
                } else if (action === 'disable') {
                    e.preventDefault();
                    target.disabled = true;
                    this.unsubscribe().finally(() => {
                        target.disabled = false;
                    });
                } else if (action === 'test') {
                    e.preventDefault();
                    target.disabled = true;
                    const originalText = target.innerHTML;
                    target.innerHTML = 'Envoi...';
                    this.testNotification().then(res => {
                        if (res && res.success) {
                            target.innerHTML = '✅ Envoyée !';
                            setTimeout(() => { target.innerHTML = originalText; target.disabled = false; }, 3000);
                        } else {
                            target.innerHTML = originalText;
                            target.disabled = false;
                        }
                    }).catch(() => {
                        target.innerHTML = originalText;
                        target.disabled = false;
                    });
                }
            });
        }
    };

    window.ZanikaPush = ZanikaPush;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ZanikaPush.init());
    } else {
        ZanikaPush.init();
    }
})();
