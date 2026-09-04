---
title: Bestellungen, Zahlung und Storno
order: 7
---

# Bestellungen, Zahlung und Storno

Eine Bestellung ist der Einkauf eines Gastes: **eine Zahlung**, **je Pause eine Buchung** mit Ort und Personenzahl, und in jeder Buchung die bestellten Positionen. Drei Dinge haben deshalb einen eigenen Status: die Bestellung, jede Buchung und die Zahlung.

---

## Die Status im Überblick

### Buchung (je Pause)

| Status | Bedeutung |
|---|---|
| **Ausstehend** | Der Gast hat bestellt, die Zahlung ist noch nicht eingegangen. Die Plätze sind reserviert |
| **Bestätigt** | Die Zahlung ist eingegangen, oder die Buchung wurde im Backoffice angelegt |
| **Storniert** | Die Buchung ist aufgehoben, die Plätze sind frei |
| **Nicht erschienen** | Der Gast kam nicht. Wird am Abend von Hand gesetzt |
| **Abgeschlossen** | Der Gast war da und wurde bedient |

### Bestellung

| Status | Bedeutung |
|---|---|
| **Ausstehend** | wartet auf die Zahlung |
| **Bestätigt** | bezahlt |
| **Stornoanfrage** | der Gast hat storniert, das Haus muss noch freigeben |
| **Storniert** | aufgehoben, gegebenenfalls erstattet |

### Zahlung

Die Zahlung läuft über Mollie. PausePlus übernimmt den Mollie-Status weitgehend wörtlich: offen, ausstehend, bezahlt, gescheitert, abgebrochen, abgelaufen, und ergänzt zwei eigene: **erstattet** und **rückbelastet**.

Eine Ausnahme gibt es: Ist Geld schon zurückgegangen, wird der Status **nicht** wieder auf *bezahlt* gesetzt. Mollie führt eine erstattete Zahlung nämlich weiterhin als bezahlt – zurück geht sie dort nur über einen eigenen Betrag. Ohne diese Ausnahme stünde im Backoffice „bezahlt" an einer Bestellung, deren Geld längst zurück ist.

---

## Der normale Weg

1. **Der Gast bestellt.** Bestellung und Buchungen entstehen als *Ausstehend*. Die Plätze sind ab jetzt reserviert. Der Gast wird zur Zahlung weitergeleitet.
2. **Die Zahlung geht ein.** Bestellung und Buchungen werden *Bestätigt*. Genau jetzt geht die **Bestätigungsmail** heraus, ein einziges Mal je Bestellung, und, wenn eingerichtet, der **Bon** in die Küche.
3. **Die Zahlung scheitert, läuft ab oder wird abgebrochen.** Bestellung und Buchungen werden *Storniert*, die Plätze sind wieder frei. Der Vorgang erscheint im Posteingang als „Zahlung gescheitert".
4. **Der Gast bricht im Bezahlfenster ab und Mollie meldet nichts.** Dann räumt ein nächtlicher Lauf auf: Was länger als **24 Stunden** auf eine Zahlung wartet, wird storniert und gibt seine Plätze frei. Bestellungen **ohne** Zahlung – Barzahlung, Buchung im Backoffice – bleiben dabei unberührt; die werden vor Ort abgerechnet.

Die Bestätigungsmail braucht einen eingerichteten Absender in den Einstellungen. Ohne Absender wird nicht versendet.

**Buchung im Backoffice:** Wer von Hand bucht, erzeugt eine sofort *bestätigte* Buchung mit Zahlungsart „vor Ort". Bestellschluss und Raumfreigabe werden dabei bewusst nicht geprüft.

---

## Am Veranstaltungsabend

In der Buchungsliste des Termins lassen sich Buchungen umsetzen:

| Aktion | Rückfrage | Wirkung |
|---|---|---|
| **Abschließen** | nein | Buchung ist *Abgeschlossen* |
| **Nicht erschienen** | ja | Buchung ist *Nicht erschienen*, zählt nicht mehr als belegt |
| **Stornieren** | ja | Buchung ist *Storniert* |
| **Zurücknehmen** | ja | Buchung geht zurück auf *Bestätigt*, wenn die Bestellung bezahlt ist, sonst auf *Ausstehend* |

Das Zurücknehmen zielt bewusst nicht immer auf *Bestätigt*: Eine unbezahlte Buchung als bestätigt zu führen, würde einen Zahlungseingang behaupten, den es nicht gab.

**Abend abschließen** setzt alle *bestätigten* Buchungen des Termins auf *Abgeschlossen*. Ausstehende und nicht erschienene bleiben, wie sie sind. Das geschieht bewusst von Hand, nicht automatisch, damit das Haus entscheidet, wann der Abend zu ist.

Statuswechsel am Abend lösen **keinen zweiten Bon** aus. Nur der Übergang auf *Bestätigt* druckt.

---

## Storno

### Durch den Gast

Ob Gäste selbst stornieren dürfen, ist eine Einstellung, standardmäßig **aus**. Ist sie an, gilt:

- Storniert werden kann nur eine **bezahlte** Bestellung.
- Die **Frist** wird in Stunden vor dem Veranstaltungstag gerechnet, gezählt ab 0 Uhr des Termindatums. 24 Stunden heißt: bis zum Vortag 0 Uhr. Ohne Frist ist Storno bis zum Termin möglich.
- Mit **Freigabepflicht** wird aus dem Storno eine *Stornoanfrage*, die im Posteingang landet. Das Haus gibt frei oder lehnt ab. Abgelehnt heißt: die Bestellung ist wieder *Bestätigt*.
- Ohne Freigabepflicht wird sofort storniert und erstattet.

### Durch das Haus

Das Haus kann jede bestätigte Bestellung stornieren, jederzeit. Alle Buchungen der Bestellung werden storniert, auch solche, die schon *Nicht erschienen* oder *Abgeschlossen* waren.

### Bei einem abgesagten Termin

Eine Absage stoppt den Verkauf, sie erstattet aber **nichts von selbst**. Das ist Absicht: Ein Haus sagt ab und verlegt, erstattet in Gutscheinen oder verhandelt einzeln.

Am abgesagten Termin steht dafür im Menü **Bestellungen erstatten**. Die Rückfrage nennt die **Zahl der Bestellungen und die Summe**, und der Knopf trägt den Betrag. Ausgelöst wird erst damit. Danach laufen alle Stornos nacheinander im Hintergrund: Plätze frei, Geld zurück, Storno-Mail an jeden Gast. Bleibt eine Erstattung bei Mollie hängen, laufen die übrigen trotzdem durch.

Unbezahlte Bestellungen zählt die Rückfrage getrennt – da wurde nichts abgebucht.

### Die Storno-Mail

Bei **jedem** Storno bekommt der Gast eine Stornobestätigung: beim Selbst-Storno, beim Storno durch das Haus und bei der Absage eines Termins. Darin steht, was storniert wurde, und – wenn ein Grund mitgegeben wurde – warum.

Über die Rückerstattung schreibt die Mail nur, wenn wirklich Geld zurückgeht. Wurde nie etwas abgebucht, steht das so drin, statt den Gast auf eine Gutschrift warten zu lassen.

Wie die Bestätigungsmail braucht sie einen eingerichteten Absender. Ohne Absender wird nicht versendet – das Storno selbst passiert trotzdem.

### Rückerstattung

Bei einer bezahlten Zahlung wird über Mollie **automatisch erstattet**. Ist die Zahlung nicht bezahlt oder schon vollständig erstattet, passiert nichts. Erstattet wird immer nur der **noch offene Rest**: Wurde vorher schon ein Teil zurückgegeben, geht nur die Differenz zurück und nie mehr, als eingenommen wurde. Das Ergebnis steht am Vorgang.

### Wenn außerhalb von PausePlus erstattet wird

**Erstatten Sie über die Oberfläche, nicht im Mollie-Dashboard.** Der Grund ist nicht Bequemlichkeit: Nur so werden Buchung, Laufzettel und Umsatz mitgeführt.

Passiert es trotzdem – oder bucht die Bank eine Zahlung zurück –, merkt PausePlus es inzwischen und zieht nach:

| Fall | Was passiert |
|---|---|
| **Voller Betrag zurück** | Bestellung und Buchungen werden storniert, Plätze frei, Storno-Mail geht raus |
| **Rückbelastung durch die Bank** | dasselbe, die Zahlung steht danach auf *rückbelastet* |
| **Nur ein Teil zurück** | nichts wird storniert. Welche Position gemeint war, weiß niemand, und wer 8 von 24 € zurückbekommt, soll nicht vor einem leeren Tisch stehen. Der Betrag steht im Detailfenster der Buchung, mit der Bitte zu prüfen, was noch geliefert wird |

---

## Der Posteingang

Der Posteingang sammelt, was das Haus sehen sollte: **neue bezahlte Bestellungen**, **Stornoanfragen**, **Stornierungen** und **gescheiterte Zahlungen**. Ein Vorgang gilt als ungesehen, bis ihn jemand öffnet. Der Zähler am Menüpunkt zeigt die ungesehenen.

---

## Umsatz

Als Umsatz zählen Buchungen mit Status **Bestätigt** und **Abgeschlossen**. **Nicht erschienen** zählt nur, wenn die Einstellung „Nicht erschienene Gäste zählen als Umsatz" gesetzt ist, standardmäßig nicht.

Diese eine Definition gilt überall: Umsatz, Startseite, Artikelauswertung, Export und DATEV. Es gibt keine Auswertung, die anders zählt.

---

## Laufende Bestellwege

Das Veranstaltungs-Dashboard und die Startseite zeigen unter „Gerade im Bestellweg", wie viele Gäste im Shop gerade dabei sind. Dazu drei Dinge, die man wissen sollte:

- Ein laufender Bestellweg **hält keinen Platz frei**. Erst die Bestellung reserviert.
- Gespeichert werden nur Schritt, Personenzahl und Warenkorb, **keine Personendaten**. Der Warenkorb ist kein erwarteter Umsatz.
- Ein Vorgang gilt drei Minuten nach dem letzten Lebenszeichen als beendet.

Die Auswertung **Bestellwege** zählt, wie viele Bestellwege mit einer Bestellung enden und in welchem Schritt die anderen abgebrochen werden. Sie zählt **ab ihrer Einführung**, nicht rückwirkend, und ein Abbruch wird verbucht, wenn der Vorgang aufgeräumt wird. Auf einer ruhigen Seite hinkt die Zahl deshalb bis zum nächsten Öffnen der Auswertung hinterher.

---

## Wo Sie das finden

- **Buchungen** eines Termins umsetzen und Abend abschließen: Veranstaltungs-Dashboard
- **Alle Buchungen** hausweit: Menüpunkt *Alle Buchungen*
- **Stornoanfragen** freigeben: Menüpunkt *Posteingang*
- **Storno-Regeln, Umsatzdefinition, Bestätigungsmail, Bon-Druck, Mollie**: *Einstellungen*
