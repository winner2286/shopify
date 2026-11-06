// Service Worker Officiel Zanika PWA
// Cache Versioning
const CACHE_NAME = 'zanika-pwa-v1.0';

// Ressources statiques essentielles mises en cache
const PRECACHE_ASSETS = [
    '/',
    '/manifest.json',
    '/site.webmanifest',
    '/favicon.ico',
    '/favicon-32x32.png',
    '/apple-touch-icon.png',
    '/images/brand/icon-192.png',
    '/images/brand/icon-512.png',
    '/images/brand/splash_icon.webp',
    '/images/brand/splash_wordmark.webp'
];

// Installation : Mise en cache des ressources critiques
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[Zanika SW] Pre-caching partial warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activation : Nettoyage des anciens caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Stratégie Fetch : Network-first pour les pages dynamiques (commandes, stock) avec fallback cache
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Ne pas intercepter les requêtes non-GET ou externes (APIs tierces, etc.)
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Ignorer les requêtes spécifiques (téléchargements, auth tokens, webhooks, etc.)
    if (url.origin !== location.origin) {
        return;
    }

    // Pour les assets statiques (images, css, js, fonts) : Cache-first avec mise à jour en arrière-plan
    if (
        request.destination === 'image' ||
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'font'
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    // Mettre à jour le cache en tâche de fond (stale-while-revalidate)
                    fetch(request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, networkResponse.clone());
                            });
                        }
                    }).catch(() => {});
                    return cachedResponse;
                }

                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseToCache);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Pour les pages HTML (navigation, dashboard) : Network-first pour afficher toujours les données fraîches
    event.respondWith(
        fetch(request)
            .then((networkResponse) => {
                // Si la page a été chargée avec succès, on la met en cache de secours
                if (networkResponse && networkResponse.status === 200) {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                // En cas de panne réseau complète (hors ligne), tenter de servir depuis le cache
                return caches.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Optionnel : page d'accueil en dernier recours
                    return caches.match('/');
                });
            })
    );
});
