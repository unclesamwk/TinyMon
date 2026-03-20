# Dashboard Pro Redesign

## Goal

Transform TinyMon's frontend from a generic Framework7 template into a distinctive "Dashboard Pro" aesthetic — Linear/Vercel-inspired, data-dense, technically refined. Both dark and light themes get equal treatment. iOS and Android F7 themes remain supported.

## Aesthetic Direction

- **Typography**: JetBrains Mono for numbers, values, timestamps. DM Sans for UI labels and headings. Fonts self-hosted under `/assets/fonts/` to avoid external dependencies (aligns with TinyMon's "no external dependencies" philosophy, works in air-gapped/offline/PWA scenarios). Use `font-display: swap` to avoid FOIT.
- **Color system**: High-contrast palette. Dark theme: near-black backgrounds (#0c0c0c, #141414) with sharp 1px borders (#1e1e1e). Light theme: cool grays (#f4f4f5, #e8e8ea) with sharp borders (#d4d4d8), not soft iOS white.
- **Status colors**: Green #22c55e, Yellow #eab308, Red #ef4444, Gray #555 (dark) / #a1a1aa (light).
- **Cards**: No box-shadows. 1px solid borders, subtle background separation. Square-ish corners (8-10px radius).
- **Spacing**: Compact. Dense information without feeling cramped.
- **Animations**: Minimal, functional. No decorative motion. Accordion open/close stays as-is.

## Information Density Features

### 1. Status Summary Tiles

Replace the 4 loose numbers with individual bordered cards in a 4-column grid. Each card has: monospace number, uppercase label, subtle background tint matching the status color. On narrow viewports (<400px), collapses to 2x2 grid.

### 2. Runner Timestamp in Summary Bar

Move runner info from a separate line into a small badge/row directly below the summary tiles. Monospace font, dimmed color.

### 3. Sparklines in Check Rows

Each check in the dashboard host-accordion and host-detail page shows a mini bar chart of the last 20 `value` results. Pure CSS inline elements — no Chart.js dependency for these (Chart.js remains for the full check history charts). Bars colored by status of each result. Rendered inline, right-aligned before the status badge.

**Normalization**: Per-check min/max of the 20 values. If min == max, render all bars at 50% height. Null values rendered as zero-height bars with a 1px minimum. Height formula: `((value - min) / (max - min)) * 100%`, clamped to 10%-100%.

**Responsive**: On viewports <480px, sparklines are hidden to preserve readability.

**Data source**: Extend `/api/dashboard` response to include `recent_values` array per check (last 20 results: `{value, status}`). Fetched via a single batch query (see Backend Changes).

### 4. Response Time Inline

For checks that have a numeric `value` in their last result (HTTP, ping, port, certificate), show the formatted value (e.g. "142ms", "85d") as a monospace badge in the check row, visible without expanding.

### 5. Uptime Indicator (24h)

A row of 24 small colored blocks per check showing hourly status over the last 24 hours. Green = all OK that hour, red = any critical, yellow = any warning, gray = unknown/no data. Displayed in check rows on the host-detail page (not dashboard accordion — too dense).

**Data source**: Extend `/api/hosts/{id}` response to include `uptime_24h` array per check (24 entries, one per hour, each being the worst status in that hour).

## Backend Changes

### Dashboard API Extension — Sparklines

**`GET /api/dashboard`** — add to each check object:
```json
{
  "recent_values": [
    {"value": 142.5, "status": "ok"},
    {"value": 155.2, "status": "ok"}
  ]
}
```
- Last 20 results per check, ordered chronologically (oldest first)
- **Single batch query** using `ROW_NUMBER()` window function (SQLite 3.25+):
```sql
SELECT check_id, value, status FROM (
    SELECT check_id, value, status,
           ROW_NUMBER() OVER (PARTITION BY check_id ORDER BY checked_at DESC) AS rn
    FROM check_results
    WHERE check_id IN (SELECT id FROM checks WHERE enabled = 1)
) WHERE rn <= 20
ORDER BY check_id, rn DESC
```
- Results grouped by check_id in PHP, then attached to each check in the response
- Performance: One additional query regardless of check count. Uses existing `(check_id, checked_at DESC)` index.

### Host Detail API Extension — Uptime 24h

**`GET /api/hosts/{id}`** — add to each check object:
```json
{
  "uptime_24h": ["ok", "ok", "critical", "ok", "unknown", ...]
}
```
- 24 entries, index 0 = 23 hours ago, index 23 = current hour
- Per hour: worst status of any result in that hour window
- Status priority (worst wins): critical > warning > unknown > ok
- **Single batch query** for all checks of the host:
```sql
SELECT check_id,
       strftime('%Y-%m-%d %H:00:00', checked_at) AS hour_bucket,
       CASE
         WHEN SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) > 0 THEN 'critical'
         WHEN SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) > 0 THEN 'warning'
         WHEN SUM(CASE WHEN status = 'unknown' THEN 1 ELSE 0 END) > 0 THEN 'unknown'
         ELSE 'ok'
       END AS worst_status
FROM check_results
WHERE check_id IN (SELECT id FROM checks WHERE host_id = ? AND enabled = 1)
  AND checked_at >= datetime('now', '-24 hours')
GROUP BY check_id, hour_bucket
ORDER BY check_id, hour_bucket
```
- In PHP: for each check, generate a 24-slot array (one per hour for the last 24h). Fill slots from query results; any hour without data defaults to `"unknown"`.

## Frontend Changes

### Files Modified

1. **`app/views/backend/index.php`** — CSS additions in `<style>` block, `@font-face` declarations for self-hosted fonts
2. **`public/assets/js/backend-app.js`** — Rendering changes for all new components
3. **`public/assets/fonts/`** — JetBrains Mono (woff2) + DM Sans (woff2), self-hosted
4. **`app/controllers/api/DashboardController.php`** — Add sparkline data
5. **`app/controllers/api/HostController.php`** — Add uptime data (or CheckController, wherever the host detail endpoint lives)

### CSS Strategy

All new styles use CSS custom properties on `:root`, with dark mode overrides using the existing F7 compound selectors. The existing F7 dark mode variables are extended, not replaced.

```css
:root {
  --tm-bg-primary: #f4f4f5;
  --tm-bg-card: #fff;
  --tm-border: #d4d4d8;
  --tm-text-primary: #18181b;
  --tm-text-secondary: #71717a;
  --tm-text-mono: #52525b;
  --tm-font-mono: 'JetBrains Mono', monospace;
  --tm-font-ui: 'DM Sans', sans-serif;
  --tm-ok: #22c55e;
  --tm-warning: #eab308;
  --tm-critical: #ef4444;
  --tm-unknown: #a1a1aa;
}

/* Dark mode — matches existing F7 pattern */
.ios .dark, .ios.dark,
.md .dark, .md.dark {
  --tm-bg-primary: #0c0c0c;
  --tm-bg-card: #141414;
  --tm-border: #1e1e1e;
  --tm-text-primary: #e0e0e0;
  --tm-text-secondary: #555;
  --tm-text-mono: #888;
  --tm-unknown: #555;
}
```

### Component Changes

**Login page**: Card with border on themed background. Monospace version number.

**Summary bar**: 4-column grid of status tiles. Runner timestamp as a small row below. Grid collapses to 2x2 on narrow viewports.

**Topic groups**: Replace box-shadow with 1px border. Slightly darker background for headers. Status border-left remains.

**Host rows**: Add sparkline element and inline response time. Tighter spacing.

**Check rows (accordion)**: Add sparkline, response time badge. Monospace values.

**Host detail page**: Add uptime-24h blocks below each check card. Sparklines inline.

**Settings page**: Same card/border treatment. Monospace for values.

### Sparkline Rendering

Pure inline HTML:
```html
<span class="sparkline" aria-label="Last 20 check values">
  <span class="bar" style="height:60%;background:var(--tm-ok)"></span>
  <span class="bar" style="height:80%;background:var(--tm-ok)"></span>
  <span class="bar" style="height:40%;background:var(--tm-critical)"></span>
  ...
</span>
```

CSS:
```css
.sparkline { display:inline-flex; align-items:flex-end; gap:1px; height:16px; }
.sparkline .bar { width:2px; border-radius:0.5px; min-height:1px; }
@media (max-width: 479px) { .sparkline { display: none; } }
```

### Uptime Blocks Rendering

```html
<div class="uptime-24h" aria-label="24 hour uptime">
  <span class="uptime-block" style="background:var(--tm-ok)" title="14:00 — OK"></span>
  <span class="uptime-block" style="background:var(--tm-critical)" title="15:00 — Critical"></span>
  ...
</div>
```

CSS:
```css
.uptime-24h { display:flex; gap:1px; height:8px; }
.uptime-24h .uptime-block { flex:1; border-radius:1px; min-width:3px; }
```

## What Does NOT Change

- Framework7 as the framework (router, accordion, swipeout, dialog, toast)
- i18n system (de/en translation objects, `t()` function)
- Functional logic (filter, accordion persistence, localStorage)
- API structure (only additive — existing fields untouched)
- Service worker, PWA manifest
- Push API, alerts, check runner logic
- Chart.js for full check history charts (sparklines are separate pure-CSS elements)

## Rollout

Single deployment. All changes are CSS + JS + minor PHP API additions + self-hosted font files. No database schema changes. No migration needed. Backward-compatible — the new `recent_values` and `uptime_24h` fields are additive.
