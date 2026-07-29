# Airbnb Guest System - Webserver Setup Anleitung

## 1. Verzeichnisstruktur auf dem Webserver

Ihr Hosting zeigt `airbnb.buntebutze.de/public` als Dokumentstamm. Die korrekte Struktur sollte sein:

```
/ihr-hosting-root/
├── airbnb-guest-system/          # Hauptverzeichnis (NICHT öffentlich)
│   ├── config/
│   │   ├── .env                  # Umgebungsvariablen
│   │   └── bootstrap.php
│   ├── storage/
│   │   └── logs/
│   └── public/                   # Das ist Ihr Document Root!
│       ├── index.php
│       ├── index-debug.php       # Debug-Version
│       ├── guest-access.html
│       └── guest-access.php
```

## 2. Document Root Konfiguration

**Wichtig:** Der Document Root sollte auf den `public/` Ordner zeigen, nicht auf das Hauptverzeichnis!

### Option A: Document Root ändern (empfohlen)
Ändern Sie in Ihrem Hosting-Panel den Document Root von:
```
airbnb.buntebutze.de/public
```
zu:
```
airbnb.buntebutze.de/airbnb-guest-system/public
```

### Option B: Dateien umstrukturieren (falls Document Root nicht änderbar)
Falls Sie den Document Root nicht ändern können, verschieben Sie die Dateien:

```
/ihr-hosting-root/airbnb.buntebutze.de/public/
├── index.php                     # PHP-Dateien hier
├── index-debug.php
├── guest-access.html
├── guest-access.php
├── config/                       # config Ordner hierher verschieben
│   ├── .env
│   └── bootstrap.php
└── storage/                      # storage Ordner hierher verschieben
    └── logs/
```

## 3. .htaccess Datei erstellen

Erstellen Sie eine `.htaccess` Datei im public/ Verzeichnis:

```apache
# Airbnb Guest System - Apache Configuration

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

# Basis-Authentifizierung für Admin-Bereich
# (Ihr Code macht das bereits via PHP)

# Cache-Kontrolle für statische Dateien
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
</FilesMatch>
```

## 4. PHP-Konfiguration (.user.ini)

Erstellen Sie eine `.user.ini` Datei im public/ Verzeichnis:

```ini
; Airbnb Guest System PHP Configuration
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
chmod 755 airbnb-guest-system/
chmod 755 airbnb-guest-system/config/
chmod 755 airbnb-guest-system/public/
chmod 755 airbnb-guest-system/storage/
chmod 755 airbnb-guest-system/storage/logs/

# PHP-Dateien: 644
chmod 644 airbnb-guest-system/public/*.php
chmod 644 airbnb-guest-system/config/*.php

# .env Datei: 600 (nur Owner kann lesen)
chmod 600 airbnb-guest-system/config/.env

# Log-Verzeichnis: 777 (damit PHP schreiben kann)
chmod 777 airbnb-guest-system/storage/logs/
```

## 6. Umgebungsvariablen (.env) überprüfen

Stellen Sie sicher, dass Ihre `.env` Datei alle notwendigen Variablen enthält:

```env
# Airbnb Guest System Configuration
APP_DEBUG=false
APP_DOMAIN=airbnb.example.com

# PI Service Configuration
PI_SERVICE_URL=https://your-pi-service.example.com
PI_API_USERNAME=your-pi-username
PI_API_PASSWORD=your-pi-password
CF_ACCESS_CLIENT_ID=your-cloudflare-access-client-id
CF_ACCESS_CLIENT_SECRET=your-cloudflare-access-client-secret

# Guest Access System
GUEST_ADMIN_PASSWORD=your-secure-admin-password
GUEST_ACCESS_ENCRYPTION_KEY=your-base64-encryption-key

# Rate Limiting Configuration
LIMIT_DOS_MAX_REQUEST=20
LIMIT_DOS_TIMEOUT_REQUESTS_IN_MINUTES=5
```

## 7. Debug-Schritte

1. **Laden Sie zuerst die Debug-Version hoch:**
   - Benennen Sie `index.php` um zu `index-backup.php`
   - Benennen Sie `index-debug.php` um zu `index.php`

2. **Öffnen Sie die Website im Browser:**
   - Gehen Sie zu: https://airbnb.buntebutze.de
   - Die Debug-Version zeigt detaillierte Informationen über das Problem

3. **Prüfen Sie die Debug-Ausgabe:**
   - Schauen Sie sich den HTML-Quellcode an (F12 → Sources)
   - Die Debug-Kommentare zeigen genau, wo das Problem liegt

## 8. Häufige Probleme und Lösungen

### Problem: "Bootstrap file not found"
**Lösung:** Pfad in `index.php` anpassen:
```php
// Statt:
require_once __DIR__ . '/../config/bootstrap.php';

// Verwenden Sie:
require_once __DIR__ . '/config/bootstrap.php';  // Wenn config/ im public/ liegt
```

### Problem: ".env file not found"
**Lösung:** 
- Überprüfen Sie, ob die .env Datei hochgeladen wurde
- Prüfen Sie die Dateiberechtigungen (chmod 600)
- Stellen Sie sicher, dass der Pfad korrekt ist

### Problem: "Permission denied"
**Lösung:**
```bash
chmod 644 config/.env
chmod 755 config/
chmod 777 storage/logs/
```

### Problem: "Function not found"
**Lösung:** PHP-Version prüfen - benötigt PHP 8.0+

## 9. Testing-Checklist

Nach der Konfiguration:

- [ ] Website lädt ohne weiße Seite
- [ ] Basic Authentication funktioniert
- [ ] Gäste-Link kann generiert werden
- [ ] Log-Dateien werden erstellt
- [ ] .env Variablen werden gelesen
- [ ] SSL/HTTPS funktioniert

## 10. Sicherheits-Hinweise

- Verwenden Sie HTTPS (SSL-Zertifikat)
- Halten Sie PHP aktuell (mindestens PHP 8.0)
- Beschränken Sie den Zugriff auf .env Dateien
- Überwachen Sie die Log-Dateien
- Verwenden Sie starke Passwörter