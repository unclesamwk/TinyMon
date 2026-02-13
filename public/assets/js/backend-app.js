// 401 → redirect to login
(function () {
  var originalFetch = window.fetch;
  window.fetch = function (url, options) {
    return originalFetch.call(this, url, options).then(function (response) {
      if (
        response.status === 401 &&
        typeof url === "string" &&
        url.indexOf("/api/") !== -1
      ) {
        window.location.href = "/backend/login";
      }
      return response;
    });
  };
})();

// Dark mode
var storedDarkMode = localStorage.getItem("darkMode");
var initialDarkMode =
  storedDarkMode === null
    ? window.matchMedia("(prefers-color-scheme: dark)").matches
    : storedDarkMode === "true";

function isDarkActive() {
  return document.documentElement.classList.contains("dark");
}

function updateDarkModeIcon() {
  var icon = document.querySelector("#dark-mode-icon");
  if (icon) icon.textContent = isDarkActive() ? "light_mode" : "dark_mode";
}

function toggleDarkMode() {
  var newMode = !isDarkActive();
  app.setDarkMode(newMode);
  localStorage.setItem("darkMode", String(newMode));
  updateDarkModeIcon();
}

// Status helpers
var statusColors = {
  ok: "#4cd964",
  warning: "#ff9500",
  critical: "#ff3b30",
  unknown: "#8e8e93",
};
var statusIcons = {
  ok: "check_circle",
  warning: "warning",
  critical: "error",
  unknown: "help",
};

function statusBadge(status) {
  var color = statusColors[status] || statusColors.unknown;
  var icon = statusIcons[status] || statusIcons.unknown;
  return (
    '<i class="icon material-icons" style="color:' +
    color +
    '; font-size:20px;">' +
    icon +
    "</i>"
  );
}

function typeIcon(type) {
  var icons = {
    ping: "network_ping",
    http: "language",
    port: "power",
    certificate: "verified_user",
    disk: "storage",
    load: "speed",
    memory: "memory",
    content: "article",
    content_hash: "fingerprint",
  };
  return icons[type] || "monitor_heart";
}

function escHtml(str) {
  if (!str) return "";
  var div = document.createElement("div");
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

function timeAgo(dateStr) {
  if (!dateStr) return "never";
  var diff = (Date.now() - new Date(dateStr + "Z").getTime()) / 1000;
  if (diff < 60) return Math.floor(diff) + "s ago";
  if (diff < 3600) return Math.floor(diff / 60) + "m ago";
  if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
  return Math.floor(diff / 86400) + "d ago";
}

// Dashboard loader
function loadDashboard(page) {
  fetch("/api/dashboard")
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      var summary = data.summary || {};
      var hosts = data.hosts || [];

      var s =
        '<div style="display:flex; justify-content:space-around; text-align:center; margin:1rem 0; padding:0 1rem;">';
      s +=
        '<div><div style="font-size:2rem; font-weight:bold; color:#4cd964;">' +
        (summary.ok || 0) +
        '</div><div style="font-size:0.75rem; color:gray;">OK</div></div>';
      s +=
        '<div><div style="font-size:2rem; font-weight:bold; color:#ff9500;">' +
        (summary.warning || 0) +
        '</div><div style="font-size:0.75rem; color:gray;">Warning</div></div>';
      s +=
        '<div><div style="font-size:2rem; font-weight:bold; color:#ff3b30;">' +
        (summary.critical || 0) +
        '</div><div style="font-size:0.75rem; color:gray;">Critical</div></div>';
      s +=
        '<div><div style="font-size:2rem; font-weight:bold; color:#8e8e93;">' +
        (summary.unknown || 0) +
        '</div><div style="font-size:0.75rem; color:gray;">Unknown</div></div>';
      s += "</div>";
      page.$el.find("#dashboard-summary").html(s);

      var html = "";
      if (hosts.length === 0) {
        html =
          '<div class="block text-align-center" style="color:gray; padding:2rem;">Keine Hosts konfiguriert.<br>Tippe + um einen Host hinzuzufügen.</div>';
      } else {
        html = '<div class="list media-list"><ul>';
        hosts.forEach(function (h) {
          var cs =
            h.checks && h.checks.length > 0
              ? h.checks.length + " Check" + (h.checks.length > 1 ? "s" : "")
              : "Keine Checks";
          html +=
            '<li><a href="#" class="item-link item-content host-link" data-host-id="' +
            h.id +
            '">';
          html += '<div class="item-media">' + statusBadge(h.status) + "</div>";
          html +=
            '<div class="item-inner"><div class="item-title-row"><div class="item-title">' +
            escHtml(h.name) +
            "</div></div>";
          html +=
            '<div class="item-subtitle" style="color:gray;">' +
            escHtml(h.address) +
            "</div>";
          html += '<div class="item-text">' + cs + "</div>";
          html += "</div></a></li>";
        });
        html += "</ul></div>";
      }
      page.$el.find("#host-list").html(html);

      page.$el.find(".host-link").on("click", function (ev) {
        ev.preventDefault();
        app.views.main.router.navigate("/hosts/" + this.dataset.hostId + "/");
      });
    })
    .catch(function (err) {
      page.$el
        .find("#host-list")
        .html(
          '<div class="block text-align-center" style="color:red;">Fehler: ' +
            err.message +
            "</div>",
        );
    });
}

// Host detail loader
function loadHostDetail(page, hostId) {
  fetch("/api/hosts/" + hostId)
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      page.$el.find("#host-title").text(data.name);
      var info =
        '<p style="color:gray; margin:0;">' + escHtml(data.address) + "</p>";
      if (data.description)
        info +=
          '<p style="margin:0.25rem 0 0;">' +
          escHtml(data.description) +
          "</p>";
      page.$el.find("#host-info").html(info);

      var checks = data.checks || [];
      var html = "";
      if (checks.length === 0) {
        html =
          '<div class="block text-align-center" style="color:gray;">Keine Checks konfiguriert.</div>';
      } else {
        html = '<div class="list media-list"><ul>';
        checks.forEach(function (c) {
          var lr = c.last_result;
          var st = lr ? lr.status : "unknown";
          var color = statusColors[st] || statusColors.unknown;
          html += '<li><div class="item-content">';
          html +=
            '<div class="item-media"><i class="icon material-icons" style="color:' +
            color +
            ';">' +
            typeIcon(c.type) +
            "</i></div>";
          html +=
            '<div class="item-inner"><div class="item-title-row"><div class="item-title" style="text-transform:uppercase; font-size:0.85rem;">' +
            escHtml(c.type) +
            "</div>";
          html += '<div class="item-after">' + statusBadge(st) + "</div></div>";
          html += '<div class="item-subtitle" style="color:gray;">';
          html += lr
            ? escHtml(lr.message) + " &middot; " + timeAgo(lr.checked_at)
            : "Noch nicht geprüft";
          html += "</div>";
          html += '<div class="item-text">';
          html +=
            '<a href="#" class="run-check" data-check-id="' +
            c.id +
            '" style="color:#007aff;">Jetzt prüfen</a>';
          html +=
            ' &middot; <a href="#" class="edit-check" data-check-id="' +
            c.id +
            '" style="color:#007aff;">Bearbeiten</a>';
          html +=
            ' &middot; <a href="#" class="delete-check" data-check-id="' +
            c.id +
            '" style="color:#ff3b30;">Löschen</a>';
          html += "</div></div></div></li>";
        });
        html += "</ul></div>";
      }
      page.$el.find("#check-list").html(html);
    });
}

// Check config fields renderer
function renderConfigFields(page, type, cfg) {
  cfg = cfg || {};
  var fields = [];
  if (type === "ping") {
    fields = [
      { key: "warning_ms", label: "Warning (ms)", val: cfg.warning_ms || 100 },
      {
        key: "critical_ms",
        label: "Critical (ms)",
        val: cfg.critical_ms || 500,
      },
    ];
  } else if (type === "http") {
    fields = [
      { key: "url", label: "Pfad", val: cfg.url || "/", t: "text" },
      { key: "port", label: "Port", val: cfg.port || 443 },
      {
        key: "expected_status",
        label: "Erwarteter Status",
        val: cfg.expected_status || 200,
      },
      {
        key: "warning_ms",
        label: "Warning (ms)",
        val: cfg.warning_ms || 1000,
      },
      {
        key: "critical_ms",
        label: "Critical (ms)",
        val: cfg.critical_ms || 5000,
      },
    ];
  } else if (type === "port") {
    fields = [
      { key: "port", label: "Port", val: cfg.port || 22 },
      { key: "warning_ms", label: "Warning (ms)", val: cfg.warning_ms || 100 },
      {
        key: "critical_ms",
        label: "Critical (ms)",
        val: cfg.critical_ms || 500,
      },
    ];
  } else if (type === "certificate") {
    fields = [
      { key: "port", label: "Port", val: cfg.port || 443 },
      {
        key: "warning_days",
        label: "Warning (Tage)",
        val: cfg.warning_days || 30,
      },
      {
        key: "critical_days",
        label: "Critical (Tage)",
        val: cfg.critical_days || 7,
      },
    ];
  } else if (type === "disk") {
    fields = [
      { key: "path", label: "Pfad", val: cfg.path || "/", t: "text" },
      {
        key: "warning_pct",
        label: "Warning (%)",
        val: cfg.warning_pct || 80,
      },
      {
        key: "critical_pct",
        label: "Critical (%)",
        val: cfg.critical_pct || 95,
      },
    ];
  } else if (type === "load") {
    fields = [
      { key: "warning", label: "Warning (Load)", val: cfg.warning || 2.0 },
      { key: "critical", label: "Critical (Load)", val: cfg.critical || 5.0 },
    ];
  } else if (type === "memory") {
    fields = [
      {
        key: "warning_pct",
        label: "Warning (%)",
        val: cfg.warning_pct || 80,
      },
      {
        key: "critical_pct",
        label: "Critical (%)",
        val: cfg.critical_pct || 95,
      },
    ];
  } else if (type === "content") {
    fields = [
      { key: "url", label: "Pfad", val: cfg.url || "/", t: "text" },
      { key: "port", label: "Port", val: cfg.port || 443 },
      {
        key: "expected_status",
        label: "Erwarteter Status",
        val: cfg.expected_status || 200,
      },
      {
        key: "expected_content",
        label: "Erwarteter Inhalt",
        val: cfg.expected_content || "",
        t: "text",
      },
      {
        key: "unexpected_content",
        label: "Unerwarteter Inhalt",
        val: cfg.unexpected_content || "",
        t: "text",
      },
    ];
  } else if (type === "content_hash") {
    fields = [
      { key: "url", label: "Pfad", val: cfg.url || "/", t: "text" },
      { key: "port", label: "Port", val: cfg.port || 443 },
      {
        key: "expected_status",
        label: "Erwarteter Status",
        val: cfg.expected_status || 200,
      },
      {
        key: "selector",
        label: "Regex-Selektor (optional)",
        val: cfg.selector || "",
        t: "text",
      },
    ];
  }
  var html = "";
  fields.forEach(function (f) {
    var inputType = f.t || "number";
    html += '<li class="item-content item-input"><div class="item-inner">';
    html += '<div class="item-title item-label">' + f.label + "</div>";
    html +=
      '<div class="item-input-wrap"><input type="' +
      inputType +
      '" data-config-key="' +
      f.key +
      '" value="' +
      f.val +
      '" step="any"></div>';
    html += "</div></li>";
  });
  page.$el.find("#config-list").html(html);
}

// App
var app = new Framework7({
  el: "#app",
  name: "MiniMon",
  theme: "ios",
  darkMode: initialDarkMode,
  view: { iosSwipeBack: true },
  routes: [
    // Home / Dashboard
    {
      path: "/",
      url: "/assets/js/pages/home.html",
      on: {
        pageInit: function (e, page) {
          updateDarkModeIcon();
          loadDashboard(page);
          page.$el.find("#toggle-dark").on("click", function (ev) {
            ev.preventDefault();
            toggleDarkMode();
          });
          page.$el.find("#nav-settings").on("click", function (ev) {
            ev.preventDefault();
            app.views.main.router.navigate("/settings/");
          });
          page.$el.find("#fab-add-host").on("click", function (ev) {
            ev.preventDefault();
            app.views.main.router.navigate("/hosts/new/");
          });
          page.$el.find(".ptr-content").on("ptr:refresh", function () {
            loadDashboard(page);
            setTimeout(function () {
              app.ptr.done(page.$el.find(".ptr-content"));
            }, 300);
          });
        },
        pageBeforeIn: function (e, page) {
          updateDarkModeIcon();
          loadDashboard(page);
        },
      },
    },

    // Host new (must be before :id)
    {
      path: "/hosts/new/",
      url: "/assets/js/pages/host-edit.html",
      on: {
        pageInit: function (e, page) {
          page.$el.find("#page-title").text("Neuer Host");

          page.$el.find("#save-host").on("click", function () {
            var name = page.$el.find("#host-name").val().trim();
            var address = page.$el.find("#host-address").val().trim();
            var description = page.$el.find("#host-description").val().trim();
            var enabled = page.$el.find("#host-enabled")[0].checked ? 1 : 0;

            if (!name || !address) {
              app.dialog.alert("Name und Adresse sind Pflichtfelder.");
              return;
            }

            fetch("/api/hosts", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                name: name,
                address: address,
                description: description,
                enabled: enabled,
              }),
            })
              .then(function (r) {
                return r.json();
              })
              .then(function (data) {
                app.views.main.router.navigate("/hosts/" + data.id + "/");
              })
              .catch(function (err) {
                app.dialog.alert("Fehler: " + err.message);
              });
          });
        },
      },
    },

    // Host detail
    {
      path: "/hosts/:id/",
      url: "/assets/js/pages/host-detail.html",
      on: {
        pageInit: function (e, page) {
          var hostId = page.route.params.id;
          loadHostDetail(page, hostId);

          page.$el.find("#edit-host-btn").on("click", function (ev) {
            ev.preventDefault();
            app.views.main.router.navigate("/hosts/" + hostId + "/edit/");
          });
          page.$el.find("#add-check-btn").on("click", function (ev) {
            ev.preventDefault();
            app.views.main.router.navigate("/hosts/" + hostId + "/checks/new/");
          });
          page.$el.find("#delete-host-btn").on("click", function (ev) {
            ev.preventDefault();
            app.dialog.confirm(
              "Host und alle Checks wirklich löschen?",
              "Host löschen",
              function () {
                fetch("/api/hosts/" + hostId, { method: "DELETE" }).then(
                  function () {
                    app.views.main.router.back("/");
                  },
                );
              },
            );
          });

          page.$el.on("click", ".run-check", function (ev) {
            ev.preventDefault();
            var checkId = this.dataset.checkId;
            app.preloader.show();
            fetch("/api/checks/" + checkId + "/run", { method: "POST" })
              .then(function () {
                loadHostDetail(page, hostId);
                app.preloader.hide();
              })
              .catch(function () {
                app.preloader.hide();
              });
          });
          page.$el.on("click", ".edit-check", function (ev) {
            ev.preventDefault();
            app.views.main.router.navigate(
              "/checks/" + this.dataset.checkId + "/edit/",
            );
          });
          page.$el.on("click", ".delete-check", function (ev) {
            ev.preventDefault();
            var checkId = this.dataset.checkId;
            app.dialog.confirm(
              "Check wirklich löschen?",
              "Löschen",
              function () {
                fetch("/api/checks/" + checkId, { method: "DELETE" }).then(
                  function () {
                    loadHostDetail(page, hostId);
                  },
                );
              },
            );
          });
        },
        pageBeforeIn: function (e, page) {
          var hostId = page.route.params.id;
          loadHostDetail(page, hostId);
        },
      },
    },

    // Host edit
    {
      path: "/hosts/:id/edit/",
      url: "/assets/js/pages/host-edit.html",
      on: {
        pageInit: function (e, page) {
          var id = page.route.params.id;
          page.$el.find("#page-title").text("Host bearbeiten");

          fetch("/api/hosts/" + id)
            .then(function (r) {
              return r.json();
            })
            .then(function (data) {
              page.$el.find("#host-name").val(data.name);
              page.$el.find("#host-address").val(data.address);
              page.$el.find("#host-description").val(data.description || "");
              page.$el.find("#host-enabled")[0].checked = !!data.enabled;
            });

          page.$el.find("#save-host").on("click", function () {
            var name = page.$el.find("#host-name").val().trim();
            var address = page.$el.find("#host-address").val().trim();
            var description = page.$el.find("#host-description").val().trim();
            var enabled = page.$el.find("#host-enabled")[0].checked ? 1 : 0;

            if (!name || !address) {
              app.dialog.alert("Name und Adresse sind Pflichtfelder.");
              return;
            }

            fetch("/api/hosts/" + id, {
              method: "PUT",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                name: name,
                address: address,
                description: description,
                enabled: enabled,
              }),
            })
              .then(function (r) {
                return r.json();
              })
              .then(function () {
                app.views.main.router.back();
              })
              .catch(function (err) {
                app.dialog.alert("Fehler: " + err.message);
              });
          });
        },
      },
    },

    // Check new
    {
      path: "/hosts/:id/checks/new/",
      url: "/assets/js/pages/check-edit.html",
      on: {
        pageInit: function (e, page) {
          var hostId = page.route.params.id;
          page.$el.find("#page-title").text("Neuer Check");
          renderConfigFields(page, "ping", {});

          page.$el.find("#check-type").on("change", function () {
            renderConfigFields(page, this.value, {});
          });

          page.$el.find("#save-check").on("click", function () {
            var type = page.$el.find("#check-type").val();
            var interval =
              parseInt(page.$el.find("#check-interval").val()) || 300;
            var enabled = page.$el.find("#check-enabled")[0].checked ? 1 : 0;
            var config = {};
            page.$el.find("[data-config-key]").each(function () {
              var num = parseFloat(this.value);
              config[this.dataset.configKey] = isNaN(num) ? this.value : num;
            });

            fetch("/api/hosts/" + hostId + "/checks", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                type: type,
                interval_seconds: interval,
                enabled: enabled,
                config: config,
              }),
            }).then(function () {
              app.views.main.router.back();
            });
          });
        },
      },
    },

    // Check edit
    {
      path: "/checks/:id/edit/",
      url: "/assets/js/pages/check-edit.html",
      on: {
        pageInit: function (e, page) {
          var checkId = page.route.params.id;
          page.$el.find("#page-title").text("Check bearbeiten");

          // Find the check data
          fetch("/api/hosts")
            .then(function (r) {
              return r.json();
            })
            .then(function (hosts) {
              hosts.forEach(function (h) {
                fetch("/api/hosts/" + h.id + "/checks")
                  .then(function (r) {
                    return r.json();
                  })
                  .then(function (checks) {
                    checks.forEach(function (c) {
                      if (String(c.id) === String(checkId)) {
                        var cfg = {};
                        try {
                          cfg = JSON.parse(c.config || "{}");
                        } catch (e) {}
                        page.$el.find("#check-type").val(c.type);
                        page.$el
                          .find("#check-interval")
                          .val(c.interval_seconds);
                        page.$el.find("#check-enabled")[0].checked =
                          !!c.enabled;
                        renderConfigFields(page, c.type, cfg);
                      }
                    });
                  });
              });
            });

          page.$el.find("#check-type").on("change", function () {
            renderConfigFields(page, this.value, {});
          });

          page.$el.find("#save-check").on("click", function () {
            var type = page.$el.find("#check-type").val();
            var interval =
              parseInt(page.$el.find("#check-interval").val()) || 300;
            var enabled = page.$el.find("#check-enabled")[0].checked ? 1 : 0;
            var config = {};
            page.$el.find("[data-config-key]").each(function () {
              var num = parseFloat(this.value);
              config[this.dataset.configKey] = isNaN(num) ? this.value : num;
            });

            fetch("/api/checks/" + checkId, {
              method: "PUT",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                type: type,
                interval_seconds: interval,
                enabled: enabled,
                config: config,
              }),
            }).then(function () {
              app.views.main.router.back();
            });
          });
        },
      },
    },

    // Settings
    {
      path: "/settings/",
      url: "/assets/js/pages/settings.html",
      on: {
        pageInit: function (e, page) {
          var dm = page.$el.find("#settings-darkmode")[0];
          if (dm) dm.checked = isDarkActive();
          page.$el.find("#settings-darkmode").on("change", function () {
            toggleDarkMode();
          });

          page.$el.find("#settings-version").text(APP_VERSION || "dev");

          fetch("/api/dashboard")
            .then(function (r) {
              return r.json();
            })
            .then(function (data) {
              var hosts = data.hosts || [];
              var checkCount = 0;
              hosts.forEach(function (h) {
                checkCount += (h.checks || []).length;
              });
              page.$el.find("#settings-host-count").text(hosts.length);
              page.$el.find("#settings-check-count").text(checkCount);
            });

          page.$el.find("#logout-btn").on("click", function () {
            var form = document.createElement("form");
            form.method = "POST";
            form.action = "/backend/logout";
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "_csrf_token";
            input.value = CSRF_TOKEN;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
          });
        },
      },
    },
  ],
});
