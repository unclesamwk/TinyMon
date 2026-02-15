<p align="center">
  <img src="public/assets/images/logo.svg" width="120" alt="TinyMon Logo">
</p>

<h1 align="center">TinyMon</h1>

<p align="center">
  <strong>Minimalist server monitoring. One database file. One container. Zero overhead.</strong>
</p>

---

TinyMon is a lightweight, web-based monitoring tool for servers and services. It checks availability, response times, certificates, and resources -- and notifies you via email and push notification when something changes.

## Why TinyMon?

Most monitoring systems are complex: Prometheus needs exporters, Grafana needs dashboards, Nagios needs config files. TinyMon needs none of that.

- **One SQLite file** instead of PostgreSQL, InfluxDB, or MySQL
- **One Docker container** instead of microservice architecture
- **One cronjob** instead of message queues and workers
- **One login** instead of LDAP, OAuth, and role management
- **Zero dependencies** on target systems -- no agent, no exporter

Perfect for small teams, homelabs, side projects, and everywhere a full monitoring stack is overkill.

## Features

**Monitoring**
- Ping (ICMP with TCP fallback for shared hosting)
- HTTP/HTTPS (status code, response time, SSL verification)
- TCP port (connect time)
- SSL certificate (days until expiry)
- Content check (expected/unexpected content)
- Content hash (change detection)
- Disk, load, memory (local)

**Alerting**
- Email notifications on status changes (ok/warning/critical)
- Browser push notifications (VAPID -- works on iOS, Android, desktop)
- Alerts only on status transitions -- no spam

**Dashboard**
- Framework7 mobile-first SPA
- At-a-glance status overview of all hosts
- Chart.js graphs per check (24h, 7 days, 30 days)
- Threshold zones in graphs (green/yellow/red)
- Dark mode (auto/on/off)
- Pull-to-refresh, PWA-ready

**API**
- REST API with session auth for the frontend
- Push API with bearer token for external systems
- OpenAPI documentation (auto-generated from PHP attributes)
- Swagger UI at `/api/docs`

## Kubernetes & Operator

TinyMon is not just a classic pull monitor. Through its **Push API**, it can serve as a monitoring backend for Kubernetes.

A K8s operator can:
- Create hosts and checks via API (`POST /api/push/hosts`, `POST /api/push/checks`)
- Submit check results (`POST /api/push/results`, `POST /api/push/bulk`)
- Work idempotently (upsert by address/type)

```bash
# Create a host
curl -X POST https://mon.example.com/api/push/hosts \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "k8s-node-1", "address": "10.0.1.5"}'

# Push a check result
curl -X POST https://mon.example.com/api/push/results \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"host_address": "10.0.1.5", "check_type": "memory", "status": "ok", "value": 62.5, "message": "62.5% used"}'

# Bulk results
curl -X POST https://mon.example.com/api/push/bulk \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"results": [
    {"host_address": "10.0.1.5", "check_type": "memory", "status": "ok", "value": 62.5, "message": "62.5%"},
    {"host_address": "10.0.1.5", "check_type": "load", "status": "warning", "value": 3.2, "message": "Load 3.2"}
  ]}'
```

TinyMon stays your single dashboard -- whether data comes from pull checks or is pushed by an operator.

## Quickstart

```bash
git clone https://github.com/your-org/TinyMon.git
cd TinyMon
cp .env.example .env
# Edit .env: set ADMIN_PASSWORD, SMTP credentials, etc.
docker compose up -d
```

Open `http://localhost:8001/backend` and log in with the password from `.env`.

### Set up the cronjob

The runner executes checks and sends alerts:

```bash
* * * * * cd /path/to/TinyMon && php bin/runner.php >> data/runner.log 2>&1
```

Or inside the container:

```bash
docker compose exec app php bin/runner.php
```

## Configuration

All settings are in `.env`:

| Variable | Description |
|----------|-------------|
| `ADMIN_PASSWORD` | Login password for `/backend` |
| `SMTP_HOST/PORT/USER/PASSWORD` | SMTP server for email alerts |
| `ALERT_RECIPIENTS` | Comma-separated email recipients |
| `PUSH_API_KEY` | API key for the Push API (empty = disabled) |
| `VAPID_PUBLIC_KEY/PRIVATE_KEY` | VAPID keys for browser push (empty = auto-generate) |
| `VAPID_SUBJECT` | Contact email for push service providers |

The database (`data/tinymon.sqlite`) is created automatically on first start. No setup, no migrations.

## Check Types

| Type | Checks | Metric |
|------|--------|--------|
| `ping` | ICMP/TCP reachability | Latency (ms) |
| `http` | HTTP(S) GET | Response time (ms) |
| `port` | TCP connection | Connect time (ms) |
| `certificate` | SSL expiry date | Days until expiry |
| `content` | Page content | Response time (ms) |
| `content_hash` | Content changes | Response time (ms) |
| `disk` | Disk usage | Percent |
| `load` | System load | Load average |
| `memory` | RAM usage | Percent |

Each check has configurable warning and critical thresholds.

## API Documentation

Full API docs are available at `/api/docs` (Swagger UI). The OpenAPI spec is auto-generated from PHP attributes.

**Session API** (`/api/*`) -- cookie auth after login:
- `GET /api/dashboard` -- Status overview
- `GET/POST/PUT/DELETE /api/hosts` -- Host CRUD
- `GET/POST/PUT/DELETE /api/checks` -- Check CRUD
- `POST /api/checks/{id}/run` -- Run check immediately
- `GET /api/checks/{id}/results` -- Result history

**Push API** (`/api/push/*`) -- bearer token auth:
- `POST /api/push/hosts` -- Create/update host
- `POST /api/push/checks` -- Create/update check
- `POST /api/push/results` -- Submit single result
- `POST /api/push/bulk` -- Submit bulk results

## Tech Stack

- **Backend:** PHP 8.4, Flight PHP, SQLite
- **Frontend:** Framework7, Chart.js
- **Push:** minishlink/web-push (VAPID)
- **API Docs:** zircote/swagger-php (OpenAPI 3)
- **Deploy:** Docker, or directly on shared hosting

## License

MIT
