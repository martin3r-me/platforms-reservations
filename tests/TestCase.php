<?php

namespace Platform\Reservation\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Basis der Testbasis dieses Moduls.
 *
 * Läuft gegen ein nacktes Laravel (Testbench) auf SQLite im Arbeitsspeicher.
 * Der ReservationServiceProvider wird BEWUSST NICHT registriert: Er zieht
 * Routen, Ansichten und die Anbindung an die Plattform nach sich, und das
 * Gerechnete – Modelle und Dienste – braucht davon nichts. Ein Testlauf, der an
 * einer Route scheitert, sagt nichts über die Kapazitätsrechnung aus.
 *
 * Ausgeführt werden die ECHTEN Migrationen aus database/migrations. Ein
 * nachgebautes Schema wäre wertlos: Es würde genau die Spalten haben, die der
 * Test erwartet, statt die, die im Betrieb existieren.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Fremdschlüssel wirklich durchsetzen.
     *
     * SQLite prüft sie nur, wenn man es verlangt – ohne dieses PRAGMA laufen
     * cascadeOnDelete und nullOnDelete ins Leere, und ein Test, der genau das
     * prüft, ist grün, während es im Betrieb (MySQL) anders aussähe. Das ist
     * die schlimmste Sorte Test: einer, der eine Zusage gibt, die er nicht
     * einlöst.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->plattformStubs();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Die zwei Tabellen der Plattform, an denen das Modul per Fremdschlüssel hängt.
     *
     * Absichtlich minimal – sie sind Anker für die Fremdschlüssel, kein Nachbau
     * von Platform\Core. Wächst diese Liste, ist das ein Hinweis darauf, dass
     * das Modul sich stärker an die Plattform bindet, als es sollte.
     */
    protected function plattformStubs(): void
    {
        Schema::create('teams', function (Blueprint $tabelle) {
            $tabelle->id();
            $tabelle->string('name')->nullable();
            $tabelle->timestamps();
        });

        Schema::create('users', function (Blueprint $tabelle) {
            $tabelle->id();
            $tabelle->string('name')->nullable();
            $tabelle->timestamps();
        });

        DB::table('teams')->insert(['id' => 1, 'name' => 'Testhaus']);
    }
}
