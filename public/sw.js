var CACHE_NAME = "minimon-v1";
var SHELL_URLS = [
  "/backend",
  "/assets/js/backend-app.js",
  "/assets/js/pages/home.html",
  "/assets/js/pages/host-detail.html",
  "/assets/js/pages/host-edit.html",
  "/assets/js/pages/check-edit.html",
  "/assets/js/pages/settings.html",
  "/assets/images/logo.svg",
];
var CDN_URLS = [
  "https://cdn.jsdelivr.net/npm/framework7@9.0.2/framework7-bundle.min.css",
  "https://cdn.jsdelivr.net/npm/framework7@9.0.2/framework7-bundle.min.js",
  "https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js",
  "https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js",
  "https://fonts.googleapis.com/icon?family=Material+Icons",
];

self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      // Cache CDN assets (best effort)
      CDN_URLS.forEach(function (url) {
        cache.add(url).catch(function () {});
      });
      return cache.addAll(SHELL_URLS);
    }),
  );
  self.skipWaiting();
});

self.addEventListener("activate", function (event) {
  event.waitUntil(
    caches
      .keys()
      .then(function (names) {
        return Promise.all(
          names
            .filter(function (n) {
              return n !== CACHE_NAME;
            })
            .map(function (n) {
              return caches.delete(n);
            }),
        );
      })
      .then(function () {
        return self.clients.claim();
      }),
  );
});

self.addEventListener("fetch", function (event) {
  var url = new URL(event.request.url);

  // API calls: network only
  if (url.pathname.indexOf("/api/") !== -1) {
    return;
  }

  // App shell and assets: stale-while-revalidate
  event.respondWith(
    caches.match(event.request).then(function (cached) {
      var fetchPromise = fetch(event.request)
        .then(function (response) {
          if (response && response.status === 200) {
            var clone = response.clone();
            caches.open(CACHE_NAME).then(function (cache) {
              cache.put(event.request, clone);
            });
          }
          return response;
        })
        .catch(function () {
          return cached;
        });
      return cached || fetchPromise;
    }),
  );
});

self.addEventListener("push", function (event) {
  var data = { title: "MiniMon", body: "Status-Aenderung" };
  try {
    if (event.data) {
      data = event.data.json();
    }
  } catch (e) {
    console.error("[SW] Push data parse error:", e);
  }

  var options = {
    body: data.body || "",
    icon: data.icon || "/assets/images/logo.svg",
    badge: "/assets/images/logo.svg",
    tag: data.tag || "minimon-alert",
    data: { url: data.url || "/backend" },
    vibrate: [100, 50, 100],
    requireInteraction: true,
  };

  event.waitUntil(
    self.registration.showNotification(data.title || "MiniMon", options),
  );
});

self.addEventListener("notificationclick", function (event) {
  event.notification.close();

  var url = "/backend";
  if (event.notification.data && event.notification.data.url) {
    url = event.notification.data.url;
  }
  if (url.startsWith("/")) {
    url = self.location.origin + url;
  }

  event.waitUntil(
    self.clients
      .matchAll({ type: "window", includeUncontrolled: true })
      .then(function (clientList) {
        for (var i = 0; i < clientList.length; i++) {
          var client = clientList[i];
          if (client.url.includes("/backend") && "focus" in client) {
            return client.focus();
          }
        }
        if (self.clients.openWindow) {
          return self.clients.openWindow(url);
        }
      }),
  );
});
