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
 * Vorher galt es nur in der Oberfläche. Über MCP war es auf drei Wegen zu
 * umgehen: ein freigegebener Artikel liess sich inhaltlich austauschen, ohne
 * dass die Freigabe fiel; die Massenfreigabe winkte auch die eigene
 * Einreichung durch; und ein per Werkzeug angelegter Artikel hatte gar keinen
 * Einreicher, gegen den zu prüfen gewesen wäre.
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
    }

    /** Die Pflicht gilt per Vorgabe – ohne Eintrag ist sie an. */
    private function pflichtAus(): void
    {
        $einstellung = CheckoutSetting::forTeam(1);
        $einstellung->four_eyes_enabled = false;
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
     | Anlegen: wer anlegt, ist der Einreicher
     ------------------------------------------------------------------ */

    public function test_mit_pflicht_gilt_der_anlegende_als_einreicher(): void
    {
        $vorgaben = MenuItem::approvalDefaultsForNew($this->anna, 1);

        $this->assertSame(MenuItem::APPROVAL_REVIEW, $vorgaben['approval_status']);
        $this->assertSame($this->anna->id, $vorgaben['submitted_by']);
    }

    public function test_der_anlegende_kann_seinen_eigenen_artikel_nicht_freigeben(): void
    {
        // Das ist der Zweck der Uebung: Ohne submitted_by griff die Sperre
        // nicht, und ein per Werkzeug angelegter Artikel liess sich vom
        // Urheber selbst durchwinken.
        $artikel = $this->artikel(MenuItem::approvalDefaultsForNew($this->anna, 1));

        $this->assertFalse($artikel->canBeApprovedBy($this->anna));
        $this->assertTrue($artikel->canBeApprovedBy($this->bert));
    }

    public function test_ohne_pflicht_bleibt_es_beim_entwurf(): void
    {
        $this->pflichtAus();

        // Leer heisst: nichts wird aufgedraengt. Der Artikel entsteht als
        // Entwurf, und jeder darf ihn freigeben - wie vor der Aenderung.
        $this->assertSame([], MenuItem::approvalDefaultsForNew($this->anna, 1));
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
}
