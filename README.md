# Gäste-Zugangssystem

Ein eigenständiges System für zeitlich begrenzte Türzugänge für Hausgäste, AirBnB-Gäste und Coworking-Gäste der bunte butze.

## Zugangsarten

Beim Erstellen eines Gäste-Links wird eine von zwei Zugangsarten festgelegt:

- **Nur Haustür** — für Hausgäste bzw. AirBnB-Gäste. Der Link öffnet ausschließlich die Haustür.
- **Haustür + Coworking-Tür** — für Gäste im Coworking Space. Der Link öffnet wahlweise die Haustür oder die Coworking-Tür.

Die Gäste-Seite zeigt automatisch nur die Türen an, für die der jeweilige Link freigeschaltet ist.

## Features

- 🔐 **Sicherer Token-basierter Zugang** mit AES-256-GCM Verschlüsselung
- 🚪 **Zwei Zugangsarten** — nur Haustür oder Haustür + Coworking-Tür
- 🕒 **Zeitbegrenzte Links** mit flexiblem Start- und Ablaufdatum
- 🛡️ **Basic-Auth Admin-Interface** für sicheren Zugriff
- 🍓 **Raspberry Pi Integration** für automatische Türöffnung
- ⚡ **Rate Limiting** zum Schutz vor DoS-Angriffen
- 📱 **Mobile-optimierte Oberfläche** für einfache Bedienung

## Technische Details

- **PHP 8.0+** ohne externe Dependencies
- **Eigenständiges System** unabhängig vom internen Coworking-Zugangssystem
- **URL-sichere Tokens** für kompakte Links
- **Umfassendes Logging** aller Zugriffe und Ereignisse

## Installation (Docker)

Das System läuft als Docker-Container (PHP 8.2 + Apache) hinter einem Caddy-Reverse-Proxy.

1. Repository klonen
2. `config/.env` anlegen (siehe Konfiguration unten, Datei ist per `.gitignore` ausgeschlossen)
3. Container starten:
   ```bash
   docker compose up -d
   ```
4. Reverse-Proxy (z. B. Caddy) auf den Container-Port 80 zeigen lassen

Der Container erwartet ein externes Docker-Netzwerk `web`, in dem auch der Reverse-Proxy hängt (`docker-compose.yml`). Persistenter Storage (Token-DB, Logs) liegt im Docker-Volume `storage`.

### Automatisches Deployment (CI/CD)

Bei jedem Push auf `main` baut GitHub Actions ([.github/workflows/deploy.yml](.github/workflows/deploy.yml)) automatisch ein neues Docker-Image und veröffentlicht es unter `ghcr.io/plobli/coworking-gast-zugangssystem:latest`.

Auf dem Server läuft zusätzlich ein `watchtower`-Container (siehe `docker-compose.yml`), der alle 60 Sekunden prüft, ob ein neues Image verfügbar ist, es automatisch zieht und den `app`-Container neu startet. Es ist also kein manuelles Deployment mehr nötig — eine Änderung, die auf `main` gemerged wird, landet innerhalb weniger Minuten live auf dem Server.

Voraussetzung einmalig auf dem Server: Falls das GHCR-Package privat ist, muss sich Docker dort einloggen:
```bash
echo "<GitHub PAT mit read:packages>" | docker login ghcr.io -u <github-username> --password-stdin
```
Alternativ das Package in den GitHub-Paketeinstellungen auf "public" stellen, dann ist kein Login nötig.

### Manuelle Installation (klassisches Hosting)

Alternativ kann das System ohne Docker auf einem klassischen Apache/PHP-Hosting betrieben werden — siehe [WEBSERVER_SETUP.md](WEBSERVER_SETUP.md).

## Konfiguration

Wichtige Umgebungsvariablen in `config/.env`:

```env
APP_DOMAIN=gast.buntebutze.de
GUEST_ADMIN_PASSWORD=IhrSicheresPasswort
GUEST_ACCESS_ENCRYPTION_KEY=Base64-Schlüssel
PI_SERVICE_URL=https://ihr-pi-service.de
PI_API_USERNAME=...
PI_API_PASSWORD=...
CF_ACCESS_CLIENT_ID=...
CF_ACCESS_CLIENT_SECRET=...
```

## Nutzung

1. **Admin-Zugang**: `https://gast.buntebutze.de/`
2. **Link erstellen**: Name des Gastes, Zugangsart (nur Haustür oder Haustür + Coworking-Tür) sowie Gültigkeitszeitraum festlegen — der Link wird automatisch generiert
3. **Türöffnung durch den Gast**: Gast öffnet den Link, sieht Begrüßung und die für ihn freigeschalteten Türen (Haustür bzw. Haustür + Coworking-Tür) und öffnet sie per Klick
4. **Übersicht**: Aktive, zukünftige und kürzlich abgelaufene Links lassen sich im Admin-Interface einsehen und einzelne Links vorzeitig löschen

## Sicherheit

- Basic-Auth Schutz für Admin-Interface
- AES-256-GCM Verschlüsselung für Token
- Rate Limiting gegen Brute-Force-Angriffe
- Umfassendes Audit-Logging

## Support

Bei Fragen oder Problemen wenden Sie sich an: info@buntebutze.de
