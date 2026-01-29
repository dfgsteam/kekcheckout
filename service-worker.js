const CACHE_NAME = "kekcounter-v6";
const PRECACHE_URLS = [
  "./",
  "index.php",
  "admin.php",
  "analysis.php",
  "assets/styles.css",
  "assets/app.js",
  "assets/admin.js",
  "assets/analysis.js",
  "assets/i18n.js",
  "assets/settings.js",
  "assets/theme.js",
  "assets/logo.png",
  "manifest.webmanifest",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_NAME)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener("message", (event) => {
  if (event?.data?.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

/** Fetch from network first, fall back to cache. */
function networkFirst(request) {
  return fetch(request)
    .then((response) => {
      const responseClone = response.clone();
      caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
      return response;
    })
    .catch(() => caches.match(request));
}

/** Fetch from cache first, update cache in the background. */
function cacheFirst(request) {
  return caches.match(request).then((cached) => {
    const fetchPromise = fetch(request)
      .then((response) => {
        const responseClone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
        return response;
      })
      .catch(() => cached);
    return cached || fetchPromise;
  });
}

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") {
    return;
  }

  const url = new URL(request.url);
  if (url.pathname.startsWith("/private/") || url.pathname.startsWith("/archives/")) {
    return;
  }
  if (url.searchParams.has("action")) {
    event.respondWith(fetch(request));
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(networkFirst(request));
    return;
  }

  if (url.origin === self.location.origin) {
    const destination = request.destination;
    if (["style", "script", "image", "font"].includes(destination)) {
      event.respondWith(cacheFirst(request));
      return;
    }
    event.respondWith(networkFirst(request));
  }
});
