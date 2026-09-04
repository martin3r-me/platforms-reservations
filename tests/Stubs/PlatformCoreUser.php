<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * Ein Platzhalter für den User der Plattform.
 *
 * Das Modul hängt nicht an platforms-core (siehe composer.json) – die Klasse
 * kommt im Betrieb aus der Wirts-App. In der Testbasis gibt es sie deshalb
 * nicht, und alles, was am Vier-Augen-Prinzip hängt, war damit ungetestet:
 * canBeApprovedBy() und approvalDefaultsForNew() nehmen einen User entgegen.
 * Genau die Regeln also, bei denen ein Fehler am teuersten ist.
 *
 * Bewusst so klein wie die Tabellen-Stubs in TestCase: ein Anker, kein Nachbau.
 * Die Abfrage auf class_exists() ohne Autoload sorgt dafür, dass die echte
 * Klasse gewinnt, sollte sie doch einmal geladen sein.
 */
if (! class_exists(User::class, false)) {
    class User extends Model
    {
        protected $table = 'users';

        protected $guarded = [];
    }
}
