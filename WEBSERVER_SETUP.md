# Gäste-Zugangssystem – Webserver Setup Anleitung (klassisches Hosting)

Diese Anleitung beschreibt den Betrieb **ohne Docker** auf einem klassischen Apache/PHP-Hosting. Für den empfohlenen Betrieb per Docker siehe [README.md](README.md).

Das System läuft unter der Domain **gast.buntebutze.de** und stellt Zugangslinks für zwei Gästearten bereit:

- **Hausgäste / AirBnB-Gäste** – Link öffnet nur die Haustür
- **Coworking-Gäste** – Link öffnet Haustür und Coworking-Tür

## 1. Verzeichnisstruktur auf dem Webserver

Die korrekte Struktur sollte sein:

```
/ihr-hosting-root/
├── coworking-gast-zugangssystem/   # Hauptverzeichnis (NICHT öffentlich)
│   ├── config/
│   │   ├── .env                    # Umgebungsvariablen
│   │   ├── bootstrap.php
│   │   └── token-db.php
│   ├── storage/
│   │   └── logs/
│   └── public/                     # Das ist Ihr Document Root!
│       ├── index.php               # Admin-Interface (Link-Generator + Übersicht)
│       ├── guest-access.php         # API: Token validieren / Tür öffnen
│       ├── guest-access-page.php    # Seite, die Gäste über den Link öffnen
│       ├── footer.inc.html
│       ├── stylesheet.css
│       ├── bunte-butze-coworking-logo.png
│       ├── favicon.ico
│       └── fonts/
```

## 2. Document Root Konfiguration

**Wichtig:** Der Document Root muss auf den `public/`-Ordner zeigen, nicht auf das Hauptverzeichnis!

### Option A: Document Root ändern (empfohlen)
Ändern Sie in Ihrem Hosting-Panel den Document Root von:
```
gast.buntebutze.de
```
zu:
```
gast.buntebutze.de/coworking-gast-zugangssystem/public
```

### Option B: Dateien umstrukturieren (falls Document Root nicht änderbar)
Falls Sie den Document Root nicht ändern können, verschieben Sie die Dateien so, dass `config/` und `storage/` eine Ebene über `public/` liegen bzw. wie folgt direkt im Document Root landen:

```
/ihr-hosting-root/gast.buntebutze.de/public/
├── index.php
├── guest-access.php
├── guest-access-page.php
├── footer.inc.html
├── stylesheet.css
├── config/                       # config Ordner hierher verschieben
│   ├── .env
│   ├── bootstrap.php
│   └── token-db.php
└── storage/                      # storage Ordner hierher verschieben
    └── logs/
```

Falls `config/` in `public/` verschoben wird, muss der Include-Pfad in `index.php`, `guest-access.php` und `guest-access-page.php` von `__DIR__ . '/../config/bootstrap.php'` auf `__DIR__ . '/config/bootstrap.php'` angepasst werden.

## 3. .htaccess Datei erstellen

Erstellen Sie eine `.htaccess` Datei im `public/`-Verzeichnis (eine Beispieldatei liegt bereits unter [public/.htaccess](public/.htaccess)):

```apache
# Gäste-Zugangssystem - Apache Configuration

# PHP Konfiguration
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 30
php_value memory_limit 256M

# Fehlerbehandlung
php_flag display_errors off
php_flag log_errors on

# Sicherheit - .env Dateien schützen
<Files ".env*">
    Require all denied
</Files>

# Sicherheit - Konfigurationsdateien schützen
<FilesMatch "\.(ini|log|conf)$">
    Require all denied
</FilesMatch>

# URL Rewriting aktivieren
RewriteEngine On

# HTTPS erzwingen (optional, aber empfohlen)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Cache-Kontrolle für statische Dateien
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|ttf)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
</FilesMatch>
```

## 4. PHP-Konfiguration (.user.ini)

Erstellen Sie eine `.user.ini` Datei im `public/`-Verzeichnis (eine Beispieldatei liegt bereits unter [public/.user.ini](public/.user.ini)):

```ini
; Gäste-Zugangssystem PHP Configuration
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
memory_limit = 256M
max_input_vars = 1000

; Fehlerbehandlung
display_errors = off
log_errors = on
error_log = ../storage/logs/php_errors.log

; Session-Konfiguration
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Zeitzone
date.timezone = Europe/Berlin
```

## 5. Dateiberechtigungen setzen

Setzen Sie die korrekten Berechtigungen via FTP/SSH:

```bash
# Verzeichnisse: 755
chmod 755 coworking-gast-zugangssystem/
chmod 755 coworking-gast-zugangssystem/config/
chmod 755 coworking-gast-zugangssystem/public/
chmod 755 coworking-gast-zugangssystem/storage/
chmod 755 coworking-gast-zugangssystem/storage/logs/

# PHP-Dateien: 644
chmod 644 coworking-gast-zugangssystem/public/*.php
chmod 644 coworking-gast-zugangssystem/config/*.php

# .env Datei: 600 (nur Owner kann lesen)
chmod 600 coworking-gast-zugangssystem/config/.env

# Log-Verzeichnis: 777 (damit PHP schreiben kann)
chmod 777 coworking-gast-zugangssystem/storage/logs/
```

## 6. Umgebungsvariablen (.env) überprüfen

Stellen Sie sicher, dass Ihre `.env` Datei alle notwendigen Variablen enthält:

```env
# Gäste-Zugangssystem Configuration
APP_DEBUG=false
APP_DOMAIN=gast.buntebutze.de

# PI Service Configuration (für Haustür- und Coworking-Tür-Öffnung)
PI_SERVICE_URL=https://ihr-pi-service.example.com
PI_API_USERNAME=ihr-pi-username
PI_API_PASSWORD=ihr-pi-passwort
CF_ACCESS_CLIENT_ID=ihre-cloudflare-access-client-id
CF_ACCESS_CLIENT_SECRET=ihr-cloudflare-access-client-secret

# Guest Access System
GUEST_ADMIN_PASSWORD=ihr-sicheres-admin-passwort
GUEST_ACCESS_ENCRYPTION_KEY=ihr-base64-encryption-key

# Rate Limiting Configuration
LIMIT_DOS_MAX_REQUEST=20
LIMIT_DOS_TIMEOUT_REQUESTS_IN_MINUTES=5
```

## 7. Debug-Schritte

1. **Öffnen Sie die Website im Browser:**
   - Admin-Interface: `https://gast.buntebutze.de/`
   - Bei weißer Seite: `APP_DEBUG=true` in `.env` setzen und `storage/logs/debug.log` prüfen

2. **Prüfen Sie die Log-Dateien:**
   - `storage/logs/debug.log` — technische Fehler (Exceptions, Tür-API-Antworten)
   - `storage/logs/guest-access.log` — Audit-Log aller Zugriffe (Linkgenerierung, Türöffnungen, abgelehnte Tokens)

## 8. Häufige Probleme und Lösungen

### Problem: "Bootstrap file not found"
**Lösung:** Pfad in `index.php`, `guest-access.php` bzw. `guest-access-page.php` anpassen:
```php
// Statt:
require_once __DIR__ . '/../config/bootstrap.php';

// Verwenden Sie:
require_once __DIR__ . '/config/bootstrap.php';  // Wenn config/ im public/ liegt
```

### Problem: ".env file not found" / Umgebungsvariablen werden nicht gelesen
**Lösung:**
- Überprüfen Sie, ob die `.env` Datei hochgeladen wurde (liegt in `config/`, nicht in `public/`)
- Prüfen Sie die Dateiberechtigungen (chmod 600)
- Stellen Sie sicher, dass der Pfad in `config/bootstrap.php` korrekt ist

### Problem: "Permission denied"
**Lösung:**
```bash
chmod 600 config/.env
chmod 755 config/
chmod 777 storage/logs/
```

### Problem: Tür öffnet nicht ("Authentifizierungsfehler HTTP 401 / 403")
**Lösung:**
- HTTP 401: `PI_API_USERNAME` / `PI_API_PASSWORD` prüfen
- HTTP 403: `CF_ACCESS_CLIENT_ID` / `CF_ACCESS_CLIENT_SECRET` prüfen (Cloudflare Access vor dem Pi-Service)
- HTTP 404: `PI_SERVICE_URL` prüfen — erwartete Endpunkte sind `/open-house-door` und `/open-coworking-door`

### Problem: Coworking-Tür wird nicht angezeigt / lässt sich nicht öffnen
**Lösung:** Das ist beabsichtigt bei Links mit Zugangsart „Nur Haustür". Nur Links mit Zugangsart „Haustür + Coworking-Tür" (im Admin-Interface bei der Linkerstellung auswählbar) schalten die Coworking-Tür frei.

### Problem: "Function not found"
**Lösung:** PHP-Version prüfen — benötigt PHP 8.0+

## 9. Testing-Checklist

Nach der Konfiguration:

- [ ] Admin-Interface lädt ohne weiße Seite und fragt Basic-Auth ab
- [ ] Link mit Zugangsart „Nur Haustür" kann generiert werden und zeigt beim Gast nur den Haustür-Button
- [ ] Link mit Zugangsart „Haustür + Coworking-Tür" kann generiert werden und zeigt beim Gast beide Buttons
- [ ] Haustür öffnet über den generierten Link
- [ ] Coworking-Tür öffnet nur bei entsprechend freigeschaltetem Link
- [ ] Log-Dateien werden erstellt (`storage/logs/`)
- [ ] Abgelaufene bzw. noch nicht gültige Links werden korrekt abgelehnt
- [ ] SSL/HTTPS funktioniert

## 10. Sicherheits-Hinweise

- Verwenden Sie HTTPS (SSL-Zertifikat)
- Halten Sie PHP aktuell (mindestens PHP 8.0)
- Beschränken Sie den Zugriff auf `.env`-Dateien
- Überwachen Sie die Log-Dateien (`storage/logs/guest-access.log`)
- Verwenden Sie ein starkes Admin-Passwort (`GUEST_ADMIN_PASSWORD`)
