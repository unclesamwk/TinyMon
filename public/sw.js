var SW_VERSION = new URL(self.location).searchParams.get("v") || "dev";
var CACHE_NAME = "tinymon-" + SW_VERSION;
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
self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
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

  // External URLs (CDN, fonts): network only, don't intercept
  if (url.origin !== self.location.origin) {
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
  var data = { title: "TinyMon", body: "Status-Aenderung" };
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
    tag: data.tag || "tinymon-alert",
    data: { url: data.url || "/backend" },
    vibrate: [100, 50, 100],
    requireInteraction: true,
  };

  event.waitUntil(
    self.registration.showNotification(data.title || "TinyMon", options),
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
