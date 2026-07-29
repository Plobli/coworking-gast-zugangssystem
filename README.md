# Airbnb Guest Access System

Ein eigenständiges System für zeitlich begrenzte Haustür-Zugänge für Airbnb-Gäste.

## Features

- 🔐 **Sicherer Token-basierter Zugang** mit AES-256-GCM Verschlüsselung
- 🕒 **Zeitbegrenzte Links** mit flexiblem Ablaufdatum
- 🛡️ **Basic-Auth Admin-Interface** für sicheren Zugriff
- 🚪 **Raspberry Pi Integration** für automatische Türöffnung
- ⚡ **Rate Limiting** zum Schutz vor DoS-Angriffen
- 📱 **Mobile-optimierte Oberfläche** für einfache Bedienung

## Technische Details

- **PHP 8.0+** ohne externe Dependencies
- **Eigenständiges System** unabhängig vom Coworking-Zugangssystem
- **URL-sichere Tokens** für kompakte Links
- **Umfassendes Logging** aller Zugriffe und Ereignisse

## Installation (Docker)

Das System läuft als Docker-Container (PHP 8.2 + Apache) hinter einem Caddy-Reverse-Proxy.

1. Repository klonen
2. `config/.env` anlegen (siehe Konfiguration unten, Datei ist per `.gitignore` ausgeschlossen)
3. Container bauen und starten:
   ```bash
   docker compose up -d --build
   ```
4. Reverse-Proxy (z. B. Caddy) auf den Container-Port 80 zeigen lassen

Der Container erwartet ein externes Docker-Netzwerk `web`, in dem auch der Reverse-Proxy hängt (`docker-compose.yml`). Persistenter Storage (Token-DB, Logs) liegt im Docker-Volume `storage`.

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
2. **Gäste-Links**: Werden automatisch generiert und sind gültig bis zum angegebenen Datum
3. **Türöffnung**: Gäste klicken auf den Button, um die Haustür zu öffnen

## Sicherheit

- Basic-Auth Schutz für Admin-Interface
- AES-256-GCM Verschlüsselung für Token
- Rate Limiting gegen Brute-Force-Angriffe
- Umfassendes Audit-Logging

## Support

Bei Fragen oder Problemen wenden Sie sich an: info@buntebutze.de