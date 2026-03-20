# Dashboard Pro Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform TinyMon's frontend into a "Dashboard Pro" aesthetic (Linear/Vercel-inspired) with increased information density (sparklines, uptime indicators, inline values).

**Architecture:** CSS-only visual overhaul via custom properties on `:root` with F7 dark-mode compound selectors. Two backend API extensions (batch queries) for sparkline and uptime data. Self-hosted fonts. All changes additive and backward-compatible.

**Tech Stack:** PHP 8.4 / Flight, SQLite (ROW_NUMBER window function), Framework7 9, vanilla JS, CSS custom properties, self-hosted JetBrains Mono + DM Sans.

**Spec:** `docs/superpowers/specs/2026-03-20-dashboard-pro-redesign-design.md`

---

## File Structure

| Action | File | Purpose |
|--------|------|---------|
| Create | `public/assets/fonts/JetBrainsMono-Regular.woff2` | Self-hosted monospace font |
| Create | `public/assets/fonts/JetBrainsMono-Medium.woff2` | Self-hosted monospace font (medium weight) |
| Create | `public/assets/fonts/DMSans-Regular.woff2` | Self-hosted UI font |
| Create | `public/assets/fonts/DMSans-Medium.woff2` | Self-hosted UI font (medium weight) |
| Create | `public/assets/fonts/DMSans-SemiBold.woff2` | Self-hosted UI font (semibold weight) |
| Create | `public/assets/fonts/DMSans-Bold.woff2` | Self-hosted UI font (bold weight) |
| Modify | `app/views/backend/index.php` | CSS overhaul: @font-face, custom properties, all component styles |
| Modify | `app/views/backend/login.php` | Dashboard Pro styling for login page |
| Modify | `app/controllers/api/DashboardController.php` | Add `recent_values` batch query for sparklines |
| Modify | `app/controllers/api/HostController.php` | Add `uptime_24h` batch query for uptime blocks |
| Modify | `public/assets/js/backend-app.js` | Rendering: sparklines, uptime blocks, inline values, new summary tiles, CSS class updates |

---

## Task 1: Self-Host Fonts

**Files:**
- Create: `public/assets/fonts/*.woff2` (6 files)
- Modify: `app/views/backend/index.php:36-37` (add @font-face before existing `<link>` tags)

- [ ] **Step 1: Download font files**

```bash
# JetBrains Mono
curl -L "https://github.com/JetBrains/JetBrainsMono/releases/download/v2.304/JetBrainsMono-2.304.zip" -o /tmp/jbmono.zip
unzip -o /tmp/jbmono.zip -d /tmp/jbmono
cp /tmp/jbmono/fonts/webfonts/JetBrainsMono-Regular.woff2 public/assets/fonts/
cp /tmp/jbmono/fonts/webfonts/JetBrainsMono-Medium.woff2 public/assets/fonts/

# DM Sans from google-webfonts-helper or fontsource
curl -L "https://github.com/fontsource/font-files/raw/main/fonts/google/dm-sans/latin-400-normal.woff2" -o public/assets/fonts/DMSans-Regular.woff2
curl -L "https://github.com/fontsource/font-files/raw/main/fonts/google/dm-sans/latin-500-normal.woff2" -o public/assets/fonts/DMSans-Medium.woff2
curl -L "https://github.com/fontsource/font-files/raw/main/fonts/google/dm-sans/latin-600-normal.woff2" -o public/assets/fonts/DMSans-SemiBold.woff2
curl -L "https://github.com/fontsource/font-files/raw/main/fonts/google/dm-sans/latin-700-normal.woff2" -o public/assets/fonts/DMSans-Bold.woff2
```

- [ ] **Step 2: Add @font-face declarations to index.php**

In `app/views/backend/index.php`, add inside the `<style>` block (before existing CSS, after line 39):

```css
@font-face { font-family: 'JetBrains Mono'; src: url('/assets/fonts/JetBrainsMono-Regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
@font-face { font-family: 'JetBrains Mono'; src: url('/assets/fonts/JetBrainsMono-Medium.woff2') format('woff2'); font-weight: 500; font-style: normal; font-display: swap; }
@font-face { font-family: 'DM Sans'; src: url('/assets/fonts/DMSans-Regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
@font-face { font-family: 'DM Sans'; src: url('/assets/fonts/DMSans-Medium.woff2') format('woff2'); font-weight: 500; font-style: normal; font-display: swap; }
@font-face { font-family: 'DM Sans'; src: url('/assets/fonts/DMSans-SemiBold.woff2') format('woff2'); font-weight: 600; font-style: normal; font-display: swap; }
@font-face { font-family: 'DM Sans'; src: url('/assets/fonts/DMSans-Bold.woff2') format('woff2'); font-weight: 700; font-style: normal; font-display: swap; }
```

- [ ] **Step 3: Verify fonts load**

Open `http://localhost:8001/backend` in browser, check DevTools Network tab for woff2 files loading with 200 status.

- [ ] **Step 4: Commit**

```bash
git add public/assets/fonts/ app/views/backend/index.php
git commit -m "feat: self-host JetBrains Mono + DM Sans fonts"
```

---

## Task 2: CSS Custom Properties & Base Theme

**Files:**
- Modify: `app/views/backend/index.php:39-107` (replace entire `<style>` block content)

- [ ] **Step 1: Add CSS custom properties and rewrite base styles**

Replace the `<style>` block in `index.php` (lines 39-107). Keep all existing functionality (topic-group, host-checks-sublist, dark mode, status-dots, accordion) but restyle with Dashboard Pro aesthetic. The new CSS must include:

1. `:root` variables (light theme):
   - `--tm-bg-primary: #f4f4f5`, `--tm-bg-card: #fff`, `--tm-bg-card-header: #fafafa`
   - `--tm-border: #d4d4d8`, `--tm-text-primary: #18181b`, `--tm-text-secondary: #71717a`, `--tm-text-mono: #52525b`
   - `--tm-font-mono: 'JetBrains Mono', monospace`, `--tm-font-ui: 'DM Sans', sans-serif`
   - `--tm-ok: #22c55e`, `--tm-warning: #eab308`, `--tm-critical: #ef4444`, `--tm-unknown: #a1a1aa`

2. Dark mode override using `.ios .dark, .ios.dark, .md .dark, .md.dark` compound selectors:
   - `--tm-bg-primary: #0c0c0c`, `--tm-bg-card: #141414`, `--tm-bg-card-header: #1a1a1a`
   - `--tm-border: #1e1e1e`, `--tm-text-primary: #e0e0e0`, `--tm-text-secondary: #555`, `--tm-text-mono: #888`
   - `--tm-unknown: #555`
   - Override F7 vars: `--f7-page-bg-color: #0c0c0c`, `--f7-bars-bg-color: #0c0c0c`, `--f7-navbar-bg-color: var(--tm-bg-card)`, etc.

3. Restyle `.topic-group`: `border: 1px solid var(--tm-border)`, `border-radius: 10px`, `background: var(--tm-bg-card)`, no box-shadow.

4. Restyle `.topic-header`: `background: var(--tm-bg-card-header)`.

5. Restyle `.host-checks-sublist`: border-left color uses `var(--tm-border)`, background uses `var(--tm-bg-card)`.

6. `.status-dots`: keep layout, apply `font-family: var(--tm-font-mono)`.

7. New: `.sparkline` styles, `.uptime-24h` styles, `.summary-tiles` grid, `.value-badge` for inline values.

8. New: body/page background override: `--f7-page-bg-color: var(--tm-bg-primary)`.

9. Responsive: `@media (max-width: 479px) { .sparkline { display: none; } }` and summary tiles 2x2 at `max-width: 399px`.

- [ ] **Step 2: Verify both themes**

Open browser, toggle dark mode in settings. Both should use the new colors/borders. Verify no broken F7 components.

- [ ] **Step 3: Commit**

```bash
git add app/views/backend/index.php
git commit -m "feat: Dashboard Pro CSS theme with custom properties"
```

---

## Task 3: Login Page Redesign

**Files:**
- Modify: `app/views/backend/login.php:24-42` (replace `<style>` content)

- [ ] **Step 1: Restyle login page**

Replace the login page styles (lines 24-42) with Dashboard Pro aesthetic:

- Body: `background: var(--tm-bg-primary)` — but since login.php is standalone (no F7), use direct values with `prefers-color-scheme` media query
- Card: `background: #fff`, `border: 1px solid #d4d4d8`, `border-radius: 10px`, no box-shadow
- Input: `border: 1px solid #d4d4d8`, `border-radius: 6px`, `font-family: 'DM Sans', sans-serif`
- Button: `background: #22c55e` (green, matching OK status — monitoring tool = green = good)
- Version: `font-family: 'JetBrains Mono', monospace`, `font-size: 0.65rem`
- Dark: `body { background: #0c0c0c }`, card `background: #141414`, `border-color: #1e1e1e`, input `background: #1a1a1a`, `border-color: #1e1e1e`

- [ ] **Step 2: Add @font-face to login.php**

Login.php is standalone — copy the same @font-face declarations from index.php into login.php's `<style>` block.

- [ ] **Step 3: Verify login page in both themes**

Check light and dark mode. Test login flow still works.

- [ ] **Step 4: Commit**

```bash
git add app/views/backend/login.php
git commit -m "feat: Dashboard Pro login page"
```

---

## Task 4: Backend — Sparkline Data (Dashboard API)

**Files:**
- Modify: `app/controllers/api/DashboardController.php:135-217` (add batch query + attach to checks)

- [ ] **Step 1: Add batch sparkline query**

After the existing checks query (line 154), add:

```php
// Sparkline data: last 20 results per check (batch)
$sparkRows = $db->fetchAll(
    "SELECT check_id, value, status FROM (
        SELECT check_id, value, status,
               ROW_NUMBER() OVER (PARTITION BY check_id ORDER BY checked_at DESC) AS rn
        FROM check_results
        WHERE check_id IN (SELECT id FROM checks WHERE enabled = 1)
    ) WHERE rn <= 20
    ORDER BY check_id, rn DESC"
);

$sparkByCheck = [];
foreach ($sparkRows as $sr) {
    $sparkByCheck[$sr['check_id']][] = [
        'value' => $sr['value'],
        'status' => $sr['status'],
    ];
}
```

- [ ] **Step 2: Attach sparkline data to each check**

In the check-grouping loop (around line 176), add after `$row['last_result'] = $lastResult;`:

```php
$row['recent_values'] = $sparkByCheck[$row['id']] ?? [];
```

- [ ] **Step 3: Verify API response**

```bash
# Login and check response
curl -s -c /tmp/tm -X POST http://localhost:8001/backend/login \
  -d "password=minimon&csrf_token=$(curl -s -c /tmp/tm http://localhost:8001/backend/login | grep -o 'value="[^"]*"' | head -1 | cut -d'"' -f2)" \
  -L > /dev/null
curl -s -b /tmp/tm http://localhost:8001/api/dashboard | python3 -c "
import json,sys
d=json.load(sys.stdin)
c=d['hosts'][0]['checks'][0]
print('recent_values count:', len(c.get('recent_values',[])))
print('sample:', c.get('recent_values',[])[0] if c.get('recent_values') else 'none')
"
```

Expected: `recent_values count: 20` (or less if fewer results exist), each with `value` and `status`.

- [ ] **Step 4: Commit**

```bash
git add app/controllers/api/DashboardController.php
git commit -m "feat: add sparkline data (recent_values) to dashboard API"
```

---

## Task 5: Backend — Uptime 24h Data (Host Detail API)

**Files:**
- Modify: `app/controllers/api/HostController.php:117-140` (add uptime query + attach)

- [ ] **Step 1: Add uptime batch query**

In `HostController::get()`, after the existing check loop (line 136), add uptime data. Replace lines 126-136 with:

```php
$checks = $db->fetchAll(
    "SELECT * FROM checks WHERE host_id = ? ORDER BY type ASC",
    [$id],
);

// Last result per check
foreach ($checks as &$check) {
    $lastResult = $db->fetchRow(
        "SELECT * FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 1",
        [$check["id"]],
    );
    $check["last_result"] = $lastResult;
}

// Uptime 24h: worst status per hour per check (batch)
$checkIds = array_column($checks, 'id');
if (!empty($checkIds)) {
    $placeholders = implode(',', array_fill(0, count($checkIds), '?'));
    $uptimeRows = $db->fetchAll(
        "SELECT check_id,
                strftime('%Y-%m-%d %H:00:00', checked_at) AS hour_bucket,
                CASE
                  WHEN SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) > 0 THEN 'critical'
                  WHEN SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) > 0 THEN 'warning'
                  WHEN SUM(CASE WHEN status = 'unknown' THEN 1 ELSE 0 END) > 0 THEN 'unknown'
                  ELSE 'ok'
                END AS worst_status
         FROM check_results
         WHERE check_id IN ($placeholders)
           AND checked_at >= datetime('now', '-24 hours')
         GROUP BY check_id, hour_bucket
         ORDER BY check_id, hour_bucket",
        $checkIds
    );

    // Build lookup: check_id -> hour_bucket -> status
    $uptimeByCheck = [];
    foreach ($uptimeRows as $ur) {
        $uptimeByCheck[$ur['check_id']][$ur['hour_bucket']] = $ur['worst_status'];
    }

    // Generate 24-slot arrays
    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    $hours = [];
    for ($i = 23; $i >= 0; $i--) {
        $h = (clone $now)->modify("-{$i} hours");
        $hours[] = $h->format('Y-m-d H:00:00');
    }

    foreach ($checks as &$check) {
        $checkUptime = $uptimeByCheck[$check['id']] ?? [];
        $uptime24h = [];
        foreach ($hours as $hour) {
            $uptime24h[] = $checkUptime[$hour] ?? 'unknown';
        }
        $check['uptime_24h'] = $uptime24h;
    }
    unset($check);
}

// Also add sparkline data for host detail
$sparkRows = $db->fetchAll(
    "SELECT check_id, value, status FROM (
        SELECT check_id, value, status,
               ROW_NUMBER() OVER (PARTITION BY check_id ORDER BY checked_at DESC) AS rn
        FROM check_results
        WHERE check_id IN ($placeholders)
    ) WHERE rn <= 20
    ORDER BY check_id, rn DESC",
    $checkIds
);
$sparkByCheck = [];
foreach ($sparkRows as $sr) {
    $sparkByCheck[$sr['check_id']][] = [
        'value' => $sr['value'],
        'status' => $sr['status'],
    ];
}
foreach ($checks as &$check) {
    $check['recent_values'] = $sparkByCheck[$check['id']] ?? [];
}
unset($check);
```

- [ ] **Step 2: Verify host detail API**

```bash
curl -s -b /tmp/tm http://localhost:8001/api/hosts/1 | python3 -c "
import json,sys
d=json.load(sys.stdin)
c=d['checks'][0]
print('uptime_24h length:', len(c.get('uptime_24h',[])))
print('uptime_24h sample:', c.get('uptime_24h',[])[0:5] if c.get('uptime_24h') else 'none')
print('recent_values count:', len(c.get('recent_values',[])))
"
```

Expected: `uptime_24h length: 24`, `recent_values count: 20` (or less).

- [ ] **Step 3: Commit**

```bash
git add app/controllers/api/HostController.php
git commit -m "feat: add uptime_24h and sparkline data to host detail API"
```

---

## Task 6: Frontend — Summary Tiles + Runner Badge

**Files:**
- Modify: `public/assets/js/backend-app.js:646-710` (replace filterBadge and summary rendering in renderDashboard)

- [ ] **Step 1: Replace filterBadge function and summary HTML**

In `renderDashboard()`, replace the `filterBadge` function (lines 646-669) and the summary HTML building (lines 671-678) with:

```javascript
function filterBadge(status, count, color, label) {
    var active = dashboardFilter === status;
    var opacity = dashboardFilter && !active ? "0.4" : "1";
    var activeBorder = active ? "var(--tm-" + status + ", " + color + ")" : "var(--tm-border, #d4d4d8)";
    return (
      '<div class="status-filter summary-tile" data-status="' + status +
      '" style="cursor:pointer; opacity:' + opacity +
      '; border-color:' + activeBorder + ';">' +
      '<div style="font-family:var(--tm-font-mono, monospace); font-size:1.8rem; font-weight:500; color:' + color +
      '; letter-spacing:-0.03em;">' + (count || 0) + '</div>' +
      '<div style="font-family:var(--tm-font-mono, monospace); font-size:0.6rem; color:var(--tm-text-secondary, gray); text-transform:uppercase; letter-spacing:0.06em;">' +
      label + '</div></div>'
    );
}
```

Replace the summary container (lines 671-677) with:

```javascript
var s = '<div class="summary-tiles">';
s += filterBadge("ok", summary.ok, "var(--tm-ok, #22c55e)", "OK");
s += filterBadge("warning", summary.warning, "var(--tm-warning, #eab308)", "Warning");
s += filterBadge("critical", summary.critical, "var(--tm-critical, #ef4444)", "Critical");
s += filterBadge("unknown", summary.unknown, "var(--tm-unknown, #a1a1aa)", "Unknown");
s += '</div>';
```

- [ ] **Step 2: Replace runner timestamp rendering**

Replace the runner HTML (lines 704-709) with:

```javascript
var runnerEl = page.$el.find("#runner-status");
if (data.runner_last_run) {
    runnerEl.html('<span style="font-family:var(--tm-font-mono, monospace); font-size:0.7rem; color:var(--tm-text-secondary, gray);">Runner: ' + timeAgo(data.runner_last_run) + '</span>');
} else {
    runnerEl.html('<span style="font-family:var(--tm-font-mono, monospace); font-size:0.7rem; color:var(--tm-text-secondary, gray);">Runner: ' + t("runner_never") + '</span>');
}
```

- [ ] **Step 3: Verify dashboard summary tiles**

Open browser, check that 4 tiles render in a grid with borders and monospace numbers. Toggle filter by clicking. Verify both themes.

- [ ] **Step 4: Commit**

```bash
git add public/assets/js/backend-app.js
git commit -m "feat: Dashboard Pro summary tiles and runner badge"
```

---

## Task 7: Frontend — Sparklines + Inline Values in Dashboard

**Files:**
- Modify: `public/assets/js/backend-app.js` — add `renderSparkline()` and `renderValueBadge()` helpers, update `renderHostListItem()` (lines 550-634)

- [ ] **Step 1: Add sparkline renderer helper**

Add before `renderHostListItem` (around line 549):

```javascript
function renderSparkline(recentValues) {
  if (!recentValues || recentValues.length < 2) return '';
  var vals = recentValues.map(function(r) { return r.value !== null ? parseFloat(r.value) : null; });
  var valid = vals.filter(function(v) { return v !== null; });
  if (valid.length < 2) return '';
  var min = Math.min.apply(null, valid);
  var max = Math.max.apply(null, valid);
  var html = '<span class="sparkline" aria-label="Last ' + recentValues.length + ' values">';
  recentValues.forEach(function(r) {
    var v = r.value !== null ? parseFloat(r.value) : null;
    var pct = 10;
    if (v !== null && max > min) {
      pct = Math.max(10, Math.min(100, ((v - min) / (max - min)) * 90 + 10));
    } else if (v !== null) {
      pct = 50;
    }
    var color = 'var(--tm-' + (r.status || 'unknown') + ', #888)';
    html += '<span class="bar" style="height:' + pct + '%;background:' + color + ';"></span>';
  });
  html += '</span>';
  return html;
}

function renderValueBadge(check) {
  var lr = check.last_result;
  if (!lr || lr.value === null || lr.value === undefined) return '';
  var val = parseFloat(lr.value);
  var type = check.type;
  var text = '';
  if (type === 'ping' || type === 'http' || type === 'port') {
    text = val < 1000 ? Math.round(val) + 'ms' : (val / 1000).toFixed(1) + 's';
  } else if (type === 'certificate') {
    text = Math.round(val) + 'd';
  } else if (type === 'disk' || type === 'memory') {
    text = val.toFixed(1) + '%';
  } else if (type === 'load') {
    text = val.toFixed(2);
  } else {
    return '';
  }
  return '<span class="value-badge">' + text + '</span>';
}
```

- [ ] **Step 2: Update renderHostListItem to include sparklines and value badges**

In `renderHostListItem`, update the check row rendering (around lines 615-622). After the check title div, before the status badge, add sparkline and value badge:

Replace the inner check list item rendering (lines 615-622) — the section that builds each `<li>` inside the host accordion — to include:

```javascript
li += '<div class="item-after" style="display:flex; align-items:center; gap:6px;">';
li += renderSparkline(c.recent_values);
li += renderValueBadge(c);
li += statusBadge(st);
li += '</div></div>';
```

(Replacing the existing `'<div class="item-after">' + statusBadge(st) + '</div></div>'`)

- [ ] **Step 3: Verify sparklines in dashboard**

Open browser. Expand a host accordion. Each check should show small colored bars and a value badge. Verify responsive: narrow window should hide sparklines.

- [ ] **Step 4: Commit**

```bash
git add public/assets/js/backend-app.js
git commit -m "feat: sparklines and inline value badges in dashboard"
```

---

## Task 8: Frontend — Sparklines + Uptime in Host Detail

**Files:**
- Modify: `public/assets/js/backend-app.js:1045-1229` (update loadHostDetail rendering)

- [ ] **Step 1: Add uptime renderer helper**

Add near the other helpers (after renderValueBadge):

```javascript
function renderUptime24h(uptime24h) {
  if (!uptime24h || uptime24h.length === 0) return '';
  var now = new Date();
  var html = '<div class="uptime-24h" aria-label="24 hour uptime">';
  uptime24h.forEach(function(status, i) {
    var hour = new Date(now.getTime() - (23 - i) * 3600000);
    var timeStr = hour.getHours().toString().padStart(2, '0') + ':00';
    var color = 'var(--tm-' + (status || 'unknown') + ', #888)';
    html += '<span class="uptime-block" style="background:' + color + ';" title="' + timeStr + ' — ' + status + '"></span>';
  });
  html += '</div>';
  return html;
}
```

- [ ] **Step 2: Update host detail check rendering**

In `loadHostDetail`, in the check card rendering loop (around line 1123-1127), after the item-after div with statusBadge, add sparkline and value badge to each check row. Also add uptime blocks after the button row.

After the existing button row div (line ~1198, the div with toggle-chart/run/edit/delete buttons), add:

```javascript
html += '<div style="padding:0 1rem 0.5rem;">' + renderUptime24h(c.uptime_24h) + '</div>';
```

Also update the check item-after in host detail to include sparkline + value (same pattern as dashboard):

```javascript
html += '<div class="item-after" style="display:flex; align-items:center; gap:6px;">';
html += renderSparkline(c.recent_values);
html += renderValueBadge(c);
html += statusBadge(st);
html += '</div></div>';
```

- [ ] **Step 3: Verify host detail page**

Navigate to a host detail. Check that:
- Each check shows sparkline + value badge + status icon
- Uptime 24h blocks appear below each check
- Hover on uptime blocks shows time tooltip

- [ ] **Step 4: Commit**

```bash
git add public/assets/js/backend-app.js
git commit -m "feat: sparklines and uptime-24h blocks in host detail"
```

---

## Task 9: Frontend — Typography & Component Styling

**Files:**
- Modify: `public/assets/js/backend-app.js` — update inline styles throughout to use `var(--tm-font-ui)` and `var(--tm-font-mono)` where appropriate

- [ ] **Step 1: Update statusBadge, renderStatusDots, timeAgo displays**

Apply monospace font to:
- `renderStatusDots` (line 540-548): add `font-family:var(--tm-font-mono, monospace)` to the container
- All `timeAgo()` output locations: wrap in monospace span
- All numeric values in host/check rows: apply monospace font

Update `renderHostListItem` — host count and after text:
```javascript
'<div class="item-after" style="color:var(--tm-text-secondary, gray); font-size:0.8rem; font-family:var(--tm-font-mono, monospace);">'
```

- [ ] **Step 2: Update topic group headers**

In `renderTree` (around line 856), apply DM Sans to topic name and monospace to host count:

```javascript
'<div class="item-title" style="font-weight:600; font-size:' +
(depth === 0 ? '1rem' : '0.9rem') +
'; font-family:var(--tm-font-ui, sans-serif);">'
```

- [ ] **Step 3: Update settings page values**

In the settings route rendering (around line 2332+), ensure runner time, version, host/check counts use monospace font.

- [ ] **Step 4: Verify typography**

Check all pages: dashboard, host detail, settings. Numbers should be monospace, labels should be DM Sans.

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/backend-app.js
git commit -m "feat: Dashboard Pro typography — monospace values, DM Sans labels"
```

---

## Task 10: Visual Polish & Final Verification

**Files:**
- Modify: `app/views/backend/index.php` (CSS tweaks)
- Modify: `public/assets/js/backend-app.js` (minor fixes)

- [ ] **Step 1: Test all pages in both themes**

Walk through:
1. Login page (light + dark)
2. Dashboard with data (light + dark)
3. Dashboard with filter active
4. Host detail page with charts
5. Check history page
6. Alert log page
7. Settings page
8. Host/check create/edit forms

- [ ] **Step 2: Test responsive layout**

Use browser DevTools to test at:
- 375px wide (iPhone SE) — sparklines hidden, tiles 2x2
- 414px wide (iPhone Pro) — sparklines hidden, tiles 4-col
- 768px wide (iPad) — full layout
- 1024px+ (desktop) — full layout

- [ ] **Step 3: Fix any visual issues found**

Adjust CSS variables, spacing, font sizes as needed.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: Dashboard Pro redesign — visual polish and responsive fixes"
```

---

## Task 11: Cleanup

- [ ] **Step 1: Remove temporary files**

```bash
rm -rf screenshots/
```

- [ ] **Step 2: Verify PHP syntax**

```bash
docker compose exec app sh -c "find app public -name '*.php' -print0 | xargs -0 -n1 php -l" 2>&1 | grep -v 'No syntax errors'
```

Expected: No output (all files pass).

- [ ] **Step 3: Final commit if any cleanup needed**

```bash
git add -A
git commit -m "chore: cleanup temporary files from redesign"
```
