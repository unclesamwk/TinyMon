# TinyMon

The minimalist alternative to Prometheus, Nagios & Co.
Pull checks. Push API. One SQLite file. Zero overhead.

## Quick Start

```bash
docker run -d \
  --name tinymon \
  -p 8080:80 \
  -e ADMIN_PASSWORD=changeme \
  -v tinymon-data:/var/www/html/data \
  unclesamwk/tinymon:latest
```

Open `http://localhost:8080/backend` and log in.

Add a cronjob for the check runner:

```bash
* * * * * docker exec tinymon php bin/runner.php >> /dev/null 2>&1
```

## Features

- **Pull checks**: Ping, HTTP, TCP Port, SSL Certificate, Content, Content Hash, Disk, Load, Memory
- **Push API**: External systems push results via REST API (K8s, cronjobs, CI/CD, IoT) with config-aware multi-check support
- **Push-only checks**: Disk Health (S.M.A.R.T.)
- **Alerting**: Email (SMTP) + Browser Push (VAPID) on status transitions
- **Dashboard**: Mobile-first PWA, charts, dark mode, multi-language (EN/DE)
- **Zero dependencies**: SQLite database, no agents required

## Supported Architectures

| Architecture | Tag |
|---|---|
| linux/amd64 | `latest` |
| linux/arm64 | `latest` |

Multi-arch images -- works on x86 servers and ARM (Raspberry Pi, Apple Silicon, AWS Graviton).

## Environment Variables

| Variable | Required | Description |
|---|---|---|
| `ADMIN_PASSWORD` | Yes | Login password (plaintext or bcrypt hash) |
| `SMTP_HOST` | No | SMTP server for email alerts |
| `SMTP_PORT` | No | SMTP port (default: 587) |
| `SMTP_USER` | No | SMTP username |
| `SMTP_PASSWORD` | No | SMTP password |
| `SMTP_FROM_EMAIL` | No | Sender email address |
| `ALERT_RECIPIENTS` | No | Comma-separated alert recipients |
| `PUSH_API_KEY` | No | Bearer token for Push API (empty = disabled) |
| `VAPID_PUBLIC_KEY` | No | Browser push key (auto-generated if empty) |
| `VAPID_PRIVATE_KEY` | No | Browser push key (auto-generated if empty) |

## Volumes

| Path | Description |
|---|---|
| `/var/www/html/data` | SQLite database and persistent data |

## Docker Compose

```yaml
services:
  tinymon:
    image: unclesamwk/tinymon:latest
    ports:
      - "8080:80"
    volumes:
      - tinymon-data:/var/www/html/data
    environment:
      - ADMIN_PASSWORD=changeme
      - SMTP_HOST=smtp.example.com
      - SMTP_PORT=587
      - SMTP_USER=your_user
      - SMTP_PASSWORD=your_password
      - SMTP_FROM_EMAIL=monitoring@example.com
      - ALERT_RECIPIENTS=admin@example.com

volumes:
  tinymon-data:
```

## Push API Example

```bash
curl -X POST https://mon.example.com/api/push/results \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"host_address": "web-1", "check_type": "backup", "status": "ok", "value": 42, "message": "Backup completed in 42s"}'
```

## Ecosystem

| Project | Description |
|---|---|
| [Docker Agent](https://github.com/unclesamwk/tinymon-docker-agent) | Monitors Docker containers via labels, pushes status to Push API |
| [K8s Operator](https://github.com/unclesamwk/tinymon-operator) | Monitors Kubernetes nodes, deployments, ingresses, PVCs, backups |
| [Terraform Provider](https://github.com/unclesamwk/terraform-provider-tinymon) | Manage hosts and checks as code |

## Source Code

[GitHub: unclesamwk/TinyMon](https://github.com/unclesamwk/TinyMon)

## License

MIT
