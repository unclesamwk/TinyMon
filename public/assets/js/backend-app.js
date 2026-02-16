// 401 → redirect to login, network error detection, CSRF token injection
(function () {
  var originalFetch = window.fetch;
  window.fetch = function (url, options) {
    options = options || {};
    var method = (options.method || "GET").toUpperCase();
    if (
      method !== "GET" &&
      method !== "HEAD" &&
      typeof url === "string" &&
      url.indexOf("/api/") !== -1
    ) {
      options.headers = options.headers || {};
      options.headers["X-CSRF-Token"] = CSRF_TOKEN;
    }
    return originalFetch
      .call(this, url, options)
      .then(function (response) {
        if (
          response.status === 401 &&
          typeof url === "string" &&
          url.indexOf("/api/") !== -1
        ) {
          window.location.href = "/backend/login";
        }
        return response;
      })
      .catch(function (err) {
        if (!navigator.onLine) {
          if (typeof app !== "undefined" && app.toast) {
            app.toast
              .create({
                text: t("no_internet"),
                position: "center",
                closeTimeout: 3000,
              })
              .open();
          }
        }
        throw err;
      });
  };
})();

// Online/offline banner
window.addEventListener("online", function () {
  if (typeof app !== "undefined" && app.toast) {
    app.toast
      .create({
        text: t("online_again"),
        position: "center",
        closeTimeout: 2000,
      })
      .open();
  }
});
window.addEventListener("offline", function () {
  if (typeof app !== "undefined" && app.toast) {
    app.toast
      .create({ text: t("offline"), position: "center", closeTimeout: 3000 })
      .open();
  }
});

// Time format (24h / 12h)
var timeFormatPref = localStorage.getItem("timeFormat") || "24h";

function setTimeFormat(fmt) {
  timeFormatPref = fmt;
  localStorage.setItem("timeFormat", fmt);
  reloadVisibleCharts();
}

// Language (de / en)
var langPref = localStorage.getItem("lang") || "en";
var translations = {
  de: {
    no_hosts: "Keine Hosts konfiguriert.\nTippe + um einen Host hinzuzufügen.",
    general: "Allgemein",
    host: "Host",
    hosts: "Hosts",
    check: "Check",
    checks: "Checks",
    no_checks: "Keine Checks",
    runner_prefix: "Runner",
    runner_never: "noch nicht gelaufen",
    ok: "OK",
    warning: "Warning",
    critical: "Critical",
    unknown: "Unknown",
    never: "nie",
    s_ago: "s her",
    m_ago: "m her",
    h_ago: "h her",
    d_ago: "d her",
    host_created: "Host erstellt",
    host_saved: "Host gespeichert",
    host_deleted: "Host gelöscht",
    check_created: "Check erstellt",
    check_saved: "Check gespeichert",
    check_deleted: "Check gelöscht",
    check_run: "Check ausgeführt",
    check_run_error: "Fehler beim Ausführen",
    accept_hash: "Hash akzeptieren",
    hash_accepted: "Hash akzeptiert",
    hash_accept_error: "Fehler beim Akzeptieren",
    delete_host_title: "Host löschen",
    delete_host_confirm: "Host und alle Checks wirklich löschen?",
    delete_check_title: "Löschen",
    delete_check_confirm: "Check wirklich löschen?",
    delete: "Löschen",
    required_fields: "Name und Adresse sind Pflichtfelder.",
    error_prefix: "Fehler",
    save_error: "Fehler beim Speichern",
    offline: "Offline",
    online_again: "Wieder online",
    no_internet: "Keine Internetverbindung",
    chart: "Chart",
    close: "Schließen",
    no_data: "Keine Daten im gewählten Zeitraum",
    unchanged: "Unverändert",
    changed: "Geändert",
    days: "Tage",
    type_ping: "Ping",
    type_http: "HTTP",
    type_port: "Port",
    type_certificate: "Zertifikat",
    type_disk: "Festplatte",
    type_disk_health: "Disk Health",
    type_load: "Load",
    type_memory: "Arbeitsspeicher",
    type_content: "Inhalt",
    type_content_hash: "Inhalt Hash",
    type_icecast: "Icecast",
    type_status: "Status",
    cfg_path: "Pfad",
    cfg_port: "Port",
    cfg_expected_status: "Erwarteter Status",
    cfg_warning_ms: "Warning (ms)",
    cfg_critical_ms: "Critical (ms)",
    cfg_warning_days: "Warning (Tage)",
    cfg_critical_days: "Critical (Tage)",
    cfg_warning_pct: "Warning (%)",
    cfg_critical_pct: "Critical (%)",
    cfg_warning_load: "Warning (Load)",
    cfg_critical_load: "Critical (Load)",
    cfg_expected_content: "Erwarteter Inhalt",
    cfg_unexpected_content: "Unerwarteter Inhalt",
    cfg_selector: "Regex-Selektor (optional)",
    cfg_mount: "Mountpoint",
    cfg_warning_listeners: "Warning (Listeners)",
    cfg_critical_listeners: "Critical (Listeners)",
    new_host: "Neuer Host",
    edit_host: "Host bearbeiten",
    name: "Name",
    address: "Adresse",
    description: "Beschreibung",
    topic: "Topic",
    enabled: "Aktiviert",
    save: "Speichern",
    ph_name: "z.B. Webserver",
    ph_address: "z.B. example.com oder 1.2.3.4",
    ph_optional: "Optional",
    ph_topic: "z.B. production/default/deployments",
    new_check: "Neuer Check",
    edit_check: "Check bearbeiten",
    type: "Typ",
    interval_seconds: "Intervall (Sekunden)",
    configuration: "Konfiguration",
    run: "Prüfen",
    settings: "Einstellungen",
    notifications: "Benachrichtigungen",
    push_notifications: "Push Notifications",
    test_notification: "Test-Benachrichtigung",
    send: "Senden",
    appearance: "Darstellung",
    dark_mode: "Dark Mode",
    auto: "Auto",
    off: "Aus",
    on: "An",
    theme: "Theme",
    ios: "iOS",
    android: "Android",
    time_format: "Zeitformat",
    language: "Sprache",
    info: "Info",
    runner: "Runner",
    version: "Version",
    logout: "Abmelden",
    push_blocked: "Benachrichtigungen wurden vom Browser blockiert.",
    push_failed: "Push-Subscription fehlgeschlagen.",
    push_test_sent: "Test gesendet an %d Gerät(e).",
    push_test_failed: "Senden fehlgeschlagen (%d Fehler).",
    push_no_subs: "Keine aktiven Push-Subscriptions vorhanden.",
    push_test_error: "Fehler beim Senden der Test-Benachrichtigung.",
    not_checked_yet: "Noch nicht geprüft",
    add_check: "Check hinzufügen",
    delete_host_btn: "Host löschen",
    "7_days": "7 Tage",
    "30_days": "30 Tage",
    password: "Passwort",
    login: "Anmelden",
  },
  en: {
    no_hosts: "No hosts configured.\nTap + to add a host.",
    general: "General",
    host: "Host",
    hosts: "Hosts",
    check: "Check",
    checks: "Checks",
    no_checks: "No checks",
    runner_prefix: "Runner",
    runner_never: "not yet run",
    ok: "OK",
    warning: "Warning",
    critical: "Critical",
    unknown: "Unknown",
    never: "never",
    s_ago: "s ago",
    m_ago: "m ago",
    h_ago: "h ago",
    d_ago: "d ago",
    host_created: "Host created",
    host_saved: "Host saved",
    host_deleted: "Host deleted",
    check_created: "Check created",
    check_saved: "Check saved",
    check_deleted: "Check deleted",
    check_run: "Check executed",
    check_run_error: "Execution failed",
    accept_hash: "Accept hash",
    hash_accepted: "Hash accepted",
    hash_accept_error: "Accept failed",
    delete_host_title: "Delete host",
    delete_host_confirm: "Delete host and all checks?",
    delete_check_title: "Delete",
    delete_check_confirm: "Delete this check?",
    delete: "Delete",
    required_fields: "Name and address are required.",
    error_prefix: "Error",
    save_error: "Error saving",
    offline: "Offline",
    online_again: "Back online",
    no_internet: "No internet connection",
    chart: "Chart",
    close: "Close",
    no_data: "No data in selected time range",
    unchanged: "Unchanged",
    changed: "Changed",
    days: "Days",
    type_ping: "Ping",
    type_http: "HTTP",
    type_port: "Port",
    type_certificate: "Certificate",
    type_disk: "Disk",
    type_disk_health: "Disk Health",
    type_load: "Load",
    type_memory: "Memory",
    type_content: "Content",
    type_content_hash: "Content Hash",
    type_icecast: "Icecast",
    type_status: "Status",
    cfg_path: "Path",
    cfg_port: "Port",
    cfg_expected_status: "Expected status",
    cfg_warning_ms: "Warning (ms)",
    cfg_critical_ms: "Critical (ms)",
    cfg_warning_days: "Warning (days)",
    cfg_critical_days: "Critical (days)",
    cfg_warning_pct: "Warning (%)",
    cfg_critical_pct: "Critical (%)",
    cfg_warning_load: "Warning (Load)",
    cfg_critical_load: "Critical (Load)",
    cfg_expected_content: "Expected content",
    cfg_unexpected_content: "Unexpected content",
    cfg_selector: "Regex selector (optional)",
    cfg_mount: "Mountpoint",
    cfg_warning_listeners: "Warning (Listeners)",
    cfg_critical_listeners: "Critical (Listeners)",
    new_host: "New Host",
    edit_host: "Edit Host",
    name: "Name",
    address: "Address",
    description: "Description",
    topic: "Topic",
    enabled: "Enabled",
    save: "Save",
    ph_name: "e.g. Webserver",
    ph_address: "e.g. example.com or 1.2.3.4",
    ph_optional: "Optional",
    ph_topic: "e.g. production/default/deployments",
    new_check: "New Check",
    edit_check: "Edit Check",
    type: "Type",
    interval_seconds: "Interval (seconds)",
    configuration: "Configuration",
    run: "Run",
    settings: "Settings",
    notifications: "Notifications",
    push_notifications: "Push Notifications",
    test_notification: "Test notification",
    send: "Send",
    appearance: "Appearance",
    dark_mode: "Dark Mode",
    auto: "Auto",
    off: "Off",
    on: "On",
    theme: "Theme",
    ios: "iOS",
    android: "Android",
    time_format: "Time format",
    language: "Language",
    info: "Info",
    runner: "Runner",
    version: "Version",
    logout: "Log out",
    push_blocked: "Notifications were blocked by the browser.",
    push_failed: "Push subscription failed.",
    push_test_sent: "Test sent to %d device(s).",
    push_test_failed: "Sending failed (%d errors).",
    push_no_subs: "No active push subscriptions.",
    push_test_error: "Error sending test notification.",
    not_checked_yet: "Not checked yet",
    add_check: "Add check",
    delete_host_btn: "Delete host",
    "7_days": "7 Days",
    "30_days": "30 Days",
    password: "Password",
    login: "Log in",
  },
};

function t(key) {
  return (translations[langPref] || translations.de)[key] || key;
}

function setLang(lang) {
  langPref = lang;
  localStorage.setItem("lang", lang);
  document.cookie = "lang=" + lang + ";path=/;max-age=31536000";
  var currentUrl = app.views.main.router.currentRoute.url || "/settings/";
  app.views.main.router.navigate(currentUrl, { reloadCurrent: true });
}

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

function iconHtml(iosName, mdName, style) {
  var isIos = app.theme === "ios";
  var cls = isIos ? "f7-icons" : "material-icons";
  var name = isIos ? iosName : mdName;
  var base = style ? style + "; " : "";
  return (
    '<i class="icon ' +
    cls +
    '" style="' +
    base +
    'margin-right:4px;">' +
    name +
    "</i>"
  );
}

function iconName(iosName, mdName) {
  return app.theme === "ios" ? iosName : mdName;
}

function iconClass() {
  return app.theme === "ios" ? "f7-icons" : "material-icons";
}

function updateDarkModeIcon() {
  var iosIcon = document.querySelector("#dark-mode-icon");
  var mdIcon = document.querySelector("#dark-mode-icon-md");
  if (iosIcon)
    iosIcon.textContent = isDarkActive() ? "sun_max_fill" : "moon_fill";
  if (mdIcon) mdIcon.textContent = isDarkActive() ? "light_mode" : "dark_mode";
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
  ok: { ios: "checkmark_circle_fill", md: "check_circle" },
  warning: { ios: "exclamationmark_triangle_fill", md: "warning" },
  critical: { ios: "xmark_circle_fill", md: "error" },
  unknown: { ios: "question_circle_fill", md: "help" },
};

function statusBadge(status) {
  var color = statusColors[status] || statusColors.unknown;
  var entry = statusIcons[status] || statusIcons.unknown;
  var icon = app.theme === "ios" ? entry.ios : entry.md;
  return (
    '<i class="icon ' +
    iconClass() +
    '" style="color:' +
    color +
    '; font-size:20px;">' +
    icon +
    "</i>"
  );
}

function typeIcon(type) {
  var icons = {
    ping: { ios: "wifi", md: "network_ping" },
    http: { ios: "globe", md: "language" },
    port: { ios: "bolt", md: "power" },
    certificate: { ios: "checkmark_seal_fill", md: "verified_user" },
    disk: { ios: "tray_full_fill", md: "storage" },
    disk_health: { ios: "checkmark_shield", md: "health_and_safety" },
    load: { ios: "speedometer", md: "speed" },
    memory: { ios: "gauge", md: "memory" },
    content: { ios: "doc_text", md: "article" },
    content_hash: { ios: "number", md: "fingerprint" },
    icecast_listeners: { ios: "antenna_radiowaves_left_right", md: "radio" },
    status: { ios: "info_circle", md: "info" },
  };
  var entry = icons[type] || { ios: "waveform_path_ecg", md: "monitor_heart" };
  return app.theme === "ios" ? entry.ios : entry.md;
}

function typeLabel(type) {
  var map = {
    ping: "type_ping",
    http: "type_http",
    port: "type_port",
    certificate: "type_certificate",
    disk: "type_disk",
    disk_health: "type_disk_health",
    load: "type_load",
    memory: "type_memory",
    content: "type_content",
    content_hash: "type_content_hash",
    icecast_listeners: "type_icecast",
    status: "type_status",
  };
  return t(map[type] || type);
}

// Toast helper
function showToast(text) {
  app.toast
    .create({ text: text, position: "center", closeTimeout: 2000 })
    .open();
}

function escHtml(str) {
  if (!str) return "";
  var div = document.createElement("div");
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

function timeAgo(dateStr) {
  if (!dateStr) return t("never");
  var diff = (Date.now() - new Date(dateStr + "Z").getTime()) / 1000;
  if (diff < 60) return Math.floor(diff) + t("s_ago");
  if (diff < 3600) return Math.floor(diff / 60) + t("m_ago");
  if (diff < 86400) return Math.floor(diff / 3600) + t("h_ago");
  return Math.floor(diff / 86400) + t("d_ago");
}

function renderHostListItem(h) {
  var cs =
    h.checks && h.checks.length > 0
      ? h.checks.length + " " + (h.checks.length > 1 ? t("checks") : t("check"))
      : t("no_checks");
  var li = '<li class="swipeout" data-host-id="' + h.id + '">';
  li +=
    '<a href="#" class="item-link item-content swipeout-content host-link" data-host-id="' +
    h.id +
    '">';
  li += '<div class="item-media">' + statusBadge(h.status) + "</div>";
  li +=
    '<div class="item-inner"><div class="item-title-row"><div class="item-title">' +
    escHtml(h.name) +
    "</div>";
  li +=
    '<div class="item-after" style="color:gray; font-size:0.8rem;">' +
    cs +
    "</div>";
  li += "</div>";
  li +=
    '<div class="item-subtitle" style="color:gray;">' +
    escHtml(h.address) +
    "</div>";
  li += "</div></a>";
  li +=
    '<div class="swipeout-actions-right"><a href="#" class="swipeout-delete-host color-red swipeout-close" data-host-id="' +
    h.id +
    '">' +
    t("delete") +
    "</a></div>";
  li += "</li>";
  return li;
}

// Dashboard loader
var dashboardFilter = null;
var dashboardData = null;

function renderDashboard(page) {
  if (!dashboardData) return;
  var data = dashboardData;
  var summary = data.summary || {};
  var hosts = data.hosts || [];

  function filterBadge(status, count, color, label) {
    var active = dashboardFilter === status;
    var opacity = dashboardFilter && !active ? "0.4" : "1";
    var border = active
      ? "border-bottom:2px solid " + color
      : "border-bottom:2px solid transparent";
    return (
      '<div class="status-filter" data-status="' +
      status +
      '" style="cursor:pointer; padding:0.25rem 0.5rem; ' +
      border +
      "; opacity:" +
      opacity +
      ';">' +
      '<div style="font-size:2rem; font-weight:bold; color:' +
      color +
      ';">' +
      (count || 0) +
      "</div>" +
      '<div style="font-size:0.75rem; color:gray;">' +
      label +
      "</div></div>"
    );
  }

  var s =
    '<div style="display:flex; justify-content:space-around; text-align:center; margin:1rem 0; padding:0 1rem;">';
  s += filterBadge("ok", summary.ok, "#4cd964", "OK");
  s += filterBadge("warning", summary.warning, "#ff9500", "Warning");
  s += filterBadge("critical", summary.critical, "#ff3b30", "Critical");
  s += filterBadge("unknown", summary.unknown, "#8e8e93", "Unknown");
  s += "</div>";
  page.$el.find("#dashboard-summary").html(s);

  page.$el.find(".status-filter").on("click", function () {
    var clicked = this.dataset.status;
    dashboardFilter = dashboardFilter === clicked ? null : clicked;
    renderDashboard(page);
  });

  if (dashboardFilter) {
    hosts = hosts.filter(function (h) {
      return h.status === dashboardFilter;
    });
  }

  var runnerEl = page.$el.find("#runner-status");
  if (data.runner_last_run) {
    runnerEl.html(t("runner_prefix") + ": " + timeAgo(data.runner_last_run));
  } else {
    runnerEl.html(t("runner_prefix") + ": " + t("runner_never"));
  }

  var html = "";
  if (hosts.length === 0) {
    html =
      '<div class="block text-align-center" style="color:gray; padding:2rem;">' +
      t("no_hosts").replace("\n", "<br>") +
      "</div>";
  } else {
    function buildTopicTree(hosts) {
      var root = { children: {}, hosts: [] };
      hosts.forEach(function (h) {
        var topic = (h.topic || "").trim();
        if (topic === "") {
          root.hosts.push(h);
        } else {
          var parts = topic.split("/");
          var node = root;
          parts.forEach(function (part) {
            if (!node.children[part]) {
              node.children[part] = { children: {}, hosts: [] };
            }
            node = node.children[part];
          });
          node.hosts.push(h);
        }
      });
      return root;
    }

    var statusPrio = { ok: 0, unknown: 1, warning: 2, critical: 3 };
    function worseStatus(a, b) {
      return (statusPrio[b] || 1) > (statusPrio[a] || 1) ? b : a;
    }

    function treeStatus(node) {
      var status = "unknown";
      node.hosts.forEach(function (h) {
        status = worseStatus(status, h.status);
      });
      Object.keys(node.children).forEach(function (key) {
        status = worseStatus(status, treeStatus(node.children[key]));
      });
      return status;
    }

    function countHosts(node) {
      var count = node.hosts.length;
      Object.keys(node.children).forEach(function (key) {
        count += countHosts(node.children[key]);
      });
      return count;
    }

    function collapsePath(node) {
      var groups = [];
      var childKeys = Object.keys(node.children).sort();

      childKeys.forEach(function (key) {
        var child = node.children[key];
        var label = key;
        var current = child;

        while (
          current.hosts.length === 0 &&
          Object.keys(current.children).length === 1
        ) {
          var nextKey = Object.keys(current.children)[0];
          label += " / " + nextKey;
          current = current.children[nextKey];
        }

        groups.push({ label: label, node: current });
      });

      return groups;
    }

    function renderTree(node, depth) {
      var out = "";
      var groups = collapsePath(node);
      depth = depth || 0;
      var indent = depth * 0.75;

      groups.forEach(function (group) {
        var child = group.node;
        var groupStatus = treeStatus(child);
        var hostCount = countHosts(child);
        var subGroups = collapsePath(child);
        var hasSubGroups = subGroups.length > 0;

        out += '<li class="accordion-item accordion-item-opened">';
        out +=
          '<a class="item-link item-content topic-header" href="#" style="padding-left:' +
          indent +
          'rem;">';
        out += '<div class="item-media">' + statusBadge(groupStatus) + "</div>";
        out += '<div class="item-inner"><div class="item-title-row">';
        out +=
          '<div class="item-title" style="font-weight:700; font-size:' +
          (depth === 0 ? "0.9rem" : "0.85rem") +
          '; text-transform:uppercase; letter-spacing:0.5px;">' +
          escHtml(group.label) +
          "</div>";
        out +=
          '<div class="item-after" style="color:gray; font-size:0.8rem;">' +
          hostCount +
          " " +
          (hostCount > 1 ? t("hosts") : t("host")) +
          "</div>";
        out += "</div></div></a>";
        out += '<div class="accordion-item-content">';

        if (child.hosts.length > 0) {
          out +=
            '<div class="list media-list" style="padding-left:' +
            indent +
            'rem;"><ul>';
          child.hosts.forEach(function (h) {
            out += renderHostListItem(h);
          });
          out += "</ul></div>";
        }

        if (hasSubGroups) {
          out += '<div class="list accordion-list media-list"><ul>';
          out += renderTree(child, depth + 1);
          out += "</ul></div>";
        }

        out += "</div></li>";
      });

      if (node.hosts.length > 0 && groups.length > 0) {
        var ungroupedStatus = "unknown";
        node.hosts.forEach(function (h) {
          ungroupedStatus = worseStatus(ungroupedStatus, h.status);
        });
        out += '<li class="accordion-item accordion-item-opened">';
        out +=
          '<a class="item-link item-content topic-header" href="#" style="padding-left:' +
          indent +
          'rem;">';
        out +=
          '<div class="item-media">' + statusBadge(ungroupedStatus) + "</div>";
        out += '<div class="item-inner"><div class="item-title-row">';
        out +=
          '<div class="item-title" style="font-weight:700; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.5px;">' +
          t("general") +
          "</div>";
        out +=
          '<div class="item-after" style="color:gray; font-size:0.8rem;">' +
          node.hosts.length +
          " " +
          (node.hosts.length > 1 ? t("hosts") : t("host")) +
          "</div>";
        out += "</div></div></a>";
        out += '<div class="accordion-item-content">';
        out +=
          '<div class="list media-list" style="padding-left:' +
          indent +
          'rem;"><ul>';
        node.hosts.forEach(function (h) {
          out += renderHostListItem(h);
        });
        out += "</ul></div></div></li>";
      }

      return out;
    }

    var tree = buildTopicTree(hosts);
    var childKeys = Object.keys(tree.children);

    if (childKeys.length === 0 && tree.hosts.length > 0) {
      html = '<div class="list media-list"><ul>';
      tree.hosts.forEach(function (h) {
        html += renderHostListItem(h);
      });
      html += "</ul></div>";
    } else {
      html = '<div class="list accordion-list media-list"><ul>';
      html += renderTree(tree, 0);
      html += "</ul></div>";
    }
  }
  page.$el.find("#host-list").html(html);

  page.$el.find(".host-link").on("click", function (ev) {
    ev.preventDefault();
    app.views.main.router.navigate("/hosts/" + this.dataset.hostId + "/");
  });

  page.$el.find(".swipeout-delete-host").on("click", function (ev) {
    ev.preventDefault();
    var hostId = this.dataset.hostId;
    var li = this.closest("li");
    app.dialog.confirm(
      t("delete_host_confirm"),
      t("delete_host_title"),
      function () {
        fetch("/api/hosts/" + hostId, { method: "DELETE" }).then(function () {
          app.swipeout.delete(li);
          showToast(t("host_deleted"));
        });
      },
    );
  });
}

function loadDashboard(page) {
  fetch("/api/dashboard")
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      dashboardData = data;
      renderDashboard(page);
    })
    .catch(function (err) {
      page.$el
        .find("#host-list")
        .html(
          '<div class="block text-align-center" style="color:red;">' +
            escHtml(t("error_prefix") + ": " + err.message) +
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
          '<div class="block text-align-center" style="color:gray;">' +
          t("no_checks") +
          "</div>";
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
          html += '<li class="swipeout" data-check-id="' + c.id + '">';
          html +=
            '<div class="item-content swipeout-content check-card" data-check-id="' +
            c.id +
            '" style="cursor:pointer;">';
          html +=
            '<div class="item-media"><i class="icon ' +
            iconClass() +
            '" style="color:' +
            color +
            ';">' +
            typeIcon(c.type) +
            "</i></div>";
          var checkTitle = typeLabel(c.type);
          if (c.type === "icecast_listeners") {
            var parsedCfg = {};
            try {
              parsedCfg =
                typeof c.config === "string"
                  ? JSON.parse(c.config || "{}")
                  : c.config || {};
            } catch (e) {}
            checkTitle += " " + (parsedCfg.mount || "/stream");
          }
          var valueStr = "";
          if (lr && lr.value != null) {
            valueStr =
              '<span style="font-weight:600; font-size:0.95rem;">' +
              lr.value +
              "</span> ";
          }
          html +=
            '<div class="item-inner"><div class="item-title-row"><div class="item-title" style="font-size:0.85rem; font-weight:600;">' +
            escHtml(checkTitle) +
            "</div>";
          html += '<div class="item-after">' + statusBadge(st) + "</div></div>";
          html +=
            '<div class="item-subtitle" style="color:gray; font-size:0.8rem;">';
          html += lr ? escHtml(lr.message) : t("not_checked_yet");
          html += "</div>";
          if (lr) {
            html +=
              '<div class="item-subtitle" style="color:gray; font-size:0.75rem; margin-top:2px;">';
            html += timeAgo(lr.checked_at);
            html += "</div>";
          }
          var btnStyle = "font-size:0.75rem; padding:0 8px; line-height:28px;";
          var icoSize = app.theme === "ios" ? "12px" : "14px";
          var icoStyle = "font-size:" + icoSize + "; vertical-align:middle;";
          html +=
            '<div class="item-text" style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-top:6px; align-items:center;">';
          html +=
            '<a href="#" class="button button-small button-outline toggle-chart" data-check-id="' +
            c.id +
            '" style="' +
            btnStyle +
            '">' +
            iconHtml("chart_bar", "show_chart", icoStyle) +
            " " +
            t("chart") +
            "</a>";
          var isPushOnly =
            c.type === "status" ||
            (data.address.indexOf("k8s://") === 0 &&
              c.type !== "http" &&
              c.type !== "certificate");
          if (!isPushOnly) {
            html +=
              '<a href="#" class="button button-small button-outline run-check" data-check-id="' +
              c.id +
              '" style="' +
              btnStyle +
              '">' +
              iconHtml("play_fill", "play_arrow", icoStyle) +
              " " +
              t("run") +
              "</a>";
          }
          if (c.type === "content_hash" && lr && lr.status === "warning") {
            html +=
              '<a href="#" class="button button-small button-outline accept-hash" data-check-id="' +
              c.id +
              '" style="' +
              btnStyle +
              '">' +
              iconHtml("checkmark", "check", icoStyle) +
              " " +
              t("accept_hash") +
              "</a>";
          }
          html +=
            '<a href="#" class="button button-small button-outline edit-check" data-check-id="' +
            c.id +
            '" style="' +
            btnStyle +
            '">' +
            iconHtml("pencil", "edit", icoStyle) +
            "</a>";
          html +=
            '<a href="#" class="button button-small button-outline color-red delete-check" data-check-id="' +
            c.id +
            '" style="' +
            btnStyle +
            '">' +
            iconHtml("trash", "delete", icoStyle) +
            "</a>";
          html += "</div></div></div>";
          html +=
            '<div class="check-chart-container" id="chart-container-' +
            c.id +
            '" data-check-type="' +
            escHtml(c.type) +
            '" data-check-config="' +
            escHtml(cfgRaw) +
            '" style="display:none; padding:0.5rem 1rem 1rem;"></div>';
          html +=
            '<div class="swipeout-actions-right"><a href="#" class="swipeout-delete-check color-red swipeout-close" data-check-id="' +
            c.id +
            '">' +
            t("delete") +
            "</a></div>";
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
      {
        key: "warning_ms",
        label: t("cfg_warning_ms"),
        val: cfg.warning_ms || 100,
      },
      {
        key: "critical_ms",
        label: t("cfg_critical_ms"),
        val: cfg.critical_ms || 500,
      },
    ];
  } else if (type === "http") {
    fields = [
      { key: "url", label: t("cfg_path"), val: cfg.url || "/", t: "text" },
      { key: "port", label: t("cfg_port"), val: cfg.port || 443 },
      {
        key: "expected_status",
        label: t("cfg_expected_status"),
        val: cfg.expected_status || 200,
      },
      {
        key: "warning_ms",
        label: t("cfg_warning_ms"),
        val: cfg.warning_ms || 1000,
      },
      {
        key: "critical_ms",
        label: t("cfg_critical_ms"),
        val: cfg.critical_ms || 5000,
      },
    ];
  } else if (type === "port") {
    fields = [
      { key: "port", label: t("cfg_port"), val: cfg.port || 22 },
      {
        key: "warning_ms",
        label: t("cfg_warning_ms"),
        val: cfg.warning_ms || 100,
      },
      {
        key: "critical_ms",
        label: t("cfg_critical_ms"),
        val: cfg.critical_ms || 500,
      },
    ];
  } else if (type === "certificate") {
    fields = [
      { key: "port", label: t("cfg_port"), val: cfg.port || 443 },
      {
        key: "warning_days",
        label: t("cfg_warning_days"),
        val: cfg.warning_days || 30,
      },
      {
        key: "critical_days",
        label: t("cfg_critical_days"),
        val: cfg.critical_days || 7,
      },
    ];
  } else if (type === "disk") {
    fields = [
      { key: "path", label: t("cfg_path"), val: cfg.path || "/", t: "text" },
      {
        key: "warning_pct",
        label: t("cfg_warning_pct"),
        val: cfg.warning_pct || 80,
      },
      {
        key: "critical_pct",
        label: t("cfg_critical_pct"),
        val: cfg.critical_pct || 95,
      },
    ];
  } else if (type === "load") {
    fields = [
      { key: "warning", label: t("cfg_warning_load"), val: cfg.warning || 2.0 },
      {
        key: "critical",
        label: t("cfg_critical_load"),
        val: cfg.critical || 5.0,
      },
    ];
  } else if (type === "memory") {
    fields = [
      {
        key: "warning_pct",
        label: t("cfg_warning_pct"),
        val: cfg.warning_pct || 80,
      },
      {
        key: "critical_pct",
        label: t("cfg_critical_pct"),
        val: cfg.critical_pct || 95,
      },
    ];
  } else if (type === "content") {
    fields = [
      { key: "url", label: t("cfg_path"), val: cfg.url || "/", t: "text" },
      { key: "port", label: t("cfg_port"), val: cfg.port || 443 },
      {
        key: "expected_status",
        label: t("cfg_expected_status"),
        val: cfg.expected_status || 200,
      },
      {
        key: "expected_content",
        label: t("cfg_expected_content"),
        val: cfg.expected_content || "",
        t: "text",
      },
      {
        key: "unexpected_content",
        label: t("cfg_unexpected_content"),
        val: cfg.unexpected_content || "",
        t: "text",
      },
    ];
  } else if (type === "content_hash") {
    fields = [
      { key: "url", label: t("cfg_path"), val: cfg.url || "/", t: "text" },
      { key: "port", label: t("cfg_port"), val: cfg.port || 443 },
      {
        key: "expected_status",
        label: t("cfg_expected_status"),
        val: cfg.expected_status || 200,
      },
      {
        key: "selector",
        label: t("cfg_selector"),
        val: cfg.selector || "",
        t: "text",
      },
    ];
  } else if (type === "icecast_listeners") {
    fields = [
      { key: "port", label: t("cfg_port"), val: cfg.port || 443 },
      {
        key: "mount",
        label: t("cfg_mount"),
        val: cfg.mount || "/stream",
        t: "text",
      },
      {
        key: "warning_listeners",
        label: t("cfg_warning_listeners"),
        val: cfg.warning_listeners || 0,
      },
      {
        key: "critical_listeners",
        label: t("cfg_critical_listeners"),
        val: cfg.critical_listeners || 0,
      },
    ];
  }
  var html = "";
  fields.forEach(function (f) {
    var inputType = f.t || "number";
    html += '<li class="item-content item-input"><div class="item-inner">';
    html += '<div class="item-title item-label">' + f.label + "</div>";
    var inputMode = inputType === "number" ? ' inputmode="decimal"' : "";
    html +=
      '<div class="item-input-wrap"><input type="' +
      inputType +
      '" data-config-key="' +
      f.key +
      '" value="' +
      f.val +
      '" step="any"' +
      inputMode +
      "></div>";
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
  return now.toISOString().replace("T", " ").replace("Z", "").split(".")[0];
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
  if (type === "content_hash") {
    return { warning: null, critical: null, unit: "" };
  }
  if (type === "icecast_listeners") {
    return {
      warning: config.warning_listeners,
      critical: config.critical_listeners,
      unit: "Listeners",
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
          '<div style="text-align:center; color:gray; padding:1rem; font-size:0.8rem;">' +
          t("no_data") +
          "</div>";
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
              pointHitRadius: 20,
              tension: 0.2,
              fill: false,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: "nearest",
            intersect: false,
            axis: "x",
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: function (items) {
                  var d = new Date(items[0].parsed.x);
                  return (
                    d.toLocaleDateString("de-DE") +
                    " " +
                    d.toLocaleTimeString("de-DE", {
                      hour12: timeFormatPref === "12h",
                    })
                  );
                },
                label: function (item) {
                  if (checkType === "content_hash") {
                    return item.parsed.y === 1 ? t("changed") : t("unchanged");
                  }
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
              time: {
                tooltipFormat:
                  timeFormatPref === "12h"
                    ? "dd.MM.yyyy h:mm a"
                    : "dd.MM.yyyy HH:mm",
                displayFormats:
                  timeFormatPref === "12h"
                    ? { hour: "h:mm a", minute: "h:mm a" }
                    : { hour: "HH:mm", minute: "HH:mm" },
              },
              ticks: { color: textColor, maxTicksLimit: 8 },
              grid: { color: gridColor },
            },
            y:
              checkType === "content_hash"
                ? {
                    min: -0.1,
                    max: 1.1,
                    ticks: {
                      color: textColor,
                      stepSize: 1,
                      callback: function (v) {
                        return v === 0
                          ? t("unchanged")
                          : v === 1
                            ? t("changed")
                            : "";
                      },
                    },
                    grid: { color: gridColor },
                  }
                : {
                    ticks: { color: textColor },
                    grid: { color: gridColor },
                    title: {
                      display: true,
                      text: thresholds.unit,
                      color: textColor,
                    },
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

// Force full reload: unregister SW, clear caches, redirect
function forceReload() {
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker
      .getRegistrations()
      .then(function (regs) {
        return Promise.all(
          regs.map(function (r) {
            return r.unregister();
          }),
        );
      })
      .then(function () {
        return caches.keys();
      })
      .then(function (names) {
        return Promise.all(
          names.map(function (n) {
            return caches.delete(n);
          }),
        );
      })
      .then(function () {
        window.location.href = "/backend";
      });
  } else {
    window.location.href = "/backend";
  }
}

// Check server version, forceReload if changed. Returns true if update triggered.
function checkForUpdate(callback) {
  fetch("/api/version")
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      if (data.version && data.version !== APP_VERSION) {
        forceReload();
      } else if (callback) {
        callback();
      }
    })
    .catch(function () {
      if (callback) callback();
    });
}

// Cache buster for SPA page templates
var pageV = "?v=" + (APP_CACHE_BUSTER || APP_VERSION || Date.now());

// Theme preference (auto / ios / md)
var themePref = localStorage.getItem("themePreference") || "auto";

// App
var app = new Framework7({
  el: "#app",
  name: "TinyMon",
  theme: themePref === "auto" ? "auto" : themePref,
  darkMode: initialDarkMode,
  view: { iosSwipeBack: true, mdSwipeBack: true },
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
            checkForUpdate(function () {
              loadDashboard(page);
              app.ptr.done();
            });
          });

          // Auto-refresh every 10s, with version check
          page.dashboardInterval = setInterval(function () {
            checkForUpdate(function () {
              loadDashboard(page);
            });
          }, 10000);
        },
        pageBeforeIn: function (e, page) {
          updateDarkModeIcon();
          loadDashboard(page);
        },
        pageBeforeRemove: function (e, page) {
          if (page.dashboardInterval) clearInterval(page.dashboardInterval);
        },
      },
    },

    // Host new (must be before :id)
    {
      path: "/hosts/new/",
      url: "/assets/js/pages/host-edit.html" + pageV,
      on: {
        pageInit: function (e, page) {
          page.$el.find("#page-title").text(t("new_host"));
          // i18n for host-edit template
          page.$el.find("[data-i18n]").each(function () {
            this.textContent = t(this.dataset.i18n);
          });
          page.$el.find("#host-name").attr("placeholder", t("ph_name"));
          page.$el.find("#host-address").attr("placeholder", t("ph_address"));
          page.$el
            .find("#host-description")
            .attr("placeholder", t("ph_optional"));
          page.$el.find("#host-topic").attr("placeholder", t("ph_topic"));

          page.$el.find("#save-host").on("click", function () {
            var name = page.$el.find("#host-name").val().trim();
            var address = page.$el.find("#host-address").val().trim();
            var description = page.$el.find("#host-description").val().trim();
            var topic = page.$el.find("#host-topic").val().trim();
            var enabled = page.$el.find("#host-enabled")[0].checked ? 1 : 0;

            if (!name || !address) {
              app.dialog.alert(t("required_fields"));
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
                showToast(t("host_created"));
                app.views.main.router.navigate("/hosts/" + data.id + "/");
              })
              .catch(function (err) {
                app.dialog.alert(t("error_prefix") + ": " + err.message);
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
          // i18n for host-detail template
          page.$el.find("[data-i18n]").each(function () {
            this.textContent = t(this.dataset.i18n);
          });
          page.$el.find("#add-check-btn").text(t("add_check"));
          page.$el.find("#delete-host-btn").text(t("delete_host_btn"));

          page.$el.find("#delete-host-btn").on("click", function (ev) {
            ev.preventDefault();
            app.dialog.confirm(
              t("delete_host_confirm"),
              t("delete_host_title"),
              function () {
                fetch("/api/hosts/" + hostId, { method: "DELETE" }).then(
                  function () {
                    showToast(t("host_deleted"));
                    app.views.main.router.back("/");
                  },
                );
              },
            );
          });

          // Toggle chart on check card click or chart button
          function toggleChart(checkId) {
            var container = document.getElementById(
              "chart-container-" + checkId,
            );
            if (!container) return;
            var link = page.$el.find(
              '.toggle-chart[data-check-id="' + checkId + '"]',
            );
            var icoSz = app.theme === "ios" ? "12px" : "14px";
            var icoSt = "font-size:" + icoSz + "; vertical-align:middle;";
            if (container.style.display === "none") {
              container.style.display = "block";
              loadChart(checkId, currentChartRange);
              if (link.length)
                link.html(
                  iconHtml("chart_bar", "show_chart", icoSt) + " " + t("close"),
                );
            } else {
              container.style.display = "none";
              if (chartInstances[checkId]) {
                chartInstances[checkId].destroy();
                delete chartInstances[checkId];
              }
              if (link.length)
                link.html(
                  iconHtml("chart_bar", "show_chart", icoSt) + " " + t("chart"),
                );
            }
          }

          page.$el.on("click", ".check-card", function (ev) {
            if (ev.target.closest("a")) return;
            toggleChart(this.dataset.checkId);
          });

          page.$el.on("click", ".toggle-chart", function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            toggleChart(this.dataset.checkId);
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
            ev.stopPropagation();
            var checkId = this.dataset.checkId;
            app.preloader.show();
            fetch("/api/checks/" + checkId + "/run", { method: "POST" })
              .then(function () {
                showToast(t("check_run"));
                loadHostDetail(page, hostId);
                app.preloader.hide();
              })
              .catch(function () {
                app.preloader.hide();
                showToast(t("check_run_error"));
              });
          });
          page.$el.on("click", ".accept-hash", function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var checkId = this.dataset.checkId;
            app.preloader.show();
            fetch("/api/checks/" + checkId + "/accept-hash", {
              method: "POST",
            })
              .then(function () {
                showToast(t("hash_accepted"));
                loadHostDetail(page, hostId);
                app.preloader.hide();
              })
              .catch(function () {
                app.preloader.hide();
                showToast(t("hash_accept_error"));
              });
          });
          page.$el.on("click", ".edit-check", function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            app.views.main.router.navigate(
              "/checks/" + this.dataset.checkId + "/edit/",
            );
          });
          page.$el.on("click", ".delete-check", function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var checkId = this.dataset.checkId;
            app.dialog.confirm(
              t("delete_check_confirm"),
              t("delete_check_title"),
              function () {
                fetch("/api/checks/" + checkId, { method: "DELETE" }).then(
                  function () {
                    showToast(t("check_deleted"));
                    loadHostDetail(page, hostId);
                  },
                );
              },
            );
          });
          page.$el.on("click", ".swipeout-delete-check", function (ev) {
            ev.preventDefault();
            var checkId = this.dataset.checkId;
            var li = this.closest("li");
            app.dialog.confirm(
              t("delete_check_confirm"),
              t("delete_check_title"),
              function () {
                fetch("/api/checks/" + checkId, { method: "DELETE" }).then(
                  function () {
                    app.swipeout.delete(li);
                    showToast(t("check_deleted"));
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
          page.$el.find("#page-title").text(t("edit_host"));
          // i18n for host-edit template
          page.$el.find("[data-i18n]").each(function () {
            this.textContent = t(this.dataset.i18n);
          });
          page.$el.find("#host-name").attr("placeholder", t("ph_name"));
          page.$el.find("#host-address").attr("placeholder", t("ph_address"));
          page.$el
            .find("#host-description")
            .attr("placeholder", t("ph_optional"));
          page.$el.find("#host-topic").attr("placeholder", t("ph_topic"));

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
              app.dialog.alert(t("required_fields"));
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
                showToast(t("host_saved"));
                app.views.main.router.back();
              })
              .catch(function (err) {
                app.dialog.alert(t("error_prefix") + ": " + err.message);
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
          page.$el.find("#page-title").text(t("new_check"));
          // i18n for check-edit template
          page.$el.find("[data-i18n]").each(function () {
            this.textContent = t(this.dataset.i18n);
          });
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
                    throw new Error(d.error || t("save_error"));
                  });
                }
                showToast(t("check_created"));
                app.views.main.router.back();
              })
              .catch(function (err) {
                app.dialog.alert(t("error_prefix") + ": " + err.message);
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
          page.$el.find("#page-title").text(t("edit_check"));
          // i18n for check-edit template
          page.$el.find("[data-i18n]").each(function () {
            this.textContent = t(this.dataset.i18n);
          });

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
                    throw new Error(d.error || t("save_error"));
                  });
                }
                showToast(t("check_saved"));
                app.views.main.router.back();
              })
              .catch(function (err) {
                app.dialog.alert(t("error_prefix") + ": " + err.message);
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
          // i18n for settings template
          page.$el.find(".title").text(t("settings"));
          var blockTitles = page.$el.find(".block-title");
          blockTitles.eq(0).text(t("notifications"));
          blockTitles.eq(1).text(t("appearance"));
          blockTitles.eq(2).text(t("info"));
          page.$el.find("[data-i18n]").each(function () {
            this.textContent = t(this.dataset.i18n);
          });
          page.$el.find("#push-test-btn").text(t("send"));
          page.$el.find("#logout-btn").text(t("logout"));
          page.$el.find('.darkmode-btn[data-mode="auto"]').text(t("auto"));
          page.$el.find('.darkmode-btn[data-mode="off"]').text(t("off"));
          page.$el.find('.darkmode-btn[data-mode="on"]').text(t("on"));

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

          // Theme segmented control
          page.$el.find('.theme-btn[data-theme="auto"]').text(t("auto"));
          page.$el.find('.theme-btn[data-theme="ios"]').text(t("ios"));
          page.$el.find('.theme-btn[data-theme="md"]').text(t("android"));
          page.$el.find(".theme-btn").each(function () {
            if (this.dataset.theme === themePref) {
              this.classList.add("button-active");
            }
          });
          page.$el.find(".theme-btn").on("click", function () {
            var newTheme = this.dataset.theme;
            if (newTheme === themePref) return;
            page.$el.find(".theme-btn").removeClass("button-active");
            this.classList.add("button-active");
            themePref = newTheme;
            localStorage.setItem("themePreference", themePref);
            forceReload();
          });

          // Time format segmented control
          page.$el.find(".timeformat-btn").each(function () {
            if (this.dataset.format === timeFormatPref) {
              this.classList.add("button-active");
            }
          });
          page.$el.find(".timeformat-btn").on("click", function () {
            page.$el.find(".timeformat-btn").removeClass("button-active");
            this.classList.add("button-active");
            setTimeFormat(this.dataset.format);
          });

          // Language segmented control
          page.$el.find(".lang-btn").each(function () {
            if (this.dataset.lang === langPref) {
              this.classList.add("button-active");
            }
          });
          page.$el.find(".lang-btn").on("click", function () {
            page.$el.find(".lang-btn").removeClass("button-active");
            this.classList.add("button-active");
            setLang(this.dataset.lang);
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
                  data.runner_last_run
                    ? timeAgo(data.runner_last_run)
                    : t("never"),
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
                    app.dialog.alert(t("push_blocked"));
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
                            app.dialog.alert(t("push_failed"));
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
                btn.textContent = t("send");
                if (data.sent > 0) {
                  app.dialog.alert(
                    t("push_test_sent").replace("%d", data.sent),
                  );
                } else if (data.failed > 0) {
                  app.dialog.alert(
                    t("push_test_failed").replace("%d", data.failed),
                  );
                } else {
                  app.dialog.alert(t("push_no_subs"));
                }
              })
              .catch(function () {
                btn.disabled = false;
                btn.textContent = t("send");
                app.dialog.alert(t("push_test_error"));
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
