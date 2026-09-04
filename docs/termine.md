---
title: Termine und Status
order: 3
---

# Termine und Status

Ein Termin ist eine Veranstaltung mit Datum, Bestellschluss, mindestens einer Pause und mindestens einem Ort, an dem bestellt werden kann. Sein Status entscheidet, ob Gäste ihn sehen und ob sie bestellen können.

---

## Die fünf Status

| Status | Im Shop sichtbar | Bestellbar | Wann |
|---|---|---|---|
| **Entwurf** | nein | nein | Neu angelegt, dupliziert oder importiert |
| **Bald verfügbar** | ja, mit Hinweis | nein | Der Termin steht fest, der Vorverkauf hat noch nicht begonnen |
| **Veröffentlicht** | ja | ja, bis zum Bestellschluss | Der Vorverkauf läuft |
| **Bestellschluss** | ja, mit Hinweis | nein | Von Hand geschlossen, oder als Anzeige nach Ablauf der Frist |
| **Abgesagt** | ja, rot markiert | nein | Die Veranstaltung findet nicht statt |

Nur **Veröffentlicht** erlaubt Bestellungen, und auch dann nur bis zum Bestellschluss. Alle anderen Status sind Anzeige.

**„Vergangen" ist kein Status.** Ein Termin bleibt nach dem Abend, was er war, und verschwindet im Shop von selbst am Tag nach dem Veranstaltungsdatum. Im Backoffice findet ihn die Terminliste unter dem Filter *Nachzubereiten*, solange er noch bestätigte Buchungen hat, die nicht abgeschlossen sind. Siehe [Bestellungen, Zahlung und Storno](bestellungen.md).

---

## Warum neue Termine Entwürfe sind

Neu angelegte, duplizierte und importierte Termine starten immer als Entwurf. Ein Import bringt schnell Dubletten oder halbfertige Einträge mit, und die soll kein Gast sehen. Wer viele Termine auf einmal sichtbar machen will, setzt sie danach gesammelt auf *Bald verfügbar*.

---

## Was zum Veröffentlichen fehlt

Ein Termin lässt sich erst veröffentlichen, wenn er hat:

- **mindestens eine Pause**
- **mindestens einen Raum oder eine Abholstation**. Eine Station genügt, ein Termin kann ganz ohne Tische auskommen

Dazu kommen die Pflichtangaben des Formulars: Name, Datum, Bestellschluss und der Modus der Raumfreigabe. Fehlt etwas, sagt die Meldung beim Veröffentlichen, was.

Zurückziehen ist jederzeit möglich. Ein zurückgezogener Termin ist wieder ein Entwurf und für Gäste unsichtbar. Bestehende Bestellungen bleiben davon unberührt.

---

## Der Bestellschluss

Jeder Termin braucht einen Bestellschluss. Ab diesem Zeitpunkt nimmt der Shop keine Bestellungen mehr an, auch wenn ein Gast die Seite noch offen hat. Der Termin bleibt sichtbar und zeigt „Bestellschluss".

Der Bestellschluss gilt **nur für den Shop**. Wer im Backoffice eine Buchung von Hand anlegt, kann das auch danach noch tun. Die Software geht davon aus, dass ein Mitarbeiter weiß, was er tut, etwa bei einer telefonischen Nachbestellung.

Ältere Termine ohne Bestellschluss bleiben bis zum Veranstaltungstag bestellbar.

---

## Was der Termin sonst noch festlegt

| Angabe | Bedeutung | Mehr dazu |
|---|---|---|
| **Pausen** | Eine oder mehrere, jede mit Namen und optional Uhrzeit | [Pausen und Tischbindung](pausen-tischbindung.md) |
| **Räume** | Tischpläne des Hauses in einer Reihenfolge, je Raum eine Schwelle für die Freigabe | [Räume und Raumfreigabe](raeume-freigabe.md) |
| **Abholstationen** | Stationen des Hauses, je Pause zugeordnet, mit Kapazität | [Abholstationen](abholstationen.md) |
| **Verkaufsliste** | Welche Artikel angeboten werden. Leer heißt Standardliste des Teams | [Artikel und Freigabe](artikel-freigabe.md) |
| **Größte Gruppe** | Wie viele Personen eine Bestellung höchstens umfasst. Leer heißt Team-Vorgabe | [Einstellungen](einstellungen.md) |
| **Tischbindung** | Ganzer Abend oder jede Pause einzeln. Erst ab zwei Pausen sichtbar | [Pausen und Tischbindung](pausen-tischbindung.md) |
| **Gesperrte Tische** | Einzelne Tische, die an diesem Termin nicht buchbar sind, etwa wegen Technik | [Räume und Raumfreigabe](raeume-freigabe.md) |

---

## Freigabe-Link für Veranstaltungsleiter

Für jeden Termin lässt sich ein Link mit PIN erzeugen, über den eine Person **ohne Konto** Küchenansicht und Laufzettel sieht. Der Link gilt bis zum Tag nach der Veranstaltung. Die Buchungsliste mit Namen und E-Mail-Adressen ist darüber bewusst nicht erreichbar.
