# TinyMon

Minimalist server monitoring tool. Pull checks via cronjob, push API for external systems. Single SQLite database, no external dependencies.

## Project Structure

```
app/
  config/         bootstrap.php, config.php (.env loader), routes.php, services.php (DB schema + migrations)
  controllers/    BackendController.php, api/HostController, CheckController, DashboardController, PushController
  middlewares/    ApiAuthMiddleware (session), PushApiAuthMiddleware (Bearer token), CorsMiddleware
  routes/         api.php (session API), push.php (push API), backend.php (frontend)
  services/       CheckRunner.php, Database.php, AlertService.php (email), WebPushService.php, CsrfService.php
bin/
  runner.php      Cronjob runner -- executes pull checks, sends alerts, cleans up old results
public/
  index.php       Entry point
  assets/         Frontend SPA (Framework7, Chart.js)
  sw.js           Service worker for PWA
charts/tinymon/   Helm chart
data/             SQLite database (auto-created), runner.log
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

## Database

SQLite at `data/tinymon.sqlite`. Schema in `app/config/services.php`. Tables: `hosts`, `checks`, `check_results`, `settings`, `push_subscriptions`, `login_attempts`. Migrations run inline on boot. Foreign keys with CASCADE delete.

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

## Versioning

- Current version: v1.8.x
- Tags: always increment +0.0.1
- Git remotes: origin (Gitea sam-services/TinyMon), github (unclesamwk/TinyMon) -- push to both

## Related Repos

- **tinymon-operator**: K8s operator that pushes node/deployment/ingress/PVC/backup status
- **terraform-provider-tinymon**: Terraform provider for managing hosts and checks as code
