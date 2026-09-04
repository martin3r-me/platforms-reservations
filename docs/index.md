---
title: So funktioniert PausePlus
order: 1
---

# So funktioniert PausePlus

PausePlus nimmt Vorbestellungen für die Pausen einer Veranstaltung an. Gäste bestellen im Shop Getränke und Snacks, wählen einen Tisch oder eine Abholstation und bezahlen im Voraus. Das Haus sieht die Bestellungen im Backoffice, bereitet sie nach Pause und Ort vor und rechnet am Ende ab.

Diese Hilfe erklärt die Regeln, nach denen das Modul arbeitet, und warum sie so sind. Jede Regel steht dort, wo sie im Alltag auffällt. Wer nur wissen will, welcher Schalter was tut, findet alle Einstellungen gesammelt unter [Einstellungen auf einen Blick](einstellungen.md).

---

## Die Bausteine

| Baustein | Was er ist | Wo er gepflegt wird |
|---|---|---|
| **Haus (Venue)** | Ein Veranstaltungsort mit seinen Tischplänen und Abholstationen | Venues & Tischpläne, Abholstationen |
| **Tischplan** | Ein Raum mit Tischen und Plätzen. Tische sind teilbar, gezählt wird in Plätzen | Venues & Tischpläne |
| **Abholstation** | Ein Ort, an dem Gäste ihre Bestellung selbst abholen, mit einer Grenze an Gästen je Pause | Abholstationen |
| **Artikel** | Ein Getränk oder Snack mit Preis, Steuersatz und Kennzeichnung. Wird erst nach Freigabe sichtbar | Artikel |
| **Verkaufsliste** | Die Auswahl an Artikeln, die ein Termin anbietet | Verkaufslisten |
| **Termin** | Eine Veranstaltung mit Datum, Bestellschluss, einer oder mehreren Pausen, Räumen und Stationen | Termine |
| **Bestellung** | Der Einkauf eines Gastes: eine Zahlung, je Pause eine Buchung mit Ort und Positionen | Alle Buchungen, Posteingang |

Die Reihenfolge ist zugleich die Reihenfolge der Einrichtung: erst das Haus mit Räumen und Stationen, dann die Artikel und ihre Freigabe, dann der Termin, der beides zusammenführt.

---

## Der Weg einer Bestellung

1. **Der Termin steht im Shop.** Das passiert erst mit dem Status *Veröffentlicht*. Vorher ist er ein Entwurf oder „Bald verfügbar". Siehe [Termine und Status](termine.md).
2. **Der Gast wählt Personenzahl und Pause.** Die Pause wird nur gefragt, wenn der Termin mehr als eine hat. Siehe [Pausen und Tischbindung](pausen-tischbindung.md).
3. **Der Gast wählt Artikel.** Angeboten wird nur, was in der Verkaufsliste des Termins steht, freigegeben und verfügbar ist. Siehe [Artikel und Freigabe](artikel-freigabe.md).
4. **Der Gast wählt einen Ort.** Einen Tisch in einem freigegebenen Raum oder eine Abholstation mit freier Kapazität. Siehe [Räume und Raumfreigabe](raeume-freigabe.md) und [Abholstationen](abholstationen.md).
5. **Der Gast bezahlt.** Erst mit dem Zahlungseingang ist die Bestellung bestätigt. Dann geht die Bestätigungsmail heraus – genau einmal, auch wenn Mollie die Meldung mehrfach schickt – und, falls eingerichtet, der Bon in die Küche. Siehe [Bestellungen, Zahlung und Storno](bestellungen.md).
6. **Am Abend** zeigt das Dashboard die Auslastung, der Laufzettel führt die Küche durch die Pausen, und nach der Veranstaltung wird der Abend abgeschlossen.

Der Server prüft jeden Schritt noch einmal, wenn die Bestellung eintrifft. Was der Shop anzeigt, ist eine Vorschau. Verbindlich ist, was das Backoffice im Moment der Bestellung feststellt: Bestellschluss, Freigabe der Artikel, freie Plätze, freigegebener Raum, Kapazität der Station.

---

## Wer entscheidet was: die drei Ebenen

Viele Regeln lassen sich auf mehreren Ebenen festlegen. Dabei gilt immer dieselbe Logik:

| Ebene | Beispiele | Verhalten |
|---|---|---|
| **Team-Vorgabe** (Einstellungen) | Größte Gruppe, Tischbindung, Raumfreigabe neuer Termine, Storno, Anmeldefelder | Gilt für alles, was keinen eigenen Wert hat |
| **Termin** | Größte Gruppe, Tischbindung, Freigabemodus, Verkaufsliste, gesperrte Tische | Ein eigener Wert schlägt die Vorgabe. Ein leeres Feld folgt der Vorgabe |
| **Raum oder Station am Termin** | Schwelle der Raumfreigabe, Raum von Hand auf oder zu, Kapazität einer Station | Gilt nur für diesen Raum oder diese Station an diesem Termin |

Wichtig dabei: Ein Termin **kopiert** die Vorgabe nicht beim Anlegen. Wer die Team-Vorgabe später ändert, ändert damit alle Termine, die kein eigenes Feld gesetzt haben. Das ist gewollt. Wer einen Termin dauerhaft anders haben will, setzt den Wert am Termin.

---

## Was oft überrascht

- **Manuelles Buchen im Backoffice kennt weder Bestellschluss noch Raumfreigabe.** Wer eine Buchung von Hand anlegt, tut das bewusst, und die Software stellt sich nicht in den Weg. Eine solche Buchung ist sofort bestätigt, mit Zahlung vor Ort.
- **„Vergangen" ist kein Status.** Ein Termin bleibt *Veröffentlicht* oder *Bestellschluss* und verschwindet im Shop von selbst am Tag nach dem Datum.
- **Eine Freigabe verfällt bei Preis oder Text, nicht bei Verfügbarkeit.** Ein Artikel darf jederzeit aus dem Angebot genommen und wieder hineingenommen werden, ohne neue Freigabe. Das Verfallen gilt allerdings nur, solange das Vier-Augen-Prinzip eingeschaltet ist – ohne Freigabepflicht bleibt eine erteilte Freigabe auch nach einer Änderung bestehen.
- **Nicht erschienene Gäste zählen nur auf Wunsch als Umsatz.** Die Einstellung dazu gilt für Umsatz, Artikelauswertung, Export und DATEV gleichermaßen.
- **Die Grenze einer Abholstation ist eine Bremse, keine Zusage.** Zwei Gäste, die in derselben Sekunde den letzten Platz an einer Station buchen, bekommen ihn beide. Bei Tischen kann das nicht passieren.
- **Die Auswertung der Bestellwege zählt ab ihrer Einführung**, nicht rückwirkend. Sie entsteht in dem Moment, in dem ein Gast den Shop verlässt oder bestellt.

---

## Die Kapitel

- [Artikel und Freigabe](artikel-freigabe.md): Entwurf, Prüfung, Freigabe und das Vier-Augen-Prinzip
- [Termine und Status](termine.md): die fünf Status, Bestellschluss und was zum Veröffentlichen fehlt
- [Pausen und Tischbindung](pausen-tischbindung.md): mehrere Pausen, ganzer Abend oder jede Pause einzeln
- [Räume und Raumfreigabe](raeume-freigabe.md): parallel oder nacheinander, Schwellen, Tische und Plätze
- [Abholstationen](abholstationen.md): Kapazität je Pause, Tisch oder Station
- [Bestellungen, Zahlung und Storno](bestellungen.md): Status von Bestellung, Buchung und Zahlung, Posteingang, Umsatz
- [Einstellungen auf einen Blick](einstellungen.md): jeder Schalter mit Vorgabe und Ebene
