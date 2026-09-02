<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN directly.
        // Recreate the table with table_id nullable.
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->foreignId('table_id')->nullable()->change();
        });
    }

    /**
     * Bewusst leer – diese Änderung lässt sich nicht zurücknehmen.
     *
     * Hier stand `nullable(false)->change()`. Das war eine Zusage, die die
     * Migration nicht halten kann: Eine Buchung ohne Tisch ist inzwischen ein
     * regulärer Zustand – ihr Tisch wurde gelöscht (`nullOnDelete`), oder sie
     * gehört seit den Abholstationen zu einer Station statt zu einem Tisch.
     * Sobald eine einzige solche Zeile existiert, scheitert das Zurücksetzen
     * an der NOT-NULL-Bedingung.
     *
     * Aufgefallen ist es beim Bauen der Stationen: Die Testbasis rollt nach
     * jedem Test zurück, und der erste Test mit einer Abholbuchung brachte die
     * ganze Datei zu Fall – mit einer Fehlermeldung, die auf die neue Migration
     * zeigte statt auf diese hier.
     *
     * Ein down(), das im Ernstfall abbricht, ist schlechter als eines, das
     * sagt, dass es nichts tut.
     */
    public function down(): void
    {
        //
    }
};
