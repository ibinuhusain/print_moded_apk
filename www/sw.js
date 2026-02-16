// Apparels Collection Service Worker
const CACHE_NAME = 'apparels-v3';
const urlsToCache = [
    '/',
    '/login.php',
    '/index.php',
    '/js/app.js',
    '/css/material-style.css',
    '/manifest.json',
    '/images/icon-192x192.png'
];

// INSTALL - Cache resources
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
            .then(() => {
                return self.skipWaiting();
            })
    );
});

// ACTIVATE - Clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// FETCH - Serve cached resources
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                
                const fetchRequest = event.request.clone();
                
                return fetch(fetchRequest)
                    .then(response => {
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        
                        const responseToCache = response.clone();
                        
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                        
                        return response;
                    })
                    .catch(() => {
                        if (event.request.headers.get('accept').includes('text/html')) {
                            return caches.match('/');
                        }
                        
                        return new Response('Offline - No network connection', {
                            status: 503,
                            headers: new Headers({
                                'Content-Type': 'text/plain'
                            })
                        });
                    });
            })
    );
});

// MESSAGE - Handle messages from clients
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// SYNC - Handle background sync
self.addEventListener('sync', event => {
    if (event.tag === 'sync-collections') {
        event.waitUntil(syncPendingData());
    }
});

// Sync pending data
async function syncPendingData() {
    const clients = await self.clients.matchAll();
    
    if (clients.length > 0) {
        clients[0].postMessage({
            type: 'TRIGGER_SYNC',
            timestamp: new Date().toISOString()
        });
    }
    
    return Promise.resolve();
}