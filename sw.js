const CACHE_NAME = 'peaks-cinema-v3';
const ASSETS = [
    '/PeaksCinema/movie.php',
    '/PeaksCinema/seat_selection.php',
    '/PeaksCinema/payment.php',
    '/PeaksCinema/profile_edit.php',
    '/PeaksCinema/personal_info_form.php',
    '/PeaksCinema/my_bookings.php',
    '/PeaksCinema/index.php'
];

// API endpoints to cache
const API_ENDPOINTS = [
    '/PeaksCinema/busyness_api.php',
    '/PeaksCinema/check_seat_availability.php',
    '/PeaksCinema/search_movies.php',
    '/PeaksCinema/notifications_api.php',
    '/PeaksCinema/queue_api.php'
];

// Pages that should ALWAYS fetch from network (never cache)
const NETWORK_ONLY = [
    '/PeaksCinema/home.php'
];

// Install event - cache static assets and core pages
self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)),
            caches.open(DYNAMIC_CACHE).then(cache => cache.addAll(CORE_PAGES))
        ]).then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(keys.map(key => {
                if (key !== STATIC_CACHE && key !== DYNAMIC_CACHE && key !== CACHE_NAME) {
                    console.log('Service Worker: Removing old cache', key);
                    return caches.delete(key);
                }
            }));
        }).then(() => self.clients.claim())
    );
});

// Network strategy for different types of requests
const getNetworkStrategy = (request) => {
    const url = new URL(request.url);
    
    // Network-only pages (never cache)
    if (NETWORK_ONLY.some(page => url.pathname.includes(page))) {
        return 'networkOnly';
    }
    
    // API endpoints - Network first, fallback to cache
    if (API_ENDPOINTS.some(endpoint => url.pathname.includes(endpoint))) {
        return 'networkFirst';
    }
    
    // Static assets - Cache first
    if (STATIC_ASSETS.some(asset => request.url.includes(asset)) || 
        request.url.includes('fonts.googleapis.com') || 
        request.url.includes('fonts.gstatic.com')) {
        return 'cacheFirst';
    }
    
    // Core pages - Cache first with network update
    if (CORE_PAGES.some(page => request.url.includes(page))) {
        return 'cacheFirstWithUpdate';
    }
    
    // Images - Cache first
    if (request.destination === 'image') {
        return 'cacheFirst';
    }
    
    // Default - Network first
    return 'networkFirst';
};

// Network Only strategy (never cache)
const networkOnly = (request) => {
    return fetch(request);
};

// Network First strategy
const networkFirst = (request) => {
    return fetch(request).then(response => {
        // Cache successful responses
        if (response.ok) {
            const responseClone = response.clone();
            caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, responseClone));
        }
        return response;
    }).catch(() => {
        // Fallback to cache
        return caches.match(request);
    });
};

// Cache First strategy
const cacheFirst = (request) => {
    return caches.match(request).then(cachedResponse => {
        if (cachedResponse) {
            return cachedResponse;
        }
        return fetch(request).then(response => {
            if (response.ok) {
                const responseClone = response.clone();
                caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, responseClone));
            }
            return response;
        });
    });
};

// Cache First with Network Update
const cacheFirstWithUpdate = (request) => {
    return caches.match(request).then(cachedResponse => {
        // Always fetch from network to update cache
        const fetchPromise = fetch(request).then(response => {
            if (response.ok) {
                const responseClone = response.clone();
                caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, responseClone));
            }
            return response;
        });
        
        // Return cached version immediately, or wait for network
        return cachedResponse || fetchPromise;
    });
};

// Fetch event - handle all requests
self.addEventListener('fetch', event => {
    const strategy = getNetworkStrategy(event.request);
    
    event.respondWith(
        (async () => {
            try {
                switch (strategy) {
                    case 'networkOnly':
                        return await networkOnly(event.request);
                    case 'networkFirst':
                        return await networkFirst(event.request);
                    case 'cacheFirst':
                        return await cacheFirst(event.request);
                    case 'cacheFirstWithUpdate':
                        return await cacheFirstWithUpdate(event.request);
                    default:
                        return await networkFirst(event.request);
                }
            } catch (error) {
                console.log('Service Worker: Request failed', error);
                
                // Offline fallback for navigation requests
                if (event.request.mode === 'navigate') {
                    return caches.match('/PeaksCinema/home.php');
                }
                
                // Return a basic offline response for other requests
                return new Response('Offline - Please check your connection', {
                    status: 503,
                    statusText: 'Service Unavailable'
                });
            }
        })()
    );
});

// Background sync for offline actions
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        event.waitUntil(
            // Handle any queued actions when back online
            console.log('Service Worker: Background sync triggered')
        );
    }
});

// Push notifications
self.addEventListener('push', event => {
    const options = {
        body: event.data ? event.data.text() : 'New notification from PeaksCinema',
        icon: '/PeaksCinema/assets/icons/icon-192x192.png',
        badge: '/PeaksCinema/assets/icons/icon-192x192.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        }
    };
    
    event.waitUntil(
        self.registration.showNotification('PeaksCinema', options)
    );
});
