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

## Installation

1. Repository auf den Server kopieren
2. Webserver-Root auf `public/` Verzeichnis zeigen lassen
3. `.env` Konfiguration anpassen
4. Apache/Nginx für Basic-Auth konfigurieren

## Konfiguration

Wichtige Umgebungsvariablen in `config/.env`:

```env
APP_DOMAIN=airbnb.buntebutze.de
GUEST_ADMIN_PASSWORD=IhrSicheresPasswort
GUEST_ACCESS_ENCRYPTION_KEY=Base64-Schlüssel
PI_SERVICE_URL=https://ihr-pi-service.de
```

## Nutzung

1. **Admin-Zugang**: `https://airbnb.buntebutze.de/`
2. **Gäste-Links**: Werden automatisch generiert und sind gültig bis zum angegebenen Datum
3. **Türöffnung**: Gäste klicken auf den Button, um die Haustür zu öffnen

## Sicherheit

- Basic-Auth Schutz für Admin-Interface
- AES-256-GCM Verschlüsselung für Token
- Rate Limiting gegen Brute-Force-Angriffe
- Umfassendes Audit-Logging

## Support

Bei Fragen oder Problemen wenden Sie sich an: info@buntebutze.de