<?php

namespace App\Tests\Formation\Progression;

use App\Formation\Progression\Lancement;
use App\Formation\Progression\ProgressionStore;
use App\Tests\Formation\FabriqueLancement;
use PHPUnit\Framework\TestCase;

class ProgressionStoreTest extends TestCase
{
    private string $chemin;

    protected function setUp(): void
    {
        $this->chemin = sys_get_temp_dir().'/progression-'.bin2hex(random_bytes(4)).'.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->chemin)) {
            unlink($this->chemin);
        }
    }

    public function testUneTransactionQuiEchoueNEcritRien(): void
    {
        $store = new ProgressionStore($this->chemin);

        try {
            $store->transaction(function () use ($store): void {
                $store->marquerFait('b1', new \DateTimeImmutable());
                $store->enregistrerLancement(new Lancement('b1', false, 1, 0, 'échec', new \DateTimeImmutable()));
                throw new \RuntimeException('panne au milieu');
            });
            self::fail('L\'exception de la transaction doit remonter.');
        } catch (\RuntimeException $e) {
            self::assertSame('panne au milieu', $e->getMessage());
        }

        self::assertFalse($store->estFait('b1'));
        self::assertSame([], $store->lancements('b1'));
    }

    public function testUneTransactionReussieEstValideeEtRendSonResultat(): void
    {
        $store = new ProgressionStore($this->chemin);

        $resultat = $store->transaction(function () use ($store): string {
            $store->marquerFait('b1', new \DateTimeImmutable());

            return 'fait';
        });

        self::assertSame('fait', $resultat);
        self::assertTrue((new ProgressionStore($this->chemin))->estFait('b1'), 'lu par une autre connexion : la transaction est bien validée');
    }

    public function testAucuneEtapeMemoriseeParDefaut(): void
    {
        self::assertNull((new ProgressionStore($this->chemin))->etapeMemorisee('s1-demonstration'));
    }

    public function testLEtapeMemoriseeEstRelueEtRemplacee(): void
    {
        $store = new ProgressionStore($this->chemin);

        $store->memoriserEtape('s1-demonstration', 4);
        self::assertSame(4, (new ProgressionStore($this->chemin))->etapeMemorisee('s1-demonstration'));

        $store->memoriserEtape('s1-demonstration', 2);
        self::assertSame(2, $store->etapeMemorisee('s1-demonstration'));
        self::assertNull($store->etapeMemorisee('autre-bloc'));
    }

    public function testUneBaseCreeeAvantLEtapeMemoriseeEstMigreeSansPerdreSesDonnees(): void
    {
        $ancienne = new \PDO('sqlite:'.$this->chemin);
        $ancienne->exec('CREATE TABLE bloc_fait (bloc_id TEXT PRIMARY KEY, fait_le TEXT NOT NULL)');
        $ancienne->exec("INSERT INTO bloc_fait VALUES ('b1', '2026-11-01T10:00:00+00:00')");
        $ancienne = null;

        $store = new ProgressionStore($this->chemin);

        self::assertTrue($store->estFait('b1'));
        self::assertNull($store->etapeMemorisee('b1'));
        $store->memoriserEtape('b1', 3);
        self::assertSame(3, $store->etapeMemorisee('b1'));
    }

    public function testLaDureeParDefautEst15(): void
    {
        self::assertSame(15, (new ProgressionStore($this->chemin))->duree());
    }

    public function testUneDureeHorsListeEstRefusee(): void
    {
        $store = new ProgressionStore($this->chemin);

        try {
            $store->changerDuree(20);
            self::fail('Une durée de 20 minutes aurait dû être refusée.');
        } catch (\InvalidArgumentException) {
            self::assertSame(15, $store->duree());
        }
    }

    public function testUneDureeDeLaListeEstConservee(): void
    {
        $store = new ProgressionStore($this->chemin);
        $store->changerDuree(45);

        self::assertSame(45, $store->duree());
    }

    public function testMarquerFaitDeuxFoisNeDoublePas(): void
    {
        $store = new ProgressionStore($this->chemin);
        $store->marquerFait('a', new \DateTimeImmutable('2026-11-21 10:00:00'));
        $store->marquerFait('a', new \DateTimeImmutable('2026-11-22 10:00:00'));

        self::assertCount(1, $store->blocsFaits());
        self::assertTrue($store->estFait('a'));
        self::assertFalse($store->estFait('b'));
        self::assertSame('2026-11-21', $store->blocsFaits()['a']->format('Y-m-d'), 'la première date est conservée');
    }

    public function testLesRougesSontComptesParBloc(): void
    {
        $store = new ProgressionStore($this->chemin);
        for ($i = 0; $i < 3; ++$i) {
            $store->enregistrerLancement(FabriqueLancement::creer('a', false));
        }
        $store->enregistrerLancement(FabriqueLancement::creer('b', true));
        $store->enregistrerLancement(FabriqueLancement::creer('b', false));

        self::assertSame(3, $store->nombreDeRouges('a'));
        self::assertSame(1, $store->nombreDeRouges('b'));
        self::assertSame(0, $store->nombreDeRouges('c'));
    }

    public function testLesLancementsSontRendusDuPlusRecentAuPlusAncien(): void
    {
        $store = new ProgressionStore($this->chemin);
        $store->enregistrerLancement(FabriqueLancement::creer('a', false, '2026-11-21 10:00:00', 'premier'));
        $store->enregistrerLancement(FabriqueLancement::creer('a', true, '2026-11-21 11:00:00', 'second'));

        $lancements = $store->lancements('a');

        self::assertSame(['second', 'premier'], array_map(fn (Lancement $l) => $l->resume, $lancements));
        self::assertTrue($lancements[0]->vert);
        self::assertSame(2, $lancements[0]->nbTests);
        self::assertSame('2026-11-21 11:00:00', $lancements[0]->date->format('Y-m-d H:i:s'));
        self::assertSame([], $store->lancements('inconnu'));
    }

    public function testLaListeDesTestsRougesEstEnregistreeEtRelue(): void
    {
        $store = new ProgressionStore($this->chemin);
        $echecs = ['testA : raison « <b> » éé', 'testB : autre raison'];
        $store->enregistrerLancement(FabriqueLancement::creer('a', false, null, 'testA : raison « <b> » éé', $echecs));
        $store->enregistrerLancement(FabriqueLancement::creer('a', true));

        $lancements = $store->lancements('a');

        self::assertSame([], $lancements[0]->echecs, 'un lancement vert n\'a aucun test rouge');
        self::assertSame($echecs, $lancements[1]->echecs);
    }

    public function testUneBaseDejaEnServiceEstMigreeSansPerteEtGardeSesLancements(): void
    {
        // Base telle que l'a créée la version précédente : table lancement sans la colonne des tests rouges.
        $pdo = new \PDO('sqlite:'.$this->chemin);
        $pdo->exec('CREATE TABLE lancement (id INTEGER PRIMARY KEY AUTOINCREMENT, bloc_id TEXT NOT NULL, vert INTEGER NOT NULL, nb_tests INTEGER NOT NULL, nb_reussis INTEGER NOT NULL, resume TEXT NOT NULL, date TEXT NOT NULL)');
        $pdo->exec("INSERT INTO lancement (bloc_id, vert, nb_tests, nb_reussis, resume, date) VALUES ('a', 0, 3, 1, 'ancien premier échec', '2026-11-20T10:00:00+00:00')");
        $pdo = null;

        $store = new ProgressionStore($this->chemin);
        $anciens = $store->lancements('a');
        self::assertCount(1, $anciens);
        self::assertSame('ancien premier échec', $anciens[0]->resume);
        self::assertSame([], $anciens[0]->echecs, 'un ancien lancement n\'a pas de liste : le résumé tient lieu de ligne');

        $store->enregistrerLancement(FabriqueLancement::creer('a', false, null, 'x', ['x', 'y']));
        self::assertSame(['x', 'y'], (new ProgressionStore($this->chemin))->lancements('a')[0]->echecs);
        self::assertCount(2, $store->lancements('a'));
    }

    public function testLaFicheEstRemplaceeEtRelue(): void
    {
        $store = new ProgressionStore($this->chemin);
        self::assertNull($store->fiche('p'));

        $store->enregistrerFiche('p', ['routes' => 'GET /a', 'ordre' => '1']);
        $store->enregistrerFiche('p', ['routes' => 'GET /b']);

        self::assertSame(['routes' => 'GET /b'], $store->fiche('p'));
    }

    public function testLesDonneesSurviventAUneNouvelleInstance(): void
    {
        $premier = new ProgressionStore($this->chemin);
        $premier->marquerFait('a', new \DateTimeImmutable());
        $premier->enregistrerFiche('p', ['routes' => 'GET /a']);
        $premier->enregistrerLancement(FabriqueLancement::creer('a', false));
        $premier->changerDuree(60);

        $second = new ProgressionStore($this->chemin);

        self::assertTrue($second->estFait('a'));
        self::assertSame(['routes' => 'GET /a'], $second->fiche('p'));
        self::assertSame(1, $second->nombreDeRouges('a'));
        self::assertSame(60, $second->duree());
    }

    public function testUneBaseAbsenteEstCreeeAuPremierAcces(): void
    {
        self::assertFileDoesNotExist($this->chemin);

        (new ProgressionStore($this->chemin))->duree();

        self::assertFileExists($this->chemin);
    }
}
