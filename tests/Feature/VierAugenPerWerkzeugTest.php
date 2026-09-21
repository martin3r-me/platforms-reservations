<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Core\Models\User;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Tests\TestCase;

/**
 * Das Vier-Augen-Prinzip gilt auch über die Werkzeuge.
 *
 * Vorher galt es nur in der Oberfläche: Ein freigegebener Artikel liess sich
 * per PATCH inhaltlich austauschen, ohne dass die Freigabe fiel, und die
 * Massenfreigabe winkte auch die eigene Einreichung durch.
 *
 * Weiter geht das Werkzeug bewusst NICHT. Es stempelt niemanden automatisch
 * als Einreicher und lehnt keine Entwürfe ab. Beides klänge strenger, wäre
 * aber für ein Ein-Personen-Team eine Sackgasse: Wer einreicht, darf nie
 * selbst freigeben – und ein zweiter Mensch, der es könnte, existiert dort
 * nicht. Das Modul kann Team-Grössen nicht sehen; es kennt keine
 * Mitgliedschaften. Eine Pflicht gegen ein Ein-Personen-Team durchzusetzen
 * erzeugt keine Sicherheit, sondern Stillstand. Einreichen ist deshalb ein
 * ausdrücklicher Schritt geblieben (submit.bulk oder die Oberfläche).
 *
 * Geprüft werden die REGELN, nicht die Werkzeugklassen: Deren Verträge liegen
 * in platforms-core, das dieses Modul bewusst nicht als Abhängigkeit führt
 * (siehe composer.json). Ein Nachbau von ToolResult wäre hier das Gegenteil
 * einer Absicherung – er würde bestätigen, was der Nachbau tut, nicht was der
 * Betrieb tut. Die Werkzeuge selbst rufen nur noch diese Regeln auf.
 *
 * Jede Regel steht ZWEIMAL: einmal mit Pflicht und einmal ohne. Ist sie
 * abgeschaltet, muss über die Werkzeuge alles so gehen wie vorher – ein Team,
 * das kein zweites Augenpaar will, soll keins aufgezwungen bekommen.
 */
class VierAugenPerWerkzeugTest extends TestCase
{
    private User $anna;
    private User $bert;
    private MenuCategory $kategorie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->anna = User::create(['name' => 'Anna']);
        $this->bert = User::create(['name' => 'Bert']);

        $this->kategorie = MenuCategory::create(['team_id' => 1, 'name' => 'Getränke']);

        // Ausdrücklich einschalten. Seit dem 04.09.2026 ist die Pflicht per
        // Vorgabe AUS – wer sie will, schaltet sie ein. Ein Test, der sich auf
        // eine Vorgabe verlässt, prüft die Vorgabe statt die Regel.
        $this->pflicht(true);
    }

    private function pflichtAus(): void
    {
        $this->pflicht(false);
    }

    /**
     * Abschalten auf dem echten Weg: Anna beantragt, Bert bestaetigt. Nur so
     * entsteht der Stichtag (four_eyes_changed_at) - pflichtAus() legt bloss
     * den Schalter um und laesst ihn leer.
     */
    private function pflichtAbschalten(): void
    {
        // Im Test passiert alles in derselben Sekunde; dann liegt die
        // Einreichung nicht VOR dem Stichtag und die Sperre greift nicht.
        // Also die Uhr weiterstellen, wie es im Betrieb ohnehin ist: erst
        // eingereicht, spaeter abgeschaltet.
        $this->travel(5)->minutes();

        $einstellung = CheckoutSetting::forTeam(1);
        $einstellung->requestFourEyesOff($this->anna);
        $einstellung->confirmFourEyesOff($this->bert);
    }

    private function pflicht(bool $an): void
    {
        $einstellung = CheckoutSetting::forTeam(1);
        $einstellung->four_eyes_enabled = $an;
        $einstellung->save();
    }

    private function artikel(array $attribute = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'team_id'     => 1,
            'category_id' => $this->kategorie->id,
            'name'        => 'Cola',
            'price'       => 3.20,
            'tax_rate'    => 19,
        ], $attribute));
    }

    /* ------------------------------------------------------------------
     | Ändern: inhaltliche Änderung entwertet die Freigabe
     ------------------------------------------------------------------ */

    public function test_mit_pflicht_muss_nach_einer_aenderung_neu_freigegeben_werden(): void
    {
        $artikel = $this->artikel(['approval_status' => MenuItem::APPROVAL_APPROVED]);

        $this->assertTrue($artikel->requiresReapprovalAfterChange());
    }

    public function test_ohne_pflicht_bleibt_die_freigabe_stehen(): void
    {
        $this->pflichtAus();
        $artikel = $this->artikel(['approval_status' => MenuItem::APPROVAL_APPROVED]);

        // Sonst fiele der Artikel bei jedem korrigierten Tippfehler aus dem
        // Shop, und jemand muesste ihn von Hand wieder hereinholen.
        $this->assertFalse($artikel->requiresReapprovalAfterChange());
    }

    public function test_eine_laufende_pruefung_faellt_auch_ohne_pflicht_zurueck(): void
    {
        $this->pflichtAus();
        $artikel = $this->artikel(['approval_status' => MenuItem::APPROVAL_REVIEW]);

        // Sonst entsteht eine Sackgasse: Ein Artikel, der beim Abschalten
        // gerade in Pruefung war, behaelt die Pflicht (canBeApprovedBy), sein
        // Einreicher darf ihn nie freigeben - und die Oberflaeche bietet fuer
        // „in Pruefung" nur „Freigeben" an. Wer allein pflegt, kaeme nicht
        // mehr heran.
        $this->assertTrue($artikel->requiresReapprovalAfterChange());
    }

    public function test_ein_entwurf_hat_nichts_zu_verlieren(): void
    {
        $artikel = $this->artikel(['approval_status' => MenuItem::APPROVAL_DRAFT]);

        $this->assertFalse($artikel->requiresReapprovalAfterChange());
    }

    public function test_was_als_inhalt_zaehlt(): void
    {
        // Diese drei sind bewusst DRAUSSEN: Einen Artikel auszublenden,
        // umzusortieren oder anders zu takten aendert nichts an dem, was der
        // Gast bekommt.
        $this->assertNotContains('available', MenuItem::CONTENT_FIELDS);
        $this->assertNotContains('category_id', MenuItem::CONTENT_FIELDS);
        $this->assertNotContains('holding_class_id', MenuItem::CONTENT_FIELDS);

        // Und diese drinnen - Preis und Kennzeichnung sind genau das, wofuer
        // es das zweite Augenpaar gibt.
        foreach (['name', 'price', 'tax_rate', 'min_age', 'is_alcoholic', 'is_bundle'] as $feld) {
            $this->assertContains($feld, MenuItem::CONTENT_FIELDS);
        }
    }

    /* ------------------------------------------------------------------
     | Freigeben: nicht die eigene Einreichung
     ------------------------------------------------------------------ */

    public function test_mit_pflicht_ist_die_eigene_einreichung_gesperrt(): void
    {
        $artikel = $this->artikel();
        $artikel->submitForReview($this->anna);

        $this->assertFalse($artikel->canBeApprovedBy($this->anna));
        $this->assertTrue($artikel->canBeApprovedBy($this->bert));
    }

    public function test_ohne_pflicht_darf_auch_die_eigene_einreichung_durch(): void
    {
        $this->pflichtAus();

        $artikel = $this->artikel();
        $artikel->submitForReview($this->anna);

        $this->assertTrue($artikel->canBeApprovedBy($this->anna));
    }

    /* ------------------------------------------------------------------
     | Warum gesperrt? Der Grund entscheidet ueber den naechsten Schritt
     ------------------------------------------------------------------ */

    public function test_mit_pflicht_nennt_die_sperre_die_pflicht(): void
    {
        $artikel = $this->artikel();
        $artikel->submitForReview($this->anna);

        $this->assertSame(MenuItem::HINDERNIS_PFLICHT, $artikel->freigabeHindernis($this->anna));
        $this->assertNull($artikel->freigabeHindernis($this->bert));
    }

    public function test_nach_dem_abschalten_nennt_die_sperre_den_stichtag(): void
    {
        // Der Fall aus dem Betrieb: Der Artikel lag in Pruefung, DANN wurde die
        // Pflicht abgeschaltet. Er bleibt gesperrt - aber aus einem anderen
        // Grund, und mit einem anderen Ausweg: zuruecknehmen, neu einreichen.
        //
        // Ohne diese Unterscheidung las der Einreicher "das muss ein anderer
        // Mensch tun", obwohl niemand mehr darauf wartete.
        $artikel = $this->artikel();
        $artikel->submitForReview($this->anna);

        $this->pflichtAbschalten();

        $this->assertSame(MenuItem::HINDERNIS_STICHTAG, $artikel->fresh()->freigabeHindernis($this->anna));
        $this->assertFalse($artikel->fresh()->canBeApprovedBy($this->anna));
    }

    public function test_nach_dem_abschalten_neu_eingereicht_ist_frei(): void
    {
        $artikel = $this->artikel();
        $artikel->submitForReview($this->anna);

        $this->pflichtAbschalten();

        // Der Weg aus der Sackgasse: zurueck auf Entwurf, neu einreichen.
        $artikel->resetApproval();
        $artikel->fresh()->submitForReview($this->anna);

        $this->assertNull($artikel->fresh()->freigabeHindernis($this->anna));
        $this->assertTrue($artikel->fresh()->canBeApprovedBy($this->anna));
    }

    /* ------------------------------------------------------------------
     | Einreichen im Bund
     ------------------------------------------------------------------ */

    public function test_einreichen_macht_den_aufrufenden_zum_einreicher(): void
    {
        $artikel = $this->artikel();
        $artikel->submitForReview($this->anna);

        // Damit ist die Kette geschlossen: Anna reicht ein, Bert gibt frei.
        // Ohne ein Massen-Einreichen waere das nach einem Import 200-mal von
        // Hand - und niemand haette es getan.
        $this->assertSame(MenuItem::APPROVAL_REVIEW, $artikel->fresh()->approval_status);
        $this->assertFalse($artikel->fresh()->canBeApprovedBy($this->anna));
        $this->assertTrue($artikel->fresh()->canBeApprovedBy($this->bert));
    }

    public function test_einreichen_loescht_eine_alte_freigabe_mit(): void
    {
        $artikel = $this->artikel([
            'approval_status' => MenuItem::APPROVAL_APPROVED,
            'approved_by'     => $this->bert->id,
            'approved_at'     => now(),
        ]);

        $artikel->submitForReview($this->anna);

        // Sonst stuende an einem Artikel, der gerade erst eingereicht wurde,
        // noch eine Freigabe von vorgestern.
        $this->assertNull($artikel->fresh()->approved_by);
        $this->assertNull($artikel->fresh()->approved_at);
    }

    public function test_ohne_eintrag_gilt_die_pflicht_nicht(): void
    {
        // Die Vorgabe war bis zum 04.09.2026 AN, und das war eine Falle:
        // Einschalten darf jeder allein, Abschalten braucht zwei Menschen. Wer
        // allein pflegt, kam nie wieder heraus - aus einer Pflicht, die er
        // sich nie ausgesucht hatte.
        CheckoutSetting::forTeam(1)->delete();

        $this->assertFalse(CheckoutSetting::forTeam(1)->fourEyesRequired());
    }
}
