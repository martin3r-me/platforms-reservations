---
title: Pausen und Tischbindung
order: 4
---

# Pausen und Tischbindung

Die meisten Veranstaltungen haben eine Pause. Manche haben zwei. PausePlus behandelt beides gleich, blendet aber alles aus, was bei einer Pause keinen Sinn hat.

---

## Pausen am Termin

Jede Pause hat einen Namen, die Uhrzeiten sind optional. Ein Termin braucht mindestens eine Pause, um veröffentlicht zu werden.

Hat der Termin **eine** Pause, sieht der Gast im Shop keinen Schritt „Wann?". Erst ab der zweiten Pause wird gefragt.

Bestellt ein Gast für mehrere Pausen, entsteht **eine Bestellung mit einer Zahlung** und je Pause eine eigene Buchung mit eigenem Warenkorb. Sekt zur ersten Pause und Kaffee zur zweiten ist der Normalfall, nicht die Ausnahme.

---

## Die Tischbindung

Bei mehreren Pausen stellt sich eine Frage, die das Haus beantworten muss: Gehört der Tisch dem Gast den ganzen Abend, oder wird er zwischen den Pausen neu vergeben?

| Modus | Bedeutung | Folge für die Räume |
|---|---|---|
| **Ganzer Abend** (Vorgabe) | Wer in einer Pause am Tisch sitzt, belegt den Platz in allen Pausen | Räume öffnen für den Abend |
| **Jede Pause einzeln** | Jede Pause wird getrennt vergeben, der Saal wird zwischen den Pausen neu verkauft | Räume öffnen je Pause, derselbe Raum kann in Pause 1 offen und in Pause 2 zu sein |

Die Vorgabe ist **Ganzer Abend**, die strengere Lesart. Sie verkauft nie einen Platz doppelt. Wer die Restplätze zwischen den Pausen neu anbieten will, stellt auf „Jede Pause einzeln".

Das Feld steht am Termin und erscheint erst, wenn der Termin zwei Pausen hat. Bleibt es leer, gilt die Team-Vorgabe aus den Einstellungen.

**Umstellen bei bestehenden Buchungen:** Wechselt ein Termin von „Jede Pause einzeln" auf „Ganzer Abend", kann ein Tisch plötzlich überbelegt sein, weil zwei Parteien in verschiedenen Pausen denselben Tisch haben. Das Formular warnt davor und nennt die betroffenen Tische. Die Gegenrichtung braucht keine Warnung, sie macht nur Plätze frei.

---

## Wie Plätze gezählt werden

Bei „Ganzer Abend" gibt es eine Falle, die PausePlus vermeidet: Bestellt eine Gruppe von vier Personen für beide Pausen, wären es naiv gezählt acht Plätze. Es sind aber vier, dieselben Menschen sitzen zweimal am selben Tisch.

Deshalb wird **nach Parteien** gezählt:

- Eine Partei ist eine Bestellung.
- Innerhalb einer Pause werden die Personen einer Partei zusammengezählt.
- Über die Pausen hinweg zählt die **größte** dieser Zahlen. Vier Personen in Pause 1 und zwei in Pause 2 belegen vier Plätze.
- Stornierte und nicht erschienene Buchungen zählen nie.

Das hat eine sichtbare Folge im Dashboard: **Belegt ist nicht dasselbe wie bestellt.** Die Auslastung zählt Plätze, die Küche zählt Bestellungen je Pause. Ein Tisch mit vier belegten Plätzen kann in Pause 2 nur zwei Bestellungen haben.

---

## Wo Sie das finden

- **Pausen** anlegen und benennen: im Terminformular
- **Tischbindung** je Termin: im Terminformular, ab der zweiten Pause
- **Team-Vorgabe** der Tischbindung: *Einstellungen*, Abschnitt *Termine*
