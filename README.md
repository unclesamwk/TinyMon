# MiniMon

Minimalistisches Server-Monitoring. Eine Datei als Datenbank. Ein Container zum Deployen. Kein Overhead.

MiniMon ist ein schlankes, webbasiertes Monitoring-Tool fuer Server und Services. Es prueft Erreichbarkeit, Antwortzeiten, Zertifikate und Ressourcen -- und benachrichtigt per E-Mail und Push Notification, wenn sich etwas aendert.

## Warum MiniMon?

Die meisten Monitoring-Systeme sind komplex: Prometheus braucht Exporters, Grafana braucht Dashboards, Nagios braucht Konfigurationsdateien. MiniMon braucht nichts davon.

- **Eine SQLite-Datei** statt PostgreSQL, InfluxDB oder MySQL
- **Ein Docker-Container** statt Microservice-Architektur
- **Ein Cronjob** statt Message Queues und Worker
- **Ein Login** statt LDAP, OAuth und Rollenkonzept
- **Zero Dependencies** auf dem Zielsystem -- kein Agent, kein Exporter

Perfekt fuer kleine Teams, Homelabs, Side-Projects und ueberall dort, wo ein vollstaendiges Monitoring-Stack Overkill waere.

## Features

**Monitoring**
- Ping (ICMP mit TCP-Fallback fuer Shared Hosting)
- HTTP/HTTPS (Status-Code, Response-Time, SSL-Verify)
- TCP Port (Connect-Time)
- SSL-Zertifikat (Tage bis Ablauf)
- Content Check (erwarteter/unerwarteter Inhalt)
- Content Hash (Aenderungserkennung)
- Disk, Load, Memory (lokal)

**Alerting**
- E-Mail-Benachrichtigung bei Statusaenderungen (ok/warning/critical)
- Browser Push Notifications (VAPID, funktioniert auf iOS, Android, Desktop)
- Alerts nur bei Statuswechsel -- kein Spam

**Dashboard**
- Framework7 Mobile-First SPA
- Status-Uebersicht aller Hosts auf einen Blick
- Chart.js Graphen pro Check (24h, 7 Tage, 30 Tage)
- Schwellenwert-Zonen in den Graphen (gruen/gelb/rot)
- Dark Mode (Auto/An/Aus)
- Pull-to-Refresh, PWA-faehig

**API**
- REST API mit Session-Auth fuer das Frontend
- Push API mit Bearer-Token fuer externe Systeme
- OpenAPI-Dokumentation (auto-generiert aus PHP-Attributen)
- Swagger UI unter `/api/docs`

## Kubernetes & Operator

MiniMon ist nicht nur ein klassisches Pull-Monitoring. Ueber die **Push API** kann es als Monitoring-Backend fuer Kubernetes dienen.

Ein K8s Operator kann:
- Hosts und Checks per API anlegen (`POST /api/push/hosts`, `POST /api/push/checks`)
- Messergebnisse einliefern (`POST /api/push/results`, `POST /api/push/bulk`)
- Idempotent arbeiten (Upsert by Address/Type)

```bash
# Host anlegen
curl -X POST https://mon.example.com/api/push/hosts \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "k8s-node-1", "address": "10.0.1.5"}'

# Messwert pushen
curl -X POST https://mon.example.com/api/push/results \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"host_address": "10.0.1.5", "check_type": "memory", "status": "ok", "value": 62.5, "message": "62.5% used"}'

# Bulk-Ergebnisse
curl -X POST https://mon.example.com/api/push/bulk \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"results": [
    {"host_address": "10.0.1.5", "check_type": "memory", "status": "ok", "value": 62.5, "message": "62.5%"},
    {"host_address": "10.0.1.5", "check_type": "load", "status": "warning", "value": 3.2, "message": "Load 3.2"}
  ]}'
```

So bleibt MiniMon das zentrale Dashboard -- egal ob die Daten per Pull-Check oder per Push vom Operator kommen.

## Quickstart

```bash
git clone https://github.com/your-org/MiniMon.git
cd MiniMon
cp .env.example .env
# .env anpassen: ADMIN_PASSWORD, SMTP-Daten, etc.
docker compose up -d
```

Oeffne `http://localhost:8001/backend` und logge dich mit dem Passwort aus `.env` ein.

### Cronjob einrichten

Der Runner fuehrt die Checks aus und sendet Alerts:

```bash
* * * * * cd /path/to/MiniMon && php bin/runner.php >> data/runner.log 2>&1
```

Oder im Container:

```bash
docker compose exec app php bin/runner.php
```

## Konfiguration

Alle Einstellungen stehen in `.env`:

| Variable | Beschreibung |
|----------|-------------|
| `ADMIN_PASSWORD` | Login-Passwort fuer `/backend` |
| `SMTP_HOST/PORT/USER/PASSWORD` | SMTP-Server fuer E-Mail-Alerts |
| `ALERT_RECIPIENTS` | Komma-getrennte E-Mail-Empfaenger |
| `PUSH_API_KEY` | API-Key fuer die Push API (leer = deaktiviert) |
| `VAPID_PUBLIC_KEY/PRIVATE_KEY` | VAPID-Keys fuer Browser Push (leer = Auto-Generierung) |
| `VAPID_SUBJECT` | Kontakt-E-Mail fuer Push-Provider |

Die Datenbank (`data/minimon.sqlite`) wird beim ersten Start automatisch erstellt. Kein Setup, keine Migration.

## Check-Typen

| Typ | Prueft | Messwert |
|-----|--------|----------|
| `ping` | ICMP/TCP Erreichbarkeit | Latenz (ms) |
| `http` | HTTP(S) GET | Response-Time (ms) |
| `port` | TCP-Verbindung | Connect-Time (ms) |
| `certificate` | SSL-Ablaufdatum | Tage bis Ablauf |
| `content` | Seiteninhalt | Response-Time (ms) |
| `content_hash` | Inhaltsaenderung | Response-Time (ms) |
| `disk` | Festplattenauslastung | Prozent |
| `load` | System Load | Load Average |
| `memory` | RAM-Auslastung | Prozent |

Jeder Check hat konfigurierbare Warning- und Critical-Schwellenwerte.

## API-Dokumentation

Die komplette API-Doku ist unter `/api/docs` verfuegbar (Swagger UI). Die OpenAPI-Spec wird automatisch aus den PHP-Attributen generiert.

**Session API** (`/api/*`) -- Cookie-Auth nach Login:
- `GET /api/dashboard` -- Statusuebersicht
- `GET/POST/PUT/DELETE /api/hosts` -- Host-CRUD
- `GET/POST/PUT/DELETE /api/checks` -- Check-CRUD
- `POST /api/checks/{id}/run` -- Check sofort ausfuehren
- `GET /api/checks/{id}/results` -- Ergebnis-Historie

**Push API** (`/api/push/*`) -- Bearer-Token-Auth:
- `POST /api/push/hosts` -- Host anlegen/aktualisieren
- `POST /api/push/checks` -- Check anlegen/aktualisieren
- `POST /api/push/results` -- Einzelergebnis einliefern
- `POST /api/push/bulk` -- Bulk-Ergebnisse einliefern

## Tech Stack

- **Backend:** PHP 8.4, Flight PHP, SQLite
- **Frontend:** Framework7, Chart.js
- **Push:** minishlink/web-push (VAPID)
- **API-Docs:** zircote/swagger-php (OpenAPI 3)
- **Deploy:** Docker, oder direkt auf Shared Hosting

## Lizenz

MIT
