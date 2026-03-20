# Dashboard Pro Redesign

## Goal

Transform TinyMon's frontend from a generic Framework7 template into a distinctive "Dashboard Pro" aesthetic — Linear/Vercel-inspired, data-dense, technically refined. Both dark and light themes get equal treatment. iOS and Android F7 themes remain supported.

## Aesthetic Direction

- **Typography**: JetBrains Mono for numbers, values, timestamps. DM Sans for UI labels and headings.
- **Color system**: High-contrast palette. Dark theme: near-black backgrounds (#0c0c0c, #141414) with sharp 1px borders (#1e1e1e). Light theme: cool grays (#f4f4f5, #e8e8ea) with sharp borders (#d4d4d8), not soft iOS white.
- **Status colors**: Green #22c55e, Yellow #eab308, Red #ef4444, Gray #555 (dark) / #a1a1aa (light).
- **Cards**: No box-shadows. 1px solid borders, subtle background separation. Square-ish corners (8-10px radius).
- **Spacing**: Compact. Dense information without feeling cramped.
- **Animations**: Minimal, functional. No decorative motion. Accordion open/close stays as-is.

## Information Density Features

### 1. Status Summary as Kacheln

Replace the 4 loose numbers with individual bordered cards in a grid. Each card has: monospace number, uppercase label, subtle background tint matching the status color.

### 2. Runner Timestamp in Summary Bar

Move runner info from a separate line into a small badge/row directly below or alongside the summary kacheln. Monospace font, dimmed color.

### 3. Sparklines in Check Rows

Each check in the dashboard host-accordion and host-detail page shows a mini bar chart of the last 20 `value` results. Pure CSS/inline SVG — no Chart.js dependency for these. Bars colored by status of each result. Rendered inline, right-aligned before the status badge.

**Data source**: Extend `/api/dashboard` response to include `recent_values` array per check (last 20 results: `{value, status}`). This avoids N+1 requests.

### 4. Response Time Inline

For checks that have a numeric `value` in their last result (HTTP, ping, port, certificate), show the formatted value (e.g. "142ms", "85 days") as a monospace badge in the check row, visible without expanding.

### 5. Uptime Indicator (24h)

A row of ~24 small colored blocks per check showing hourly status over the last 24 hours. Green = all OK that hour, red = any critical, yellow = any warning, gray = unknown/no data. Displayed in check rows on the host-detail page (not dashboard accordion — too dense).

**Data source**: Extend `/api/hosts/{id}` response to include `uptime_24h` array per check (24 entries, one per hour, each being the worst status in that hour).

## Backend Changes

### Dashboard API Extension

**`GET /api/dashboard`** — add to each check object:
```json
{
  "recent_values": [
    {"value": 142.5, "status": "ok"},
    {"value": 155.2, "status": "ok"},
    ...
  ]
}
```
- Last 20 results per check, ordered chronologically (oldest first)
- Query: `SELECT value, status FROM check_results WHERE check_id = ? ORDER BY checked_at DESC LIMIT 20`, then reverse
- Performance: Runs inside the existing dashboard query loop. With the `(check_id, checked_at DESC)` index this is fast.

### Host Detail API Extension

**`GET /api/hosts/{id}`** — add to each check object:
```json
{
  "uptime_24h": ["ok", "ok", "critical", "ok", ...]
}
```
- 24 entries, index 0 = 23 hours ago, index 23 = current hour
- Per hour: worst status of any result in that hour window
- Query: Single query per check grouping by hour

## Frontend Changes

### Files Modified

1. **`app/views/backend/index.php`** — CSS additions in `<style>` block, Google Fonts link for JetBrains Mono + DM Sans
2. **`public/assets/js/backend-app.js`** — Rendering changes for all new components

### CSS Strategy

All new styles use CSS custom properties scoped to `.dashboard-pro` theme class. The existing F7 dark mode variables are extended, not replaced. Structure:

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
}
.dark {
  --tm-bg-primary: #0c0c0c;
  --tm-bg-card: #141414;
  --tm-border: #1e1e1e;
  --tm-text-primary: #e0e0e0;
  --tm-text-secondary: #555;
  --tm-text-mono: #888;
}
```

### Component Changes

**Login page**: Dark card on dark background (or light equivalent). Monospace version number. Same card border style as dashboard.

**Summary bar**: 4-column grid of status kacheln. Runner timestamp as a small row below.

**Topic groups**: Replace box-shadow with 1px border. Slightly darker background for headers. Status border-left remains.

**Host rows**: Add sparkline element and inline response time. Tighter spacing.

**Check rows (accordion)**: Add sparkline, response time badge. Monospace values.

**Host detail page**: Add uptime-24h blocks below each check card. Sparklines inline.

**Settings page**: Same card/border treatment. Monospace for values.

### Sparkline Rendering

Pure inline HTML — no canvas/SVG needed:
```html
<span class="sparkline">
  <span class="bar" style="height:60%;background:var(--tm-ok)"></span>
  <span class="bar" style="height:80%;background:var(--tm-ok)"></span>
  <span class="bar" style="height:40%;background:var(--tm-critical)"></span>
  ...
</span>
```

### Uptime Blocks Rendering

```html
<div class="uptime-24h">
  <span class="uptime-block" style="background:var(--tm-ok)"></span>
  <span class="uptime-block" style="background:var(--tm-ok)"></span>
  <span class="uptime-block" style="background:var(--tm-critical)"></span>
  ...
</div>
```

## What Does NOT Change

- Framework7 as the framework (router, accordion, swipeout, dialog, toast)
- i18n system (de/en translation objects, `t()` function)
- Functional logic (filter, accordion persistence, localStorage)
- API structure (only additive — existing fields untouched)
- Service worker, PWA manifest
- Push API, alerts, check runner logic

## Rollout

Single deployment. All changes are CSS + JS + minor PHP API additions. No database schema changes. No migration needed. Backward-compatible — the new `recent_values` and `uptime_24h` fields are additive.
