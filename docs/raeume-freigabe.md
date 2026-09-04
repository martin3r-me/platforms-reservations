---
title: Räume und Raumfreigabe
order: 5
---

# Räume und Raumfreigabe

Ein Termin bekommt einen oder mehrere Räume, das sind die Tischpläne des Hauses. Die Raumfreigabe entscheidet, wann der Gast welchen Raum wählen darf. Sie ist das Werkzeug, mit dem das Haus einen Saal füllt, bevor der nächste aufgemacht wird.

---

## Zwei Modi

| Modus | Bedeutung |
|---|---|
| **Parallel** | Alle Räume sind von Anfang an offen, der Gast wählt frei |
| **Nacheinander** (sequenziell) | Der erste Raum ist offen. Jeder weitere öffnet, sobald der vorherige zu einem bestimmten Anteil gefüllt ist |

Der Modus ist eine Pflichtangabe am Termin. Für neue Termine gilt die Team-Vorgabe aus den Einstellungen, standardmäßig **Parallel**.

---

## Die Kette bei „Nacheinander"

Die Räume stehen am Termin in einer Reihenfolge. Jeder Raum hat eine **Schwelle in Prozent**, die sich auf Plätze bezieht, nicht auf Tische.

- Der erste Raum ist immer offen.
- Der nächste Raum öffnet, wenn der vorherige zu mindestens seiner Schwelle gefüllt ist. Die Schwelle eines Raums entscheidet also über den **Raum danach**. Am letzten Raum ist sie ohne Wirkung.
- Die Vorgabe ist **100 %**, die strengste Auslegung. Sie öffnet einen Raum nie zu früh. Wer nicht auf die letzten Restplätze warten will, setzt 85 oder 90.

Das Formular erklärt den gewählten Wert im Satz, etwa „Ab 100 % von 16 Plätzen öffnet ROSSINI".

### Von Hand öffnen oder schließen

Jeder Raum lässt sich am Termin von Hand **öffnen** oder **schließen**. Das schlägt die Kette:

- Ein von Hand geöffneter Raum ist offen, egal wie voll der vorherige ist.
- Ein von Hand geschlossener Raum bleibt zu. Die Kette **überspringt** ihn, damit der Raum dahinter nicht für immer gesperrt bleibt.

### Je Pause gerechnet

Bei der Tischbindung „Jede Pause einzeln" wird die Freigabe je Pause berechnet. Derselbe Raum kann in Pause 1 schon offen und in Pause 2 noch zu sein. Bei „Ganzer Abend" öffnen die Räume für den Abend. Siehe [Pausen und Tischbindung](pausen-tischbindung.md).

---

## Was der Gast sieht

Gesperrte Räume werden im Shop **angezeigt, mit Grund**, etwa „öffnet, sobald der Große Saal zu 100 % gefüllt ist" oder „von Hand geschlossen". So versteht der Gast, warum er noch nicht wählen kann. Wer das nicht will, schaltet in den Einstellungen ab, dass gesperrte Räume gezeigt werden. Dann fehlen sie einfach.

Versucht ein Gast trotzdem, in einen gesperrten Raum zu bestellen, etwa weil er die Seite lange offen hatte, lehnt der Server die Bestellung ab. Das Backoffice ist davon ausgenommen: Wer von Hand bucht, darf in jeden Raum.

---

## Hinweise im Dashboard

Ab **85 % Auslastung** über alle offenen Räume schlägt das Veranstaltungs-Dashboard etwas vor: den nächsten Raum der Kette öffnen oder, wenn es keinen mehr gibt, einen weiteren Tischplan anhängen. Ein so angehängter Raum ist sofort offen. Die 85 % sind fest und keine Einstellung.

---

## Tische und Plätze

Tische sind **teilbar**: Ein Tisch mit acht Plätzen kann zwei Parteien mit je vier Personen aufnehmen. Gezählt wird in Plätzen. Ein Tisch ist frei, teilweise belegt oder voll, und die Farbe im Plan zeigt das.

Eine Gruppe muss in die **freien Plätze** eines Tisches passen. Zwei Ausnahmen lassen sich einstellen:

| Einstellung | Wirkung |
|---|---|
| **Weiche Tischkapazität** | Eine Gruppe darf einen **komplett leeren** Tisch überbelegen, etwa sechs Personen an einen Vierertisch. Ein Tisch, an dem schon jemand sitzt, bleibt hart begrenzt |
| **Obergrenze am leeren Tisch** | Bis zu welcher Gruppengröße die weiche Kapazität gilt. Leer heißt unbegrenzt |

„Leer" heißt bei der Tischbindung „Ganzer Abend": in keiner Pause belegt.

### Gesperrte Tische

Einzelne Tische lassen sich **je Termin** sperren, etwa weil dort die Technik steht. Ein gesperrter Tisch hat für diesen Termin keine freien Plätze und ist im Shop nicht wählbar.

### Zwei Gäste, ein letzter Platz

Bestellen zwei Gäste gleichzeitig den letzten Platz an einem Tisch, bekommt ihn genau einer. Der andere erhält die Meldung, dass der Tisch voll ist, und es bleibt keine halbe Bestellung zurück. Bei Abholstationen ist das bewusst anders, siehe [Abholstationen](abholstationen.md).

---

## Wo Sie das finden

- **Räume und Reihenfolge, Schwellen, von Hand auf oder zu, gesperrte Tische**: im Terminformular
- **Raum öffnen oder anhängen** am Abend: im Veranstaltungs-Dashboard
- **Vorgabe für neue Termine, gesperrte Räume zeigen, weiche Tischkapazität, Obergrenze**: *Einstellungen*, Abschnitt *Termine*
