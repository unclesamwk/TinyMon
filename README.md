<p align="center">
  <img src="public/assets/images/logo.svg" width="120" alt="TinyMon Logo">
</p>

<h1 align="center">TinyMon</h1>

<p align="center">
  <strong>The minimalist alternative to Prometheus, Nagios & Co.</strong><br>
  Pull checks. Push API. One SQLite file. Zero overhead.
</p>

---

TinyMon monitors your servers and services without the complexity of a full monitoring stack. It combines **pull-based checks** (ping, HTTP, certificates, ...) with a **push API** for external systems like Kubernetes, cronjobs, or CI pipelines -- all in a single lightweight application.

## Why PHP?

TinyMon is written in PHP on purpose. It runs on any cheap shared hosting provider -- no Docker, no VPS, no Node.js runtime required. A 3 EUR/month PHP hoster with SQLite and a cronjob is all you need. That makes it the cheapest possible monitoring setup for small teams, freelancers, and side projects.

## Why not Prometheus, Zabbix, or Nagios?

| | Prometheus | Zabbix | Nagios | **TinyMon** |
|---|---|---|---|---|
| Database | TimescaleDB / remote storage | PostgreSQL / MySQL | Flat files | **SQLite** |
| Agents required | Exporters on every target | Zabbix Agent | NRPE / NSCA | **None** |
| Configuration | YAML files, relabeling | Web UI + templates | Config files | **Web UI** |
| Setup time | Hours | Hours | Hours | **Minutes** |
| Push support | Pushgateway (extra component) | Active checks | NSCA (extra daemon) | **Built-in REST API** |
| Alerting | Alertmanager (extra component) | Built-in (complex) | Config files | **Built-in (email + browser push)** |

TinyMon is not a replacement for enterprise monitoring. It is designed for a manageable number of hosts and checks -- not for hundreds of targets with high-frequency scraping. If you need that, Prometheus or Zabbix are the right choice. TinyMon is the right tool when a full stack is overkill -- for small teams, homelabs, side projects, freelancers, and shared hosting.

## Pull & Push -- One Dashboard

### Pull Checks

TinyMon actively monitors your infrastructure via a cronjob runner:

- **Ping** -- ICMP with TCP fallback (works on shared hosting)
- **HTTP/HTTPS** -- status code, response time, SSL verification
- **TCP Port** -- connect time
- **SSL Certificate** -- days until expiry
- **Content** -- expected/unexpected strings on a page
- **Content Hash** -- detect changes (SHA-256)
- **Disk / Load / Memory** -- local system resources

Each check has configurable **warning** and **critical** thresholds.

### Push API

External systems push results to TinyMon via REST API with bearer token auth:

```bash
# Push a check result from a cronjob, CI pipeline, or K8s operator
curl -X POST https://mon.example.com/api/push/results \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"host_address": "10.0.1.5", "check_type": "backup", "status": "ok", "value": 42, "message": "Backup completed in 42s"}'
```

Hosts and checks are created automatically on first push (upsert by address + type). Bulk endpoint available for submitting multiple results at once.

Use cases:
- **Kubernetes** -- an operator pushes node/pod health
- **Cronjobs** -- report success/failure after each run
- **CI/CD** -- push deployment status
- **IoT / Edge** -- devices report their own metrics

Pull and push results appear side by side in the same dashboard.

## Alerting

TinyMon notifies you when a check changes status (ok / warning / critical):

- **Email** via SMTP -- configurable recipients
- **Browser Push** via VAPID -- works on iOS, Android, and desktop

Alerts fire only on **status transitions** -- no repeated spam while something is down.

## Dashboard

- Mobile-first SPA (Framework7, iOS-style)
- Status overview across all hosts at a glance
- Charts per check (24h, 7d, 30d) with threshold zones
- Dark mode (auto / on / off)
- Multi-language (EN / DE)
- PWA-ready, pull-to-refresh

## Getting Started

### Option A: Docker

```bash
git clone https://github.com/your-org/TinyMon.git
cd TinyMon
cp .env.example .env
# Edit .env: set ADMIN_PASSWORD, SMTP credentials, etc.
docker compose up -d
```

Open `http://localhost:8001/backend` and log in.

Add a cronjob for the check runner (inside or outside the container):

```bash
# Outside
* * * * * cd /path/to/TinyMon && docker compose exec -T app php bin/runner.php >> data/runner.log 2>&1

# Or inside the container
* * * * * php /var/www/html/bin/runner.php >> /var/www/html/data/runner.log 2>&1
```

### Option B: Shared Hosting / Bare Metal

Requirements: PHP 8.3+, Composer, SQLite, Apache with `mod_rewrite`.

```bash
git clone https://github.com/your-org/TinyMon.git
cd TinyMon
cp .env.example .env
# Edit .env
composer install --no-dev --optimize-autoloader
```

Point your document root to the project root. The `.htaccess` handles routing to `public/` and blocks access to `app/`, `vendor/`, `data/`, and `.env`.

On hosts where the document root must be `public/`, point it there directly -- the `public/.htaccess` handles the rest.

Set up the runner cronjob:

```bash
* * * * * cd /path/to/TinyMon && php bin/runner.php >> data/runner.log 2>&1
```

Open `https://your-domain.com/backend` and log in.

### Enable Push API

Set `PUSH_API_KEY` in `.env`, then push results from anywhere:

```bash
# Single result
curl -X POST https://mon.example.com/api/push/results \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"host_address": "web-1", "check_type": "http", "status": "ok", "value": 120, "message": "200 OK, 120ms"}'

# Bulk
curl -X POST https://mon.example.com/api/push/bulk \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"results": [
    {"host_address": "web-1", "check_type": "memory", "status": "ok", "value": 62.5, "message": "62.5%"},
    {"host_address": "web-1", "check_type": "load", "status": "warning", "value": 3.2, "message": "Load 3.2"}
  ]}'

# Create/update hosts and checks programmatically
curl -X POST https://mon.example.com/api/push/hosts \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "k8s-node-1", "address": "10.0.1.5"}'
```

## Configuration

All settings via `.env`:

| Variable | Description |
|----------|-------------|
| `ADMIN_PASSWORD` | Login password |
| `SMTP_HOST/PORT/USER/PASSWORD` | SMTP for email alerts |
| `ALERT_RECIPIENTS` | Comma-separated recipients |
| `PUSH_API_KEY` | Bearer token for Push API (empty = disabled) |
| `VAPID_PUBLIC_KEY/PRIVATE_KEY` | Browser push keys (empty = auto-generate) |

The database (`data/tinymon.sqlite`) is created automatically. No migrations.

## API

Interactive API documentation is available via Swagger UI:

```
https://your-domain.com/api/docs
```

The OpenAPI spec is auto-generated from PHP attributes and always reflects the current code.

| API | Auth | Endpoints |
|-----|------|-----------|
| **Session API** `/api/*` | Cookie (login) | Dashboard, Host/Check CRUD, run checks, result history |
| **Push API** `/api/push/*` | Bearer token | Create hosts/checks, push results (single + bulk) |

## Tech Stack

- **Backend:** PHP 8.4, Flight PHP, SQLite
- **Frontend:** Framework7, Chart.js
- **Push Notifications:** minishlink/web-push (VAPID)
- **API Docs:** zircote/swagger-php (OpenAPI 3)
- **Deploy:** Docker or bare metal / shared hosting

## Contributing

Contributions are welcome! Feel free to open a pull request or create an issue if you find a bug, have a feature idea, or want to improve the docs.

## Disclaimer

TinyMon is provided as-is, without warranty of any kind. Use at your own risk. The authors are not liable for any damages, data loss, or missed alerts resulting from the use of this software.

## License

MIT
