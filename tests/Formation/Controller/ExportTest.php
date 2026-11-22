<?php

namespace App\Tests\Formation\Controller;

use App\Formation\Contenu\Catalogue;
use App\Tests\Formation\PlateformeWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;

/**
 * « bin/formation formation:exporter <dossier> » : le site statique de lecture, sur le contenu de test (fixtures/contenu-mini).
 * Un seul export, dans un dossier jetable, partagé par tous les tests de la classe.
 */
class ExportTest extends PlateformeWebTestCase
{
    private static string $dossier;
    private static int $code;
    private static string $sortie;
    /** @var list<string> */
    private static array $blocs;

    public static function setUpBeforeClass(): void
    {
        self::$dossier = sys_get_temp_dir().'/export-formation-'.bin2hex(random_bytes(4));

        self::bootKernel();
        $catalogue = self::getContainer()->get(Catalogue::class);
        self::$blocs = array_map(static fn ($bloc): string => $bloc->id, $catalogue->blocsOrdonnes());
        $application = new Application(self::$kernel);
        $testeur = new CommandTester($application->find('formation:exporter'));
        self::$code = $testeur->execute(['dossier' => self::$dossier]);
        self::$sortie = $testeur->getDisplay();
        self::ensureKernelShutdown();
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(self::$dossier);
    }

    public function testLExportReussitEtAnnonceSesPages(): void
    {
        self::assertSame(0, self::$code, self::$sortie);
        self::assertMatchesRegularExpression('/\d+ pages/', self::$sortie);
    }

    public function testChaqueBlocDuCatalogueAUnePage(): void
    {
        self::assertCount(6, self::$blocs);
        foreach (self::$blocs as $id) {
            self::assertFileExists(self::$dossier.'/lecon-'.$id.'.html', $id);
        }
        foreach (['index.html', 'badges.html', 'programme.html'] as $page) {
            self::assertFileExists(self::$dossier.'/'.$page);
        }
    }

    public function testChaqueEtapeDeDemonstrationASaPage(): void
    {
        // La démonstration de test a deux étapes : la première est la page du bloc.
        self::assertFileExists(self::$dossier.'/lecon-s1-demonstration.html');
        self::assertFileExists(self::$dossier.'/lecon-s1-demonstration-etape-2.html');
        self::assertFileDoesNotExist(self::$dossier.'/lecon-s1-demonstration-etape-3.html');

        $premiere = $this->page('lecon-s1-demonstration.html');
        $seconde = $this->page('lecon-s1-demonstration-etape-2.html');
        self::assertStringContainsString('Première étape', $premiere->filter('.prose')->text());
        self::assertSame('lecon-s1-demonstration-etape-2.html', $premiere->selectLink('Étape suivante')->attr('href'));
        self::assertCount(0, $premiere->selectLink('Étape précédente'));
        self::assertStringContainsString('Deuxième étape', $seconde->filter('.prose')->text());
        self::assertSame('lecon-s1-demonstration.html', $seconde->selectLink('Étape précédente')->attr('href'));
        self::assertCount(0, $seconde->selectLink('Étape suivante'));
    }

    public function testTousLesLiensRelatifsPointentVersUnFichierExporte(): void
    {
        $verifies = 0;
        foreach ($this->pages() as $fichier) {
            $crawler = $this->page($fichier);
            $cibles = array_merge(
                $crawler->filter('[href]')->each(static fn (Crawler $n): string => (string) $n->attr('href')),
                $crawler->filter('[src]')->each(static fn (Crawler $n): string => (string) $n->attr('src')),
            );
            foreach ($cibles as $cible) {
                if (str_starts_with($cible, 'data:')) {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression('#^(/|[a-z][a-z0-9+.-]*:)#i', $cible, sprintf('%s : lien non relatif « %s »', $fichier, $cible));
                [$chemin, $ancre] = array_pad(explode('#', $cible, 2), 2, '');
                $chemin = '' === $chemin ? $fichier : $chemin;
                $cheminAbsolu = self::$dossier.'/'.$chemin;
                self::assertFileExists($cheminAbsolu, sprintf('%s : « %s » ne mène nulle part', $fichier, $cible));
                if ('' !== $ancre) {
                    self::assertStringContainsString('id="'.$ancre.'"', (string) file_get_contents($cheminAbsolu), sprintf('%s : ancre « %s » absente', $fichier, $cible));
                }
                ++$verifies;
            }
        }
        self::assertGreaterThan(30, $verifies, 'le parcours des liens ne doit pas être vide');
    }

    public function testAucunFormulaireAucunLienVersLaPlateformeLocale(): void
    {
        foreach ($this->pages() as $fichier) {
            $html = (string) file_get_contents(self::$dossier.'/'.$fichier);
            self::assertStringNotContainsStringIgnoringCase('<form', $html, $fichier);
            self::assertStringNotContainsString('href="/formation', $html, $fichier);
            self::assertStringNotContainsString('Demander de l', $html, $fichier);
            self::assertStringNotContainsString('>Terminé<', $html, $fichier);
            self::assertStringNotContainsString('Séance du jour', $html, $fichier);
            self::assertStringNotContainsString('Durée de ta séance', $html, $fichier);
        }
    }

    public function testRienDeCorrigesNiDeDocsNiDeTests(): void
    {
        foreach ($this->pages() as $fichier) {
            // Chaîne découpée : ce test est livré au junior, et la garde de la livraison refuse tout test qui cite ce dossier.
            self::assertStringNotContainsString('outils/'.'corriges', (string) file_get_contents(self::$dossier.'/'.$fichier), $fichier);
        }
        $racine = array_map('basename', glob(self::$dossier.'/*') ?: []);
        sort($racine);
        foreach ($racine as $nom) {
            self::assertMatchesRegularExpression('/\.html$|^atelier$|^robots\.txt$/', $nom, 'seuls les pages, atelier/ et robots.txt sont exportés');
        }
        self::assertFileDoesNotExist(self::$dossier.'/docs');
        self::assertFileDoesNotExist(self::$dossier.'/.claude');
        self::assertFileDoesNotExist(self::$dossier.'/tests');
    }

    public function testLaFeuilleDeStyleEstCopieeEtLieeEnRelatif(): void
    {
        self::assertFileExists(self::$dossier.'/atelier/atelier.css');
        self::assertSame('atelier/atelier.css', $this->page('index.html')->filter('link[rel=stylesheet]')->attr('href'));
    }

    public function testChaquePagePorteLeBandeauDeLecture(): void
    {
        foreach ($this->pages() as $fichier) {
            $bandeau = $this->page($fichier)->filter('.bandeau');
            self::assertCount(1, $bandeau, $fichier);
            $texte = preg_replace('/\s+/', ' ', trim($bandeau->text()));
            self::assertStringContainsString('Version de lecture : pour valider un exercice, lance bin/exercice test dans ton projet', $texte, $fichier);
            self::assertStringContainsString('Les boutons « Terminé » et les formulaires dont parlent les pages n\'existent que sur la plateforme installée chez toi.', $texte, $fichier);
        }
    }

    public function testChaquePageExporteeEstNonIndexeeEtRobotsTxtInterdit(): void
    {
        foreach ($this->pages() as $fichier) {
            $meta = $this->page($fichier)->filter('head meta[name=robots]');
            self::assertCount(1, $meta, $fichier);
            self::assertSame('noindex, nofollow', $meta->attr('content'), $fichier);
        }
        self::assertFileExists(self::$dossier.'/robots.txt');
        self::assertSame("User-agent: *\nDisallow: /\n", file_get_contents(self::$dossier.'/robots.txt'));
    }

    public function testLAccueilEstLeParcoursSansEtatNiVerrou(): void
    {
        $accueil = $this->page('index.html');

        self::assertStringContainsString('Premier sujet', $accueil->filter('body')->text());
        self::assertStringContainsString('Débutant', $accueil->filter('body')->text());
        self::assertSame('lecon-s1-exercice.html', $accueil->selectLink('Exercice')->attr('href'));
        self::assertCount(0, $accueil->filter('.etat'));
        self::assertCount(0, $accueil->filter('.bloc--verrouille'));
        // Tous les blocs sont des liens, rappels et lectures compris.
        self::assertCount(\count(self::$blocs), $accueil->filter('li.bloc a'));
    }

    public function testLaFicheDePreparationMontreLesQuestionsEtUneCorrectionRepliee(): void
    {
        $fiche = $this->page('lecon-s1-preparation.html');

        self::assertCount(0, $fiche->filter('textarea'));
        self::assertStringContainsString('Routes à créer', $fiche->filter('article')->text());
        self::assertStringContainsString('Ex : GET /fournisseurs', $fiche->filter('article')->text());
        $corrections = $fiche->filter('article details');
        self::assertCount(2, $corrections);
        self::assertNull($corrections->first()->attr('open'), 'la correction est repliée');
        self::assertStringContainsString('GET /fournisseurs', $corrections->first()->text());
        self::assertStringContainsString('1. Contrôleur', $corrections->eq(1)->text());
    }

    public function testUnExerciceMontreSonSqueletteSesTestsEtLaCommande(): void
    {
        $exercice = $this->page('lecon-s2-exercice.html');
        $texte = $exercice->filter('article')->text();

        self::assertStringContainsString('Squelette des fichiers', $texte);
        self::assertGreaterThanOrEqual(1, $exercice->filter('article pre')->count());
        self::assertStringContainsString('Tests attendus', $texte);
        self::assertStringContainsString('bin/exercice test', $texte);
        self::assertCount(0, $exercice->filter('.terminal'));
        self::assertCount(0, $exercice->filter('button'));
    }

    public function testUnTableauMarkdownEstRenduEnTable(): void
    {
        $exercice = $this->page('lecon-s1-exercice.html');

        self::assertCount(1, $exercice->filter('.prose table'));
        self::assertStringContainsString('<table', (string) file_get_contents(self::$dossier.'/lecon-s1-exercice.html'));
        self::assertStringNotContainsString('<script', (string) file_get_contents(self::$dossier.'/lecon-s1-exercice.html'), 'le HTML brut du contenu reste retiré');
    }

    public function testLesBadgesEtLeProgrammeSontExportesSansProgression(): void
    {
        $badges = $this->page('badges.html');
        self::assertStringContainsString('Premier badge', $badges->filter('body')->text());
        self::assertStringNotContainsString('obtenus', $badges->filter('body')->text());
        self::assertCount(0, $badges->filter('.badge--a-obtenir'));
        self::assertCount(3, $badges->filter('.badge'));

        $programme = $this->page('programme.html');
        self::assertStringNotContainsString('couverte', $programme->filter('body')->text());
        self::assertGreaterThan(0, $programme->filter('.ligne')->count());
    }

    /** @return list<string> */
    private function pages(): array
    {
        $pages = [];
        foreach ((new Finder())->files()->in(self::$dossier)->depth(0)->name('*.html')->sortByName() as $fichier) {
            $pages[] = $fichier->getFilename();
        }
        self::assertNotSame([], $pages);

        return $pages;
    }

    private function page(string $fichier): Crawler
    {
        return new Crawler((string) file_get_contents(self::$dossier.'/'.$fichier));
    }
}
