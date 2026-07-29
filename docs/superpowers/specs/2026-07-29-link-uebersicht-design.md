# Design: Übersicht aktiver/zukünftiger/abgelaufener Gäste-Links im Admin-Backend

## Kontext

Das Admin-Backend (`public/index.php`) erlaubt bisher nur das Generieren neuer
Gäste-Zugangslinks. Es gibt keine Möglichkeit, bestehende Links einzusehen —
weder aktive, noch zukünftig aktive, noch kürzlich abgelaufene. Die
`TokenDatabase`-Klasse (`config/token-db.php`) speichert alle Token bereits
persistent in `storage/tokens.json` und bietet mit `getStats()` nur
aggregierte Zahlen, aber keine Methode, um einzelne Einträge aufzulisten oder
gezielt zu löschen.

## Ziel

Admin soll im Backend eine Übersicht sehen über:
1. **Aktive Links** (Startdatum ≤ jetzt ≤ Ablaufdatum)
2. **Zukünftig aktive Links** (Startdatum in der Zukunft)
3. **Kürzlich abgelaufene Links** (Ablaufdatum in der Vergangenheit, aber
   nicht älter als 7 Tage)

Zusätzlich soll der Admin einen Link direkt aus der Übersicht heraus löschen
(widerrufen) können.

## Architektur

### `TokenDatabase` (config/token-db.php)

Zwei neue öffentliche Methoden:

- `listTokens(): array` — gibt alle gespeicherten Token zurück, jeweils
  angereichert um ihre Short-ID (Schlüssel aus der JSON-Struktur wird als
  Feld `id` in jedes Element gemischt), damit der Aufrufer sortieren/filtern
  und die ID fürs Löschen referenzieren kann.
- `deleteToken(string $shortId): bool` — entfernt den Eintrag mit der
  gegebenen Short-ID aus der Datenbank. Gibt `true` zurück, wenn ein Eintrag
  gelöscht wurde, `false` wenn die ID nicht (mehr) existierte (No-op, kein
  Fehler).

Keine Änderung an bestehenden Methoden oder am Speicherformat.

### `public/index.php`

**POST-Handling (vor dem bestehenden `generate`-Handling ergänzt):**

- Neuer Branch für `isset($_POST['delete_token'])`: liest `token_id` aus dem
  POST-Body, ruft `deleteToken()` auf, loggt die Aktion über
  `logGuestAccess("Admin: Link gelöscht", [...])`, setzt eine `$success`-
  oder `$error`-Meldung analog zum bestehenden Muster.

**Anzeige-Sektion (neu, unterhalb des bestehenden Formulars):**

- Lädt `listTokens()` einmal beim Seitenaufruf.
- Filtert in PHP nach den drei Kategorien anhand von `starts`/`expires` vs.
  `time()`:
  - Aktiv: `starts <= now && expires >= now`, sortiert aufsteigend nach
    `expires` (bald ablaufende zuerst).
  - Zukünftig aktiv: `starts > now`, sortiert aufsteigend nach `starts`.
  - Kürzlich abgelaufen: `expires < now && expires >= now - 7*86400`,
    sortiert absteigend nach `expires` (zuletzt abgelaufene zuerst).
- Jede Kategorie wird als eigene Tabelle mit Überschrift gerendert (leere
  Kategorien zeigen einen kurzen Platzhaltertext statt einer leeren Tabelle).
- Spalten je Zeile: Gastname, Zugangsart (Klartext-Label wie im
  Generator-Formular), gültig von, gültig bis, Nutzungszähler
  (`used_count`), Löschen-Button.
- Der Löschen-Button ist ein eigenes `<form method="post">` mit Hidden-Field
  `token_id` und einem `confirm()`-JavaScript-Handler auf `onsubmit`, um
  versehentliches Löschen zu vermeiden. Kein AJAX — die Seite lädt nach dem
  Löschen komplett neu (konsistent mit dem bestehenden Formular-Pattern der
  Seite).

**Styling:** folgt dem bestehenden Inline-`<style>`-Block der Seite (gleiche
Farbpalette, Radius, Abstände wie die bestehenden Elemente).

## Datenfluss

```
GET /index.php
  → listTokens() → Filterung in 3 Kategorien → Rendern der 3 Tabellen

POST /index.php (delete_token=1, token_id=XXXXXXXX)
  → deleteToken(token_id) → logGuestAccess(...) → Redirect/Re-Render mit
    aktualisierter Übersicht
```

## Fehlerbehandlung

- Löschen einer nicht (mehr) existierenden ID: kein Fehler, stiller No-op
  (Seite zeigt einfach die aktuelle Liste ohne den Eintrag).
- Leere Kategorien: Platzhaltertext ("Keine aktiven Links", etc.) statt
  leerer Tabelle.

## Out of Scope (dieser Teil)

- Keine Änderung am Speicherformat/-format der Token-Datenbank.
- Keine AJAX-/JS-Framework-Einführung.
- Keine Paginierung (Projektgröße/Nutzungsvolumen rechtfertigt das nicht).
- Keine Bearbeitung bestehender Links (nur Anzeige + Löschen).

**Status:** Umgesetzt und committet (b4799e6).

---

# Nachtrag: Getrenntes Design pro Gast-Typ auf gemeinsamer Domain

## Kontext / Anlass

Commit `e187ff2` ("align guest page design") hat das eigenständige Airbnb-
Gäste-Frontend (`guest-access.html`, danach `guest-access-page.php`) komplett
durch das Coworking-Design ersetzt (bunte-butze-Logo, `stylesheet.css`,
Footer mit Öffnungszeiten/Impressum). Das war ein Fehler: Airbnb-Gäste sollen
ein eigenständiges, schlichtes Design sehen — unabhängig vom Coworking-
Branding. Team-Gäste (Zugangsart `house_and_coworking`) sollen dagegen
weiterhin das Coworking-Design sehen, da sie faktisch Coworking-Nutzer sind.

Ursprünglich war angedacht, dies über zwei separate Domains zu lösen. Der
Nutzer hat sich stattdessen entschieden: **Beide Gast-Typen laufen künftig
über dieselbe Domain `gast.buntebutze.de`.** Die Design-Auswahl kann daher
nicht an der Domain hängen, sondern muss am `access_type` des jeweiligen
Tokens hängen.

## Ziel

- `house_only`-Token (Airbnb-Gäste) → eigenständiges, schlichtes Airbnb-
  Design (wie vor `e187ff2`: weiße Karte, kein Logo/Footer, eigene
  Farbpalette, Titel "Airbnb Gäste-Zugang").
- `house_and_coworking`-Token (Team-Gäste) → bestehendes Coworking-Design
  (Logo, `stylesheet.css`, Footer, "bunte butze coworking"-Branding),
  unverändert wie aktuell in `guest-access-page.php`.
- Beide Fälle laufen über dieselbe URL-Struktur und Domain
  (`gast.buntebutze.de/guest-access-page.php?token=...`).

## Architektur

### Serverseitige Vorab-Validierung (Änderung am bisherigen Ablauf)

Bisher validiert `guest-access-page.php` den Token ausschließlich clientseitig
per `fetch()` gegen `guest-access.php`. Damit das Template beim ersten Render
schon weiß, welches Design zu zeigen ist, validiert `guest-access-page.php`
den Token künftig **zusätzlich serverseitig** direkt beim Laden:

- Ruft `validateGuestToken($token)` (aus `config/bootstrap.php`) auf.
- Prüft Zeitgrenzen (`starts`/`expires`) analog zu den bestehenden Prüfungen
  in `guest-access.php`, nur um den Fall "kein Token / ungültiges Token /
  noch nicht gültig / abgelaufen" **anzeigeseitig** zu erkennen (ohne dabei
  `markTokenUsed()` erneut unnötig oft zu zählen — dafür bleibt die
  bestehende Zählung in `validateGuestToken()` tolerierbar, da das ohnehin
  bei jedem Laden/Reload passiert, wie es die bisherige Client-Validierung
  auch schon auslöste).
- Ergebnis bestimmt, welches der zwei HTML-Templates gerendert wird:
  - kein/ungültiges Token, noch nicht gültig, abgelaufen → **neutrales
    Fehler-Template** (schlicht, ohne Branding, zeigt nur die Fehlermeldung —
    es ist ja vorab nicht bekannt, welchem Gast-Typ ein ungültiger Token
    zugeordnet gewesen wäre).
  - gültig, `access_type === 'house_and_coworking'` → Coworking-Template.
  - gültig, `access_type === 'house_only'` (oder fehlend, Altbestand) →
    Airbnb-Template.
- Das clientseitige `fetch()`-basierte Verhalten (Tür öffnen per Button,
  Re-Validierung, Fehleranzeige nach Button-Klick) bleibt in beiden
  Templates erhalten — nur der äußere Rahmen (Kopfbereich, Branding, CSS)
  unterscheidet sich. Die serverseitige Vorab-Validierung dient nur der
  Template-Auswahl und dem initialen Begrüßungstext, ersetzt nicht die
  bestehende clientseitige Logik in `guest-access.php`.

### Zwei Templates in einer Datei

`guest-access-page.php` bekommt zu Beginn PHP-Logik, die den Token liest und
validiert, danach zwei getrennte HTML-Blöcke (`if`/`else`), die jeweils ihr
eigenes `<style>` bzw. `<link rel="stylesheet">` mitbringen:

- **Airbnb-Block**: Wiederherstellung des Designs aus der Vor-`e187ff2`-
  Version von `guest-access.html` (Inline-`<style>`, weiße Karte, Titel
  "🏠 Airbnb Gäste-Zugang", `status-message`/`guest-info`/`btn-reopen`-
  Klassen). Die vorhandene JS-Logik (Token validieren, Tür öffnen, erneut
  öffnen) bleibt unverändert erhalten.
- **Coworking-Block**: bleibt exakt der aktuelle Inhalt von
  `guest-access-page.php` (Logo, `stylesheet.css`, Footer-Include,
  Zwei-Türen-UI). Keine inhaltliche Änderung, nur in einen bedingten Block
  verschoben.
- Gemeinsame Teile (Doctype, `<html>`, `<head>`-Grundgerüst) werden nicht
  dupliziert, sofern sich das ohne Verrenkungen sauber trennen lässt;
  andernfalls (z. B. wegen unterschiedlicher `<title>`/Font-Includes) werden
  beide Blöcke als vollständige, unabhängige HTML-Dokumente behandelt — das
  ist bei zwei so unterschiedlichen Designs klarer als ein gemeinsames
  Grundgerüst mit vielen bedingten Stellen.

### Admin-Formular (`public/index.php`)

Keine funktionale Änderung nötig: Der generierte Link zeigt weiterhin auf
`guest-access-page.php?token=...` unter der (einen) konfigurierten
`APP_DOMAIN`. Lediglich der Wert von `APP_DOMAIN` in `.env` wird vom Nutzer
selbst auf `gast.buntebutze.de` umgestellt (Infrastruktur-Schritt, kein
Code).

## Datenfluss

```
GET /guest-access-page.php?token=XXXXXXXX
  → validateGuestToken(token) [serverseitig, nur für Template-Wahl]
  → Template-Entscheidung:
      ungültig/abgelaufen/noch nicht gültig → Fehler-Template
      access_type == house_and_coworking    → Coworking-Template
      access_type == house_only (default)   → Airbnb-Template
  → Rendering des gewählten Templates (inkl. bestehendem Client-JS)

  (unverändert) Client-JS validiert/öffnet Tür weiterhin per fetch()
  gegen guest-access.php
```

## Fehlerbehandlung

- Kein Token in der URL, Token ungültig, noch nicht gültig oder abgelaufen:
  neutrales Fehler-Template ohne Branding, analog zur bisherigen
  Fehleranzeige in `guest-access.html`, aber als eigener Rendering-Pfad statt
  nur als clientseitiger Zustand.
- Fehlender/unbekannter `access_type` im Token (z. B. sehr alte Datensätze
  ohne dieses Feld): wird wie `house_only` behandelt (Airbnb-Design),
  konsistent mit dem bestehenden Fallback in `guest-access.php`
  (`$accessType = $guestData['access_type'] ?? 'house_only';`).

## Out of Scope

- Keine Einrichtung von DNS/vHost/SSL für `gast.buntebutze.de` — das ist ein
  Infrastruktur-Schritt, den der Nutzer selbst vornimmt.
- Keine Änderung an `guest-access.php` (Tür-Öffnungs-API) oder am
  Token-/Datenbankformat.
- Keine Vereinheitlichung der beiden Templates zu einem gemeinsamen
  CSS-System — sie bleiben bewusst unabhängig voneinander.
