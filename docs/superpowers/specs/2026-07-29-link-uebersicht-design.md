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

## Out of Scope

- Keine Änderung am Speicherformat/-format der Token-Datenbank.
- Keine AJAX-/JS-Framework-Einführung.
- Keine Paginierung (Projektgröße/Nutzungsvolumen rechtfertigt das nicht).
- Keine Bearbeitung bestehender Links (nur Anzeige + Löschen).
