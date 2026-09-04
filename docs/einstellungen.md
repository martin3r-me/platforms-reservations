---
title: Einstellungen auf einen Blick
order: 8
---

# Einstellungen auf einen Blick

Alle Team-Einstellungen von PausePlus mit ihrer Vorgabe und der Ebene, auf der sie sich überschreiben lassen. „Vorgabe" ist der Wert, mit dem ein neues Haus startet.

Zur Ebenen-Logik siehe [So funktioniert PausePlus](index.md): Ein Termin kopiert die Vorgabe nicht, ein leeres Feld am Termin folgt ihr.

---

## Termine

| Einstellung | Wirkung | Vorgabe | Überschreibbar |
|---|---|---|---|
| **Größte Gruppe** | Wie viele Personen eine Bestellung höchstens umfassen darf | 20 | je Termin |
| **Tischbindung** | Ganzer Abend oder jede Pause einzeln, wirkt ab zwei Pausen | Ganzer Abend | je Termin |
| **Raumfreigabe neuer Termine** | Parallel oder nacheinander, als Startwert im Terminformular | Parallel | Pflichtwert je Termin |
| **Gesperrte Räume im Shop zeigen** | Gesperrte Räume mit Grund anzeigen statt weglassen | an | nein |
| **Weiche Tischkapazität** | Eine Gruppe darf einen leeren Tisch überbelegen | aus | nein |
| **Obergrenze am leeren Tisch** | Bis zu welcher Gruppengröße die weiche Kapazität gilt | unbegrenzt | nein |

Mehr dazu in [Räume und Raumfreigabe](raeume-freigabe.md) und [Pausen und Tischbindung](pausen-tischbindung.md).

## Artikelfreigabe

| Einstellung | Wirkung | Vorgabe | Besonderheit |
|---|---|---|---|
| **Vier-Augen-Prinzip** | Freigeber muss eine andere Person sein als der Einreicher | aus | Einschalten sofort, Ausschalten zu zweit. Nicht am Speichern-Knopf |

Mehr dazu in [Artikel und Freigabe](artikel-freigabe.md).

## Anmeldefelder im Shop

| Feld | Vorgabe | Mögliche Werte |
|---|---|---|
| **Name** | Pflicht | nicht einstellbar |
| **Personenzahl** | Pflicht | nicht einstellbar |
| **E-Mail** | Pflicht | Pflicht, optional, ausgeblendet |
| **Rufnummer** | optional | Pflicht, optional, ausgeblendet |
| **Notiz** | optional | Pflicht, optional, ausgeblendet |

## Texte im Shop

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Altersbestätigung** | Text, den der Gast bei alkoholischen Artikeln bestätigt | Standardtext |
| **Rechtstext** | Text vor dem Bezahlen | Standardtext |
| **Datenschutz-Link** | Adresse der Datenschutzerklärung | leer |
| **Shop-Sprachen** | Sprachen, in denen der Shop Artikel und Texte anbietet | nur Deutsch, Deutsch immer enthalten |

## Selbst-Storno

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Gäste dürfen stornieren** | Storno-Link in der Bestätigung | aus |
| **Frist in Stunden** | Vor 0 Uhr des Veranstaltungstags | keine Frist |
| **Freigabe nötig** | Storno wird erst zur Anfrage im Posteingang | aus |

Mehr dazu in [Bestellungen, Zahlung und Storno](bestellungen.md).

## Bestätigungsmail

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Absender** | Kanal, über den die Bestätigung verschickt wird | keiner, dann wird nicht versendet |
| **Shop-Adresse** | Basis-Adresse des Shops, unter anderem für den Rücksprung nach der Zahlung | leer, dann kein Rücksprung |

## Bon-Druck

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Automatisch drucken** | Bon bei jeder bestätigten Buchung | aus |
| **Drucker oder Druckergruppe** | Ziel des Bons | keins, ohne Ziel wird nicht gedruckt |

## Beleg-Design und Rechnungsangaben

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Akzentfarbe** | Farbe auf Belegen | Hausfarbe |
| **Logo, Fußzeile** | Kopf und Fuß der Belege | leer |
| **Ausstellerangaben** | Firma, Anschrift, Steuernummer und weitere Angaben für Bewirtungsbeleg und Rechnung | leer. Der Bewirtungsbeleg braucht mindestens den Firmennamen |

## Umsatz

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Nicht erschienene Gäste zählen als Umsatz** | Gilt für Umsatz, Auswertung, Export und DATEV zugleich | aus |

## DATEV

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Berater, Mandant, Erlöskonten 7 % und 19 %, Geldkonto** | Pflichtangaben für den Buchungsstapel | leer. Der Export nennt, was fehlt |
| **Sachkontenlänge** | Stellen der Konten | 4 |
| **Wirtschaftsjahr-Beginn** | Monat und Tag | 1. Januar |
| **Buchungsstil** | Einzelbuchungen oder Tagessumme | Einzelbuchungen |

## Zahlung (Mollie)

| Einstellung | Wirkung | Vorgabe |
|---|---|---|
| **Aktiv** | Zahlung über Mollie eingeschaltet | aus |
| **Modus** | Test oder Live | Test |
| **API-Schlüssel** | je Modus einer, verschlüsselt gespeichert | leer |

---

## Am Termin, am Raum, an der Station

Diese Werte gibt es nur dort, nicht als Team-Vorgabe:

| Ort | Wert | Vorgabe |
|---|---|---|
| **Termin** | Bestellschluss | Pflicht, keine Vorgabe |
| **Termin** | Verkaufsliste | Standardliste des Teams |
| **Termin** | Gesperrte Tische | keine |
| **Raum am Termin** | Schwelle der Freigabe in Prozent | 100 |
| **Raum am Termin** | Von Hand offen oder zu | der Logik folgen |
| **Station am Termin** | Kapazität je Pause | Wert der Station |
| **Station am Termin** | Pausen, in denen sie geführt wird | alle Pausen, beim Anlegen einer neuen Pause automatisch angehakt |
