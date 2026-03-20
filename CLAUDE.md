# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What is TinyMon

Minimalist server monitoring tool. Pull checks via cronjob, push API for external systems. Single SQLite database, no external dependencies.

## Development Commands

```bash
# Start dev environment
cp .env.example .env   # first time only
composer install        # first time only
docker compose up -d   # http://localhost:8001/backend

# Run pull checks (cronjob runner)
docker compose exec app php bin/runner.php

# Seed demo data
docker compose exec app php bin/seed-demo.php

# PHP syntax check (same as CI)
docker compose exec app sh -c "find app public bin -name '*.php' -print0 | xargs -0 -n1 php -l"
```

**No PHP installed locally.** Always run PHP commands via `docker compose exec app`.

## CI/CD

Drone CI (`.drone.yml`): lint pipeline runs `php -l` syntax check + `composer install` on every push. Deploy pipeline triggers on `v*` tags -- SSHs to shared hosting, runs `deploy.sh`. GitHub Actions publish Docker image and Helm chart.

## Deployment / Versioning

- Tags trigger deploy: `git tag -a v1.8.33 -m "description" && git push origin main v1.8.33 && git push github main v1.8.33`
- Always increment `+0.0.1` from latest tag
- Git remotes: `origin` (Gitea sam-services/TinyMon), `github` (unclesamwk/TinyMon) -- push to both
- `VERSION` file is written by `deploy.sh` at deploy time, not manually maintained
- Docker image: `unclesamwk/tinymon` (amd64 + arm64)

## Project Structure

```
app/
  config/         bootstrap.php, config.php (.env loader), routes.php, services.php (DB schema + migrations)
  controllers/    BackendController.php, api/HostController, CheckController, DashboardController, PushController
  middlewares/    ApiAuthMiddleware (session), PushApiAuthMiddleware (Bearer token), CorsMiddleware
  routes/         api.php (session API), push.php (push API), backend.php (frontend SPA catch-all)
  services/       CheckRunner.php, Database.php, AlertService.php (email), WebPushService.php, CsrfService.php
  views/backend/  index.php (SPA shell with CSS), login.php (standalone login page)
bin/
  runner.php      Cronjob runner -- executes pull checks, sends alerts, cleans up old results
public/
  index.php       Entry point
  .htaccess       Routing, security headers, CSP (includes api.github.com in connect-src)
  assets/         Frontend SPA (Framework7, Chart.js), self-hosted fonts (JetBrains Mono, DM Sans)
  sw.js           Service worker for PWA
charts/tinymon/   Helm chart
data/             SQLite database (auto-created), runner.log
```

## Tech Stack

- PHP 8.4, Flight PHP framework, SQLite (WAL mode)
- Frontend: Framework7 (iOS-style SPA), Chart.js -- single file `public/assets/js/backend-app.js`
- Typography: JetBrains Mono (monospace values), DM Sans (UI text) -- self-hosted under `public/assets/fonts/`
- Notifications: minishlink/web-push (VAPID), PHPMailer (SMTP)
- API docs: zircote/swagger-php (OpenAPI 3, auto-generated from PHP annotations)
- PSR-4 autoload: `App\` -> `app/`

## Key Concepts

- **Pull checks**: Runner executes checks (ping, http, port, certificate, content, disk, load, memory, etc.) via cronjob every minute
- **Push API**: External systems (K8s operator, Terraform, scripts) push results via Bearer token auth
- **Upsert pattern**: Hosts matched by `address`, checks by `host_address` + `type` + `config`
- **Config-aware checks**: Multiple checks of same type per host distinguished by `config` JSON (e.g. `{"mount": "/"}`)
- **Alerts on status transitions only**: No repeated notifications while something stays down

## Frontend Architecture

The SPA (`public/assets/js/backend-app.js`) is a single large JS file with:

- **i18n**: Two inline translation objects (de/en) at the top. Keys used via `t("key")`.
- **Dashboard**: `renderDashboard(page)` builds the host list. Hosts are grouped by `topic` into a tree (`buildTopicTree` -> `renderTree`). Topic groups and hosts are Framework7 accordion items.
- **Summary tiles**: 4-column grid of bordered status cards (OK/Warning/Critical/Unknown) with monospace numbers. Clickable for filtering.
- **Sparklines**: `renderSparkline(recentValues)` renders inline mini bar charts from last 20 check values. Pure CSS, no Chart.js.
- **Value badges**: `renderValueBadge(check)` shows formatted values inline (e.g. "142ms", "85d", "62.5%").
- **Uptime 24h**: `renderUptime24h(uptime24h, uptimeHours)` renders 24 colored blocks per check showing hourly status. Displayed on host detail page.
- **Status filter**: `dashboardFilter` variable. Filters on **check level** (not host level). When active, all topic groups and hosts auto-expand.
- **Accordion persistence**: Topic open/close state in `localStorage.openTopics`, host open/close state in `localStorage.openHosts`.
- **Update check**: `checkGitHubUpdate()` fetches latest tag from GitHub API, caches in localStorage for 1 hour.
- **Browser history**: Framework7 `browserHistory` with `browserHistoryRoot: "/backend"`.

### CSS Theming

CSS custom properties on `:root` with dark mode overrides via F7 compound selectors (`.ios .dark, .ios.dark, .md .dark, .md.dark`). Key variables: `--tm-bg-primary`, `--tm-bg-card`, `--tm-border`, `--tm-text-primary`, `--tm-font-mono`, `--tm-font-ui`, `--tm-ok`, `--tm-warning`, `--tm-critical`, `--tm-unknown`.

## Database

SQLite at `data/tinymon.sqlite`. Schema in `app/config/services.php`. Tables: `hosts`, `checks`, `check_results`, `settings`, `push_subscriptions`, `login_attempts`, `alert_log`. Migrations run inline on boot. Foreign keys with CASCADE delete.

## APIs

- **Session API** (`/api/*`): Cookie auth via `/backend/login`. CRUD for hosts, checks, results, notifications.
  - `/api/dashboard` returns hosts with checks, `recent_values` (sparkline data, last 20 per check)
  - `/api/hosts/{id}` returns host with checks, `recent_values`, `uptime_24h` (24 hourly status slots), `uptime_hours` (UTC hour labels)
- **Push API** (`/api/push/*`): Bearer token auth (`PUSH_API_KEY`). Upsert hosts/checks, push results (single + bulk), GET for state reads.
- **Swagger UI**: `/api/docs`. OpenAPI spec auto-generated from PHP annotations.

## Related Repos

- **tinymon-operator**: K8s operator that pushes node/deployment/ingress/PVC/backup status
- **tinymon-docker-agent**: Docker agent that monitors containers via labels and pushes status to Push API
- **terraform-provider-tinymon**: Terraform provider for managing hosts and checks as code
