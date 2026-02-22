# TinyMon

Minimalist server monitoring tool. Pull checks via cronjob, push API for external systems. Single SQLite database, no external dependencies.

## Project Structure

```
app/
  config/         bootstrap.php, config.php (.env loader), routes.php, services.php (DB schema + migrations)
  controllers/    BackendController.php, api/HostController, CheckController, DashboardController, PushController
  middlewares/    ApiAuthMiddleware (session), PushApiAuthMiddleware (Bearer token), CorsMiddleware
  routes/         api.php (session API), push.php (push API), backend.php (frontend SPA catch-all)
  services/       CheckRunner.php, Database.php, AlertService.php (email), WebPushService.php, CsrfService.php
  views/backend/  index.php (SPA shell with CSS for topic-groups, host-checks-sublist, dark mode)
bin/
  runner.php      Cronjob runner -- executes pull checks, sends alerts, cleans up old results
public/
  index.php       Entry point
  .htaccess       Routing, security headers, CSP (includes api.github.com in connect-src)
  assets/         Frontend SPA (Framework7, Chart.js)
  sw.js           Service worker for PWA
charts/tinymon/   Helm chart
data/             SQLite database (auto-created), runner.log
VERSION           Current version tag (read by backend for update check)
```

## Tech Stack

- PHP 8.4, Flight PHP framework, SQLite (WAL mode)
- Frontend: Framework7 (iOS-style SPA), Chart.js
- Notifications: minishlink/web-push (VAPID), PHPMailer (SMTP)
- API docs: zircote/swagger-php (OpenAPI 3, auto-generated from annotations)
- Docker image: `unclesamwk/tinymon` (amd64 + arm64)

## Key Concepts

- **Pull checks**: Runner executes checks (ping, http, port, certificate, content, disk, load, memory, etc.) via cronjob every minute
- **Push API**: External systems (K8s operator, Terraform, scripts) push results via Bearer token auth
- **Upsert pattern**: Hosts matched by `address`, checks by `host_address` + `type` + `config`
- **Config-aware checks**: Multiple checks of same type per host distinguished by `config` JSON (e.g. `{"mount": "/"}`)
- **Alerts on status transitions only**: No repeated notifications while something stays down

## Frontend Architecture

The SPA (`public/assets/js/backend-app.js`) is a single large JS file with:

- **i18n**: Two inline translation objects (de/en) at the top. Keys used via `t("key")`.
- **Dashboard**: `renderDashboard(page)` builds the host list. Hosts are grouped by `topic` into a tree (`buildTopicTree` → `renderTree`). Topic groups and hosts are Framework7 accordion items.
- **Host accordion items**: Each host in the dashboard is an accordion. When expanded, shows inline check list (`host-checks-sublist` div) with status icons and links to check history. "Details →" link navigates to host detail page.
- **Status filter**: `dashboardFilter` variable. Filters on **check level** (not host level) — hosts are mapped to only include matching checks. When active, all topic groups and hosts auto-expand.
- **Accordion persistence**: Topic open/close state in `localStorage.openTopics`, host open/close state in `localStorage.openHosts`.
- **Status dots**: `renderStatusDots(counts)` renders colored count indicators (e.g. `3● 1●`). Used in topic group headers and host headers.
- **Update check**: `checkGitHubUpdate()` fetches latest tag from GitHub API (`/tags?per_page=1`), caches in localStorage for 1 hour. Shows badge in navbar linking to update instructions.
- **Browser history**: Framework7 `browserHistory` enabled with `browserHistoryRoot: "/backend"`. Server has SPA catch-all route (`GET /backend/*`).
- **Check history page**: Route `/check-history/:checkId/` shows alert log filtered by check ID (from `/api/alert-log?check_id=X`).

## Database

SQLite at `data/tinymon.sqlite`. Schema in `app/config/services.php`. Tables: `hosts`, `checks`, `check_results`, `settings`, `push_subscriptions`, `login_attempts`, `alert_log`. Migrations run inline on boot. Foreign keys with CASCADE delete.

## APIs

- **Session API** (`/api/*`): Cookie auth via `/backend/login`. CRUD for hosts, checks, results, notifications.
- **Push API** (`/api/push/*`): Bearer token auth (`PUSH_API_KEY`). Upsert hosts/checks, push results (single + bulk), GET for state reads.
- **Swagger UI**: `/api/docs`. OpenAPI spec auto-generated from PHP annotations.

## Development

```bash
cp .env.example .env   # edit credentials
composer install
docker compose up -d   # http://localhost:8001/backend
```

Runner cronjob (pull checks): `php bin/runner.php`

**Important:** No PHP installed locally. Always test PHP via Docker:
```bash
docker compose exec app php bin/runner.php
docker compose exec app php bin/seed-demo.php
```

## Versioning

- Current version: v1.8.x
- Tags: always increment +0.0.1
- Git remotes: origin (Gitea sam-services/TinyMon), github (unclesamwk/TinyMon) -- push to both

## Related Repos

- **tinymon-operator**: K8s operator that pushes node/deployment/ingress/PVC/backup status
- **tinymon-docker-agent**: Docker agent that monitors containers via labels and pushes status to Push API
- **terraform-provider-tinymon**: Terraform provider for managing hosts and checks as code
