<?php

namespace App\Tests\Formation\Parcours;

use App\Formation\Contenu\Catalogue;
use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Parcours\Parcours;
use App\Formation\Parcours\StatutBloc;
use App\Formation\Progression\ProgressionStore;
use App\Tests\Formation\FabriqueLancement;
use PHPUnit\Framework\TestCase;

/**
 * Ordre du catalogue de test : rappel-a, s1-preparation, s1-demonstration, s1-exercice,
 * s2-preparation, s2-exercice (durées 15, 15, 15, 60, 15, 30).
 */
class ParcoursTest extends TestCase
{
    private string $chemin;
    private ProgressionStore $store;
    private Catalogue $catalogue;
    private Parcours $parcours;

    protected function setUp(): void
    {
        $this->chemin = sys_get_temp_dir().'/parcours-'.bin2hex(random_bytes(4)).'.sqlite';
        $this->store = new ProgressionStore($this->chemin);
        $this->catalogue = (new ChargeurCatalogue(__DIR__.'/../fixtures/contenu-mini', __DIR__.'/../../..'))->charger();
        $this->parcours = new Parcours($this->catalogue, $this->store);
    }

    protected function tearDown(): void
    {
        if (is_file($this->chemin)) {
            unlink($this->chemin);
        }
    }

    public function testAuDepartLePremierBlocEstEnCoursEtLesAutresVerrouilles(): void
    {
        self::assertSame('rappel-a', $this->parcours->blocCourant()->id);
        self::assertSame(StatutBloc::EnCours, $this->parcours->statut('rappel-a'));
        self::assertSame(StatutBloc::Verrouille, $this->parcours->statut('s1-preparation'));
        self::assertSame(StatutBloc::Verrouille, $this->parcours->statut('s2-exercice'));
    }

    public function testUnBlocTermineEstFaitEtLeSuivantDevientCourant(): void
    {
        $this->faire('rappel-a');

        self::assertSame(StatutBloc::Fait, $this->parcours->statut('rappel-a'));
        self::assertSame('s1-preparation', $this->parcours->blocCourant()->id);
    }

    public function testUneSeanceDe15MinutesContientUnSeulBloc(): void
    {
        self::assertSame(['rappel-a'], $this->idsDeSeance());
    }

    public function testUneSeanceDe30MinutesEnchaineDeuxBlocs(): void
    {
        $this->store->changerDuree(30);

        self::assertSame(['rappel-a', 's1-preparation'], $this->idsDeSeance());
    }

    public function testLExerciceClotLaSeance(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $this->store->changerDuree(60);

        // L'exercice (60 min) est seul dans la séance, et le bloc suivant n'y est pas ajouté.
        self::assertSame(['s1-exercice'], $this->idsDeSeance());
    }

    public function testUnExerciceSeulDepasseLeBudgetMaisReste(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');

        self::assertSame(['s1-exercice'], $this->idsDeSeance());
    }

    public function testAvec15MinutesLaFicheNEstPasSuivieDeLExercice(): void
    {
        // Courant = s2-preparation (15 min), suivi de s2-exercice (60 min) : le budget de 15 min est épuisé.
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration', 's1-exercice');

        self::assertSame(['s2-preparation'], $this->idsDeSeance());
    }

    public function testUnExerciceQuiTientExactementDansLeBudgetSAjouteApresLesAutresBlocs(): void
    {
        // Courant = s2-preparation (15 min), suivi de s2-exercice (30 min) : 15 + 30 = 45, le budget est exactement épuisé.
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration', 's1-exercice');
        $this->store->changerDuree(45);

        self::assertSame(['s2-preparation', 's2-exercice'], $this->idsDeSeance());
    }

    public function testUneSeanceDe45MinutesEnchaineTroisBlocsSansLExerciceTropLong(): void
    {
        $this->store->changerDuree(45);

        // 15 + 15 + 15 = 45 : l'exercice (60 min) ne tient pas, il ouvrira la séance suivante.
        self::assertSame(['rappel-a', 's1-preparation', 's1-demonstration'], $this->idsDeSeance());
    }

    public function testLaSeanceSignaleUnExerciceDejaEssaye(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $this->store->changerDuree(60);
        self::assertFalse($this->parcours->seanceDuJour()->exerciceEnCours);

        $this->store->enregistrerLancement(FabriqueLancement::creer('s1-exercice', false));

        self::assertTrue($this->parcours->seanceDuJour()->exerciceEnCours);
    }

    public function testQuandToutEstFaitLeParcoursEstTermine(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration', 's1-exercice', 's2-preparation', 's2-exercice');

        self::assertNull($this->parcours->blocCourant());
        $seance = $this->parcours->seanceDuJour();
        self::assertSame([], $seance->blocs);
        self::assertTrue($seance->parcoursTermine);
    }

    public function testOnNePeutPasTerminerUnBlocFutur(): void
    {
        self::assertFalse($this->parcours->peutTerminer('s1-demonstration'));

        $this->expectException(\LogicException::class);
        $this->parcours->terminer('s1-demonstration');
    }

    public function testOnNePeutPasTerminerUnExercice(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');

        self::assertSame('s1-exercice', $this->parcours->blocCourant()->id);
        self::assertFalse($this->parcours->peutTerminer('s1-exercice'));

        $this->expectException(\LogicException::class);
        $this->parcours->terminer('s1-exercice');
    }

    public function testOnNePeutPasTerminerUnBlocDejaFait(): void
    {
        $this->faire('rappel-a');

        self::assertFalse($this->parcours->peutTerminer('rappel-a'));
        $this->expectException(\LogicException::class);
        $this->parcours->terminer('rappel-a');
    }

    public function testLeBlocCourantSeTermineEtDonneLaMainAuSuivant(): void
    {
        self::assertTrue($this->parcours->peutTerminer('rappel-a'));

        $this->parcours->terminer('rappel-a');

        self::assertSame(StatutBloc::Fait, $this->parcours->statut('rappel-a'));
    }

    public function testUneFichePreparationIncompleteBloqueLaFinDuBloc(): void
    {
        $this->faire('rappel-a');

        self::assertFalse($this->parcours->peutTerminer('s1-preparation'), 'aucune fiche');

        $this->store->enregistrerFiche('s1-preparation', ['routes' => 'GET /a']);
        self::assertFalse($this->parcours->peutTerminer('s1-preparation'), 'champ « ordre » manquant');

        $this->store->enregistrerFiche('s1-preparation', ['routes' => 'GET /a', 'ordre' => '1. Contrôleur']);
        self::assertTrue($this->parcours->peutTerminer('s1-preparation'));
    }

    public function testUneFicheAvecUnChampFaitDEspacesNEstPasComplete(): void
    {
        $this->store->enregistrerFiche('s1-preparation', ['routes' => 'GET /a', 'ordre' => " \t\n "]);
        self::assertFalse($this->parcours->ficheComplete('s1-preparation'));

        $this->store->enregistrerFiche('s1-preparation', ['routes' => 'GET /a', 'ordre' => ' 1. ']);
        self::assertTrue($this->parcours->ficheComplete('s1-preparation'));
    }

    public function testQuatreRougesDeclenchentLAide(): void
    {
        for ($i = 1; $i <= 3; ++$i) {
            $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', false));
        }
        self::assertFalse($this->parcours->besoinDAide('s1-exercice'));

        $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', false));
        self::assertTrue($this->parcours->besoinDAide('s1-exercice'));

        $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', true));
        self::assertFalse($this->parcours->besoinDAide('s1-exercice'));
    }

    public function testUnRougeNeMarquePasLExerciceFait(): void
    {
        $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', false));

        self::assertFalse($this->store->estFait('s1-exercice'));
        self::assertCount(1, $this->store->lancements('s1-exercice'));
    }

    public function testUnResultatNEstEnregistreQuePourUnExercice(): void
    {
        $this->expectException(\LogicException::class);

        $this->parcours->enregistrerResultat('rappel-a', FabriqueLancement::creer('rappel-a', true));
    }

    public function testLeBadgeDuSujetEstObtenuAuPremierVert(): void
    {
        self::assertSame([], $this->parcours->badgesObtenus());

        $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', false));
        self::assertSame([], $this->parcours->badgesObtenus());

        $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', true));
        self::assertSame(['badge-s1'], $this->idsDeBadges());
    }

    public function testLeBadgeDeNiveauArriveAvecLeDernierBadgeDExercice(): void
    {
        $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', true));
        self::assertNotContains('niveau-1', $this->idsDeBadges());

        $this->parcours->enregistrerResultat('s2-exercice', FabriqueLancement::creer('s2-exercice', true));
        self::assertSame(['badge-s1', 'badge-s2', 'niveau-1'], $this->idsDeBadges());
    }

    public function testLAvancementDuNiveauCompteLesBlocsFaitsSurLeTotal(): void
    {
        $niveau = $this->catalogue->niveaux()[0];
        self::assertSame(['faits' => 0, 'total' => 6], $this->parcours->avancementDuNiveau($niveau));

        $this->faire('rappel-a', 's1-preparation');

        self::assertSame(['faits' => 2, 'total' => 6], $this->parcours->avancementDuNiveau($niveau));
    }

    public function testLesStatutsDeTousLesBlocsSontLesMemesQueCeuxDeStatut(): void
    {
        $ids = array_map(static fn ($b) => $b->id, $this->catalogue->blocsOrdonnes());
        foreach ([[], ['rappel-a'], ['rappel-a', 's1-preparation', 's1-demonstration']] as $faits) {
            $this->faire(...$faits);

            $statuts = $this->parcours->statuts();

            self::assertSame($ids, array_keys($statuts));
            foreach ($ids as $id) {
                self::assertSame($this->parcours->statut($id), $statuts[$id], $id);
            }
        }
        self::assertSame(StatutBloc::EnCours, $statuts['s1-exercice']);
        self::assertSame(StatutBloc::Verrouille, $statuts['s2-preparation']);
    }

    public function testEnregistrerUnResultatVertEcritLeLancementEtLeBlocFaitEnsemble(): void
    {
        $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', true));

        self::assertCount(1, $this->store->lancements('s1-exercice'));
        self::assertTrue($this->store->estFait('s1-exercice'));
    }

    public function testUnEchecAuMilieuDeLEnregistrementNEcritRien(): void
    {
        // La base refuse le marquage « fait » : le lancement, écrit avant lui, doit être annulé aussi.
        $this->store->ouvrir();
        (new \PDO('sqlite:'.$this->chemin))->exec("CREATE TRIGGER refus BEFORE INSERT ON bloc_fait BEGIN SELECT RAISE(ABORT, 'panne simulée'); END");

        try {
            $this->parcours->enregistrerResultat('s1-exercice', FabriqueLancement::creer('s1-exercice', true));
            self::fail('L\'échec du marquage doit remonter.');
        } catch (\PDOException $e) {
            self::assertStringContainsString('panne simulée', $e->getMessage());
        }

        self::assertSame([], $this->store->lancements('s1-exercice'));
        self::assertFalse($this->store->estFait('s1-exercice'));
    }

    public function testUneLigneDeProgrammeEstCouverteQuandUnBlocQuiLaCiteEstFait(): void
    {
        $couvertes = $this->parcours->lignesCouvertes();
        self::assertSame(['http.requete' => false, 'http.codes' => false, 'hp.doctrine' => false], $couvertes);

        $this->faire('rappel-a');
        self::assertTrue($this->parcours->lignesCouvertes()['http.requete']);
        self::assertFalse($this->parcours->lignesCouvertes()['http.codes']);

        $this->faire('s1-preparation', 's1-demonstration');
        self::assertTrue($this->parcours->lignesCouvertes()['http.codes']);
    }

    /** Marque ces blocs faits, dans l'ordre donné. */
    /**
     * @dataProvider etapesDemandees
     */
    public function testLEtapeAAfficherSuitLaRegle(mixed $demandee, ?int $memorisee, int $nombreDEtapes, int $attendue): void
    {
        if (null !== $memorisee) {
            $this->store->memoriserEtape('s1-demonstration', $memorisee);
        }

        self::assertSame($attendue, $this->parcours->etapeAAfficher('s1-demonstration', $demandee, $nombreDEtapes));
    }

    /** @return iterable<string, array{mixed, ?int, int, int}> */
    public static function etapesDemandees(): iterable
    {
        yield 'demandée valide' => ['2', null, 3, 2];
        yield 'demandée gagne sur la mémorisée' => ['1', 3, 3, 1];
        yield 'demandée au-delà des étapes : la dernière' => ['99', null, 3, 3];
        yield 'demandée à zéro : la première' => ['0', null, 3, 1];
        yield 'demandée illisible : la première' => ['abc', 3, 3, 1];
        yield 'demandée trop longue : la première' => ['1000', null, 3, 1];
        yield 'demandée en tableau : la première' => [['2'], null, 3, 1];
        yield 'rien de demandé, rien de mémorisé : la première' => [null, null, 3, 1];
        yield 'rien de demandé : la mémorisée' => [null, 2, 3, 2];
        yield 'mémorisée au-delà des étapes (contenu raccourci) : la dernière' => [null, 9, 3, 3];
        yield 'une seule étape' => ['5', 4, 1, 1];
    }

    private function faire(string ...$ids): void
    {
        foreach ($ids as $id) {
            $this->store->marquerFait($id, new \DateTimeImmutable());
        }
    }

    /** @return list<string> */
    private function idsDeSeance(): array
    {
        return array_map(fn ($b) => $b->id, $this->parcours->seanceDuJour()->blocs);
    }

    /** @return list<string> */
    private function idsDeBadges(): array
    {
        return array_map(fn ($b) => $b->id, $this->parcours->badgesObtenus());
    }
}
