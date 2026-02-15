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

// Dark mode (auto / on / off)
var darkModePref = localStorage.getItem("darkModePreference") || "auto";

function resolveDarkMode(pref) {
  if (pref === "on") return true;
  if (pref === "off") return false;
  return window.matchMedia("(prefers-color-scheme: dark)").matches;
}

var initialDarkMode = resolveDarkMode(darkModePref);

function isDarkActive() {
  return document.documentElement.classList.contains("dark");
}

function updateDarkModeIcon() {
  var icon = document.querySelector("#dark-mode-icon");
  if (icon) icon.textContent = isDarkActive() ? "light_mode" : "dark_mode";
}

function setDarkModePreference(pref) {
  darkModePref = pref;
  localStorage.setItem("darkModePreference", pref);
  app.setDarkMode(resolveDarkMode(pref));
  updateDarkModeIcon();
  reloadVisibleCharts();
}

function toggleDarkMode() {
  var newPref = isDarkActive() ? "off" : "on";
  setDarkModePreference(newPref);
}

// Listen for system theme changes when in auto mode
window
  .matchMedia("(prefers-color-scheme: dark)")
  .addEventListener("change", function () {
    if (darkModePref === "auto") {
      app.setDarkMode(resolveDarkMode("auto"));
      updateDarkModeIcon();
    }
  });

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
    icecast_listeners: "radio",
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

function renderHostListItem(h) {
  var cs =
    h.checks && h.checks.length > 0
      ? h.checks.length + " Check" + (h.checks.length > 1 ? "s" : "")
      : "Keine Checks";
  var li =
    '<li><a href="#" class="item-link item-content host-link" data-host-id="' +
    h.id +
    '">';
  li += '<div class="item-media">' + statusBadge(h.status) + "</div>";
  li +=
    '<div class="item-inner"><div class="item-title-row"><div class="item-title">' +
    escHtml(h.name) +
    "</div></div>";
  li +=
    '<div class="item-subtitle" style="color:gray;">' +
    escHtml(h.address) +
    "</div>";
  li += '<div class="item-text">' + cs + "</div>";
  li += "</div></a></li>";
  return li;
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

      var runnerEl = page.$el.find("#runner-status");
      if (data.runner_last_run) {
        runnerEl.html("Runner: " + timeAgo(data.runner_last_run));
      } else {
        runnerEl.html("Runner: noch nicht gelaufen");
      }

      var html = "";
      if (hosts.length === 0) {
        html =
          '<div class="block text-align-center" style="color:gray; padding:2rem;">Keine Hosts konfiguriert.<br>Tippe + um einen Host hinzuzufügen.</div>';
      } else {
        // Group hosts by topic
        var groups = {};
        var ungrouped = [];
        hosts.forEach(function (h) {
          var t = (h.topic || "").trim();
          if (t === "") {
            ungrouped.push(h);
          } else {
            if (!groups[t]) groups[t] = [];
            groups[t].push(h);
          }
        });
        var topicNames = Object.keys(groups).sort();

        if (topicNames.length === 0) {
          // No topics at all – flat list like before
          html = '<div class="list media-list"><ul>';
          ungrouped.forEach(function (h) {
            html += renderHostListItem(h);
          });
          html += "</ul></div>";
        } else {
          html = '<div class="list accordion-list media-list">';

          topicNames.forEach(function (topic) {
            var groupHosts = groups[topic];
            var groupStatus = "unknown";
            groupHosts.forEach(function (h) {
              var s = h.status;
              if (
                s === "critical" ||
                (groupStatus !== "critical" && s === "warning") ||
                (groupStatus === "unknown" && s === "ok")
              ) {
                groupStatus = s;
              }
            });

            html += '<li class="accordion-item accordion-item-opened">';
            html += '<a class="item-link item-content" href="#">';
            html +=
              '<div class="item-media">' + statusBadge(groupStatus) + "</div>";
            html += '<div class="item-inner">';
            html +=
              '<div class="item-title" style="font-weight:600;">' +
              escHtml(topic) +
              "</div>";
            html += '<div class="item-after">' + groupHosts.length + "</div>";
            html += "</div></a>";
            html += '<div class="accordion-item-content">';
            html +=
              '<div class="list media-list" style="margin-left:1rem;"><ul>';
            groupHosts.forEach(function (h) {
              html += renderHostListItem(h);
            });
            html += "</ul></div></div></li>";
          });

          if (ungrouped.length > 0) {
            html += '<li class="accordion-item accordion-item-opened">';
            html += '<a class="item-link item-content" href="#">';
            html += '<div class="item-inner">';
            html +=
              '<div class="item-title" style="font-weight:600;">Allgemein</div>';
            html += '<div class="item-after">' + ungrouped.length + "</div>";
            html += "</div></a>";
            html += '<div class="accordion-item-content">';
            html +=
              '<div class="list media-list" style="margin-left:1rem;"><ul>';
            ungrouped.forEach(function (h) {
              html += renderHostListItem(h);
            });
            html += "</ul></div></div></li>";
          }

          html += "</div>";
        }
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
  // Remember which charts are currently open
  var openCharts = [];
  page.$el.find(".check-chart-container").each(function () {
    if (this.style.display !== "none") {
      var id = this.id.replace("chart-container-", "");
      if (id) openCharts.push(id);
    }
  });

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
          var cfgRaw =
            typeof c.config === "string"
              ? c.config
              : JSON.stringify(c.config || {});
          html += "<li>";
          html +=
            '<div class="item-content check-card" data-check-id="' +
            c.id +
            '" style="cursor:pointer;">';
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
          html += "</div></div></div>";
          html +=
            '<div class="check-chart-container" id="chart-container-' +
            c.id +
            '" data-check-type="' +
            escHtml(c.type) +
            '" data-check-config="' +
            escHtml(cfgRaw) +
            '" style="display:none; padding:0.5rem 1rem 1rem;"></div>';
          html += "</li>";
        });
        html += "</ul></div>";
      }
      page.$el.find("#check-list").html(html);

      // Restore previously open charts
      openCharts.forEach(function (checkId) {
        var container = document.getElementById("chart-container-" + checkId);
        if (container) {
          container.style.display = "block";
          loadChart(checkId, currentChartRange);
        }
      });
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
  } else if (type === "icecast_listeners") {
    fields = [
      { key: "port", label: "Port", val: cfg.port || 443 },
      {
        key: "warning_listeners",
        label: "Warning unter (Listeners)",
        val: cfg.warning_listeners || 0,
      },
      {
        key: "critical_listeners",
        label: "Critical unter (Listeners)",
        val: cfg.critical_listeners || 0,
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

// Chart helpers
var chartInstances = {};
var currentChartRange = "1h";

function getSinceTimestamp(range) {
  var now = new Date();
  if (range === "24h") now.setHours(now.getHours() - 24);
  else if (range === "7d") now.setDate(now.getDate() - 7);
  else if (range === "30d") now.setDate(now.getDate() - 30);
  else now.setHours(now.getHours() - 1);
  return now.toISOString().replace("Z", "");
}

function getChartThresholds(type, config) {
  if (type === "ping" || type === "http" || type === "port") {
    return {
      warning: config.warning_ms,
      critical: config.critical_ms,
      unit: "ms",
    };
  }
  if (type === "certificate") {
    return {
      warning: config.warning_days,
      critical: config.critical_days,
      unit: "Tage",
      inverted: true,
    };
  }
  if (type === "disk" || type === "memory") {
    return {
      warning: config.warning_pct,
      critical: config.critical_pct,
      unit: "%",
    };
  }
  if (type === "load") {
    return { warning: config.warning, critical: config.critical, unit: "Load" };
  }
  if (type === "icecast_listeners") {
    return {
      warning: config.warning_listeners,
      critical: config.critical_listeners,
      unit: "Listeners",
      inverted: true,
    };
  }
  return { warning: null, critical: null, unit: "" };
}

function loadChart(checkId, range) {
  var since = getSinceTimestamp(range);
  var container = document.getElementById("chart-container-" + checkId);
  if (!container) return;

  var checkType = container.dataset.checkType;
  var config = {};
  try {
    config = JSON.parse(container.dataset.checkConfig || "{}");
  } catch (e) {}
  var thresholds = getChartThresholds(checkType, config);
  var dark = isDarkActive();
  var textColor = dark ? "#ffffffb3" : "#666";
  var gridColor = dark ? "#ffffff1a" : "#0000001a";
  var lineColor = "#007aff";

  fetch(
    "/api/checks/" + checkId + "/results?since=" + encodeURIComponent(since),
  )
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      if (chartInstances[checkId]) {
        chartInstances[checkId].destroy();
        delete chartInstances[checkId];
      }

      if (!data.length) {
        container.innerHTML =
          '<div style="text-align:center; color:gray; padding:1rem; font-size:0.8rem;">Keine Daten im gewählten Zeitraum</div>';
        return;
      }

      container.innerHTML =
        '<canvas id="chart-' +
        checkId +
        '" style="max-height:200px;"></canvas>';
      var ctx = document.getElementById("chart-" + checkId).getContext("2d");

      var labels = [];
      var values = [];
      var pointColors = [];
      data.forEach(function (r) {
        labels.push(r.checked_at + "Z");
        values.push(r.value);
        pointColors.push(statusColors[r.status] || statusColors.unknown);
      });

      // Threshold zone plugin
      var zonePlugin = {
        id: "thresholdZones",
        beforeDraw: function (chart) {
          if (thresholds.warning == null || thresholds.critical == null) return;
          var yAxis = chart.scales.y;
          var xAxis = chart.scales.x;
          var ctx2 = chart.ctx;
          var left = xAxis.left;
          var right = xAxis.right;

          var zones;
          if (thresholds.inverted) {
            // certificate: higher = better
            zones = [
              {
                from: yAxis.min,
                to: thresholds.critical,
                color: "rgba(255,59,48,0.08)",
              },
              {
                from: thresholds.critical,
                to: thresholds.warning,
                color: "rgba(255,149,0,0.08)",
              },
              {
                from: thresholds.warning,
                to: yAxis.max,
                color: "rgba(76,217,100,0.08)",
              },
            ];
          } else {
            zones = [
              {
                from: yAxis.min,
                to: thresholds.warning,
                color: "rgba(76,217,100,0.08)",
              },
              {
                from: thresholds.warning,
                to: thresholds.critical,
                color: "rgba(255,149,0,0.08)",
              },
              {
                from: thresholds.critical,
                to: yAxis.max,
                color: "rgba(255,59,48,0.08)",
              },
            ];
          }

          zones.forEach(function (z) {
            var top = yAxis.getPixelForValue(Math.min(z.to, yAxis.max));
            var bottom = yAxis.getPixelForValue(Math.max(z.from, yAxis.min));
            if (top > bottom) {
              var tmp = top;
              top = bottom;
              bottom = tmp;
            }
            ctx2.save();
            ctx2.fillStyle = z.color;
            ctx2.fillRect(left, top, right - left, bottom - top);
            ctx2.restore();
          });
        },
      };

      chartInstances[checkId] = new Chart(ctx, {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              data: values,
              borderColor: lineColor,
              borderWidth: 2,
              pointBackgroundColor: pointColors,
              pointBorderColor: pointColors,
              pointRadius: 0,
              pointHoverRadius: 5,
              tension: 0.2,
              fill: false,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: function (items) {
                  var d = new Date(items[0].parsed.x);
                  return (
                    d.toLocaleDateString("de-DE") +
                    " " +
                    d.toLocaleTimeString("de-DE")
                  );
                },
                label: function (item) {
                  return (
                    (item.parsed.y != null ? item.parsed.y : "-") +
                    " " +
                    thresholds.unit
                  );
                },
              },
            },
          },
          scales: {
            x: {
              type: "time",
              time: { tooltipFormat: "dd.MM.yyyy HH:mm" },
              ticks: { color: textColor, maxTicksLimit: 8 },
              grid: { color: gridColor },
            },
            y: {
              ticks: { color: textColor },
              grid: { color: gridColor },
              title: { display: true, text: thresholds.unit, color: textColor },
            },
          },
        },
        plugins: [zonePlugin],
      });
    });
}

function reloadVisibleCharts() {
  Object.keys(chartInstances).forEach(function (checkId) {
    var container = document.getElementById("chart-container-" + checkId);
    if (container && container.style.display !== "none") {
      loadChart(checkId, currentChartRange);
    }
  });
}

// Cache buster for SPA page templates
var pageV = "?v=" + (APP_VERSION || Date.now());

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
      url: "/assets/js/pages/home.html" + pageV,
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
            window.location.reload();
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
      url: "/assets/js/pages/host-edit.html" + pageV,
      on: {
        pageInit: function (e, page) {
          page.$el.find("#page-title").text("Neuer Host");

          page.$el.find("#save-host").on("click", function () {
            var name = page.$el.find("#host-name").val().trim();
            var address = page.$el.find("#host-address").val().trim();
            var description = page.$el.find("#host-description").val().trim();
            var topic = page.$el.find("#host-topic").val().trim();
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
                topic: topic,
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
      url: "/assets/js/pages/host-detail.html" + pageV,
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

          // Toggle chart on check card click
          page.$el.on("click", ".check-card", function (ev) {
            if (ev.target.closest("a")) return;
            var checkId = this.dataset.checkId;
            var container = document.getElementById(
              "chart-container-" + checkId,
            );
            if (!container) return;
            if (container.style.display === "none") {
              container.style.display = "block";
              loadChart(checkId, currentChartRange);
            } else {
              container.style.display = "none";
              if (chartInstances[checkId]) {
                chartInstances[checkId].destroy();
                delete chartInstances[checkId];
              }
            }
          });

          // Time range selector
          page.$el.find(".chart-range-btn").on("click", function () {
            page.$el.find(".chart-range-btn").removeClass("button-active");
            this.classList.add("button-active");
            currentChartRange = this.dataset.range;
            reloadVisibleCharts();
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
        pageBeforeRemove: function () {
          Object.keys(chartInstances).forEach(function (id) {
            chartInstances[id].destroy();
          });
          chartInstances = {};
        },
      },
    },

    // Host edit
    {
      path: "/hosts/:id/edit/",
      url: "/assets/js/pages/host-edit.html" + pageV,
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
              page.$el.find("#host-topic").val(data.topic || "");
              page.$el.find("#host-enabled")[0].checked = !!data.enabled;
            });

          page.$el.find("#save-host").on("click", function () {
            var name = page.$el.find("#host-name").val().trim();
            var address = page.$el.find("#host-address").val().trim();
            var description = page.$el.find("#host-description").val().trim();
            var topic = page.$el.find("#host-topic").val().trim();
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
                topic: topic,
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
      url: "/assets/js/pages/check-edit.html" + pageV,
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
            })
              .then(function (r) {
                if (!r.ok) {
                  return r.json().then(function (d) {
                    throw new Error(d.error || "Fehler beim Speichern");
                  });
                }
                app.views.main.router.back();
              })
              .catch(function (err) {
                app.dialog.alert("Fehler: " + err.message);
              });
          });
        },
      },
    },

    // Check edit
    {
      path: "/checks/:id/edit/",
      url: "/assets/js/pages/check-edit.html" + pageV,
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
            })
              .then(function (r) {
                if (!r.ok) {
                  return r.json().then(function (d) {
                    throw new Error(d.error || "Fehler beim Speichern");
                  });
                }
                app.views.main.router.back();
              })
              .catch(function (err) {
                app.dialog.alert("Fehler: " + err.message);
              });
          });
        },
      },
    },

    // Settings
    {
      path: "/settings/",
      url: "/assets/js/pages/settings.html" + pageV,
      on: {
        pageInit: function (e, page) {
          // Dark mode segmented control
          page.$el.find(".darkmode-btn").each(function () {
            if (this.dataset.mode === darkModePref) {
              this.classList.add("button-active");
            }
          });
          page.$el.find(".darkmode-btn").on("click", function () {
            page.$el.find(".darkmode-btn").removeClass("button-active");
            this.classList.add("button-active");
            setDarkModePreference(this.dataset.mode);
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
              page.$el
                .find("#settings-runner")
                .text(
                  data.runner_last_run ? timeAgo(data.runner_last_run) : "nie",
                );
            });

          // Push Notifications toggle
          var pushToggle = page.$el.find("#settings-push")[0];
          if (
            pushToggle &&
            "serviceWorker" in navigator &&
            "PushManager" in window
          ) {
            navigator.serviceWorker.ready.then(function (reg) {
              reg.pushManager.getSubscription().then(function (sub) {
                pushToggle.checked = !!sub;
              });
            });

            page.$el.find("#settings-push").on("change", function () {
              if (this.checked) {
                Notification.requestPermission().then(function (permission) {
                  if (permission !== "granted") {
                    pushToggle.checked = false;
                    app.dialog.alert(
                      "Benachrichtigungen wurden vom Browser blockiert.",
                    );
                    return;
                  }
                  fetch("/api/notifications/vapid-key")
                    .then(function (r) {
                      return r.json();
                    })
                    .then(function (data) {
                      var key = data.publicKey;
                      var padding = "=".repeat((4 - (key.length % 4)) % 4);
                      var base64 = (key + padding)
                        .replace(/-/g, "+")
                        .replace(/_/g, "/");
                      var raw = atob(base64);
                      var arr = new Uint8Array(raw.length);
                      for (var i = 0; i < raw.length; i++)
                        arr[i] = raw.charCodeAt(i);

                      navigator.serviceWorker.ready.then(function (reg) {
                        reg.pushManager
                          .subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: arr,
                          })
                          .then(function (sub) {
                            var subJson = sub.toJSON();
                            fetch("/api/notifications/subscribe", {
                              method: "POST",
                              headers: { "Content-Type": "application/json" },
                              body: JSON.stringify({
                                endpoint: subJson.endpoint,
                                keys: subJson.keys,
                              }),
                            });
                          })
                          .catch(function () {
                            pushToggle.checked = false;
                            app.dialog.alert(
                              "Push-Subscription fehlgeschlagen.",
                            );
                          });
                      });
                    });
                });
              } else {
                navigator.serviceWorker.ready.then(function (reg) {
                  reg.pushManager.getSubscription().then(function (sub) {
                    if (sub) {
                      var endpoint = sub.endpoint;
                      sub.unsubscribe().then(function () {
                        fetch("/api/notifications/unsubscribe", {
                          method: "POST",
                          headers: { "Content-Type": "application/json" },
                          body: JSON.stringify({ endpoint: endpoint }),
                        });
                      });
                    }
                  });
                });
              }
            });
          } else if (pushToggle) {
            pushToggle.disabled = true;
          }

          // Push test button
          page.$el.find("#push-test-btn").on("click", function () {
            var btn = this;
            btn.disabled = true;
            btn.textContent = "...";
            fetch("/api/notifications/test", { method: "POST" })
              .then(function (r) {
                return r.json();
              })
              .then(function (data) {
                btn.disabled = false;
                btn.textContent = "Senden";
                if (data.sent > 0) {
                  app.dialog.alert(
                    "Test gesendet an " + data.sent + " Gerät(e).",
                  );
                } else if (data.failed > 0) {
                  app.dialog.alert(
                    "Senden fehlgeschlagen (" + data.failed + " Fehler).",
                  );
                } else {
                  app.dialog.alert(
                    "Keine aktiven Push-Subscriptions vorhanden.",
                  );
                }
              })
              .catch(function () {
                btn.disabled = false;
                btn.textContent = "Senden";
                app.dialog.alert(
                  "Fehler beim Senden der Test-Benachrichtigung.",
                );
              });
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
