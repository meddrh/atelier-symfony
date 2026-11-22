<?php

namespace App\Tests\Exercices\ServicesAvances;

use App\DependencyInjection\Compiler\ColonnesHistoriquePass;
use App\DependencyInjection\Compiler\FormatsExportPass;
use App\Entity\HistoriqueAffectation;
use App\Entity\Materiel;
use App\Entity\Utilisateur;
use App\Export\ExportHistorique;
use App\Export\ExportHistoriqueAvecBom;
use App\Export\ExportHistoriqueInterface;
use App\Kernel;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Chaque test crée ses propres utilisateurs (noms et adresses fictifs) et se connecte par loginUser().
 * Aucun accès réseau. Les passes de compilation sont éprouvées de trois façons : sur un conteneur minimal,
 * sur le vrai conteneur de l'application (un noyau de test qui l'observe), et en démarrant un noyau qui doit refuser.
 */
class ServicesAvancesTest extends ExerciceTestCase
{
    private const BOM = "\xEF\xBB\xBF";

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : l'export des matériels par format ------------------------------------------

    public function testLExportDesMaterielsEnCsv(): void
    {
        $this->creerMateriel('Imprimante');
        $this->creerMateriel('Scanner');
        $this->client->loginUser($this->creerUtilisateur('Technicien', ['ROLE_TECHNICIEN']));

        $this->client->request('GET', '/materiels/export/csv');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/csv', $this->client->getResponse()->headers->get('Content-Type'));
        $corps = $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('Imprimante', $corps);
        self::assertStringContainsString('Scanner', $corps);
        self::assertCount(3, array_filter(explode("\n", $corps)), "Une ligne d'en-tête et une ligne par matériel.");
    }

    /** @dataProvider valeursDangereuses */
    public function testLExportDesMaterielsNeutraliseLesCellulesQuiCommencentCommeUneFormule(string $valeur): void
    {
        $materiel = (new Materiel())->setNom($valeur)->setCategorie($valeur.' (catégorie)');
        $this->em->persist($materiel);
        $this->em->flush();
        $this->client->loginUser($this->creerUtilisateur('Technicien', ['ROLE_TECHNICIEN']));

        $this->client->request('GET', '/materiels/export/csv');

        $corps = $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString("'".$valeur, $corps, 'Le texte écrit contient l\'apostrophe devant la valeur dangereuse.');
        $flux = fopen('php://memory', 'w+');
        fwrite($flux, $corps);
        rewind($flux);
        fgetcsv($flux, 0, ';', '"', '');
        $ligne = fgetcsv($flux, 0, ';', '"', '');
        fclose($flux);
        self::assertSame("'".$valeur, $ligne[1], 'Le nom : une apostrophe en tête empêche le tableur de calculer.');
        self::assertSame("'".$valeur.' (catégorie)', $ligne[2], 'La catégorie aussi.');
        self::assertSame((string) $materiel->getId(), $ligne[0], 'Le numéro n\'est pas touché.');
    }

    public function testLExportDesMaterielsEnJsonEstIndenteEnDeveloppement(): void
    {
        $this->creerMateriel('Imprimante');
        $this->client->loginUser($this->creerUtilisateur('Technicien', ['ROLE_TECHNICIEN']));

        $this->client->request('GET', '/materiels/export/json');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('application/json', $this->client->getResponse()->headers->get('Content-Type'));
        $corps = $this->client->getInternalResponse()->getContent();
        self::assertSame('Imprimante', json_decode($corps, true, 512, \JSON_THROW_ON_ERROR)[0]['nom']);
        self::assertStringContainsString("\n    ", $corps, 'La fabrique règle l\'indentation selon le mode de débogage, actif en test.');
    }

    public function testUnFormatInconnuDonne404EtLExportEstReserveAuxTechniciens(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Technicien', ['ROLE_TECHNICIEN']));
        $this->client->request('GET', '/materiels/export/xml');
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->creerUtilisateur('Simple'));
        $this->client->request('GET', '/materiels/export/csv');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLeDecorateurJournaliseChaqueExport(): void
    {
        $this->creerMateriel('Imprimante');
        $this->client->loginUser($this->creerUtilisateur('Technicien', ['ROLE_TECHNICIEN']));
        $journal = static::getContainer()->getParameter('app.fichier_journal');
        clearstatcache();
        $avant = is_file($journal) ? filesize($journal) : 0;

        $this->client->request('GET', '/materiels/export/csv');
        $this->client->request('GET', '/materiels/export/json');

        clearstatcache();
        $ecrit = (string) file_get_contents($journal, false, null, $avant);
        self::assertMatchesRegularExpression('/export.*csv/i', $ecrit);
        self::assertMatchesRegularExpression('/export.*json/i', $ecrit);
    }

    public function testLaPasseDesFormatsRefuseUnFormatEnDoubleOuSansIdentifiant(): void
    {
        $double = new ContainerBuilder();
        $double->register('format_a', \stdClass::class)->addTag('app.format_export', ['format' => 'csv']);
        $double->register('format_b', \stdClass::class)->addTag('app.format_export', ['format' => 'csv']);
        $double->addCompilerPass(new FormatsExportPass());

        try {
            $double->compile();
            self::fail('Deux services pour le même format doivent être refusés.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('csv', $e->getMessage());
        }

        $sans = new ContainerBuilder();
        $sans->register('format_sans_nom', \stdClass::class)->addTag('app.format_export');
        $sans->addCompilerPass(new FormatsExportPass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/format_sans_nom/');
        $sans->compile();
    }

    // ---- Exercice : l'export CSV de l'historique ----------------------------------------------------

    public function testLExportEstReserveAuGestionnaire(): void
    {
        $this->client->request('GET', '/historique/export.csv');
        self::assertResponseRedirects('http://localhost/connexion');

        $this->client->loginUser($this->creerUtilisateur('Simple'));
        $this->client->request('GET', '/historique/export.csv');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->creerUtilisateur('Technicien', ['ROLE_TECHNICIEN']));
        $this->client->request('GET', '/historique/export.csv');
        self::assertResponseStatusCodeSame(403, 'Un technicien n\'exporte pas l\'historique.');

        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->appelerLExport();
        self::assertResponseIsSuccessful();
    }

    public function testLesEnTetesDeLaReponse(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $avant = date('Y-m-d');

        $corps = $this->appelerLExport();

        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $apres = date('Y-m-d');
        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');
        self::assertMatchesRegularExpression('/^attachment; filename="?historique-(\d{4}-\d{2}-\d{2})\.csv"?$/', $disposition, 'Un téléchargement, avec un nom de fichier daté.');
        preg_match('/historique-(\d{4}-\d{2}-\d{2})\.csv/', $disposition, $date);
        self::assertContains($date[1], [$avant, $apres], 'La date du nom est celle du jour.');
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $this->client->getResponse());
    }

    public function testLeCorpsCommenceParLeBomUneSeuleFois(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $corps = $this->appelerLExport();

        self::assertStringStartsWith(self::BOM, $corps, 'Le BOM UTF-8 permet au tableur de lire les accents.');
        self::assertStringStartsNotWith(self::BOM, substr($corps, 3), 'Une seule fois.');
    }

    public function testLeBomVientDuDecorateurPasDuControleur(): void
    {
        $flux = fopen('php://memory', 'w+');
        static::getContainer()->get(ExportHistoriqueInterface::class)->ecrire($flux, []);
        rewind($flux);
        $ecrit = (string) stream_get_contents($flux);

        self::assertStringStartsWith(self::BOM, $ecrit, "Le service obtenu par l'interface est le décorateur : il écrit le BOM.");
        $service = static::getContainer()->get(ExportHistoriqueInterface::class);
        self::assertInstanceOf(ExportHistoriqueAvecBom::class, $service, 'L\'interface désigne le décorateur, pas le service enveloppé.');
        self::assertNotInstanceOf(ExportHistorique::class, $service);
        $interieur = (new \ReflectionProperty(ExportHistoriqueAvecBom::class, 'interieur'))->getValue($service);
        self::assertInstanceOf(ExportHistorique::class, $interieur, 'Le décorateur enveloppe le service qui écrit les lignes.');
    }

    public function testLEnTeteListeLesCinqColonnesDansLOrdre(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $corps = $this->appelerLExport();

        $lignes = $this->lire($corps);
        self::assertSame([['Date', 'Matériel', 'Ancien utilisateur', 'Nouvel utilisateur', 'Auteur']], $lignes, 'Sans historique : l\'en-tête seul.');
    }

    public function testUneLigneParChangementDuPlusAncienAuPlusRecent(): void
    {
        $auteur = $this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']);
        $ada = $this->creerUtilisateur('Ada');
        $grace = $this->creerUtilisateur('Grace');
        $materiel = $this->creerMateriel('Imprimante');
        $this->creerLigne($materiel, $ada, $grace, '2026-03-01 09:30', $auteur);
        $this->creerLigne($materiel, null, $ada, '2026-01-02 10:00', $auteur);
        $this->creerLigne($materiel, $grace, null, '2026-04-01 08:00', null);
        $this->client->loginUser($auteur);

        $corps = $this->appelerLExport();

        $lignes = $this->lire($corps);
        self::assertCount(4, $lignes);
        self::assertSame(['2026-01-02 10:00', 'Imprimante', '', 'Ada', 'Gestionnaire'], $lignes[1], 'La première affectation : personne avant (cellule vide).');
        self::assertSame(['2026-03-01 09:30', 'Imprimante', 'Ada', 'Grace', 'Gestionnaire'], $lignes[2]);
        self::assertSame(['2026-04-01 08:00', 'Imprimante', 'Grace', '', ''], $lignes[3], 'Retour au stock, auteur inconnu : cellules vides.');
    }

    /** @dataProvider valeursDangereuses */
    public function testUneCelluleQuiCommenceParUnSigneDeFormuleEstNeutralisee(string $valeur): void
    {
        $auteur = $this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']);
        $this->creerLigne($this->creerMateriel($valeur), $this->creerUtilisateur($valeur.'?'), $this->creerUtilisateur($valeur), '2026-01-02 10:00', $this->creerUtilisateur($valeur.'!', ['ROLE_USER']));
        $this->client->loginUser($auteur);

        $corps = $this->appelerLExport();

        // Le corps brut d'abord : un caractère de commande en tête de cellule s'y lit tel quel, la cellule est entre guillemets.
        self::assertStringContainsString("'".$valeur, $corps, 'Le texte écrit contient l\'apostrophe devant la valeur dangereuse.');
        $ligne = $this->lire($corps)[1];
        self::assertSame("'".$valeur, $ligne[1], 'Le titre du matériel : une apostrophe en tête empêche le tableur de calculer.');
        self::assertSame("'".$valeur.'?', $ligne[2], 'L\'ancien utilisateur aussi.');
        self::assertSame("'".$valeur, $ligne[3], 'Le nouvel utilisateur aussi.');
        self::assertSame("'".$valeur.'!', $ligne[4], 'Et l\'auteur.');
        self::assertSame('2026-01-02 10:00', $ligne[0], 'Une date n\'est pas touchée.');
    }

    /** @return iterable<string, array{string}> */
    public function valeursDangereuses(): iterable
    {
        yield 'égal' => ['=1+1'];
        yield 'plus' => ['+33612345678'];
        yield 'moins' => ['-2+3'];
        yield 'arobase' => ['@SOMME(A1:A9)'];
        yield 'tabulation' => ["\t1+1"];
        yield 'retour chariot' => ["\r1+1"];
    }

    public function testUneValeurSansDangerResteTelleQuelle(): void
    {
        $materiel = $this->creerMateriel('Écran a=b - 24"');
        $this->creerLigne($materiel, null, $this->creerUtilisateur('Ada'), '2026-01-02 10:00', null);
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $corps = $this->appelerLExport();

        self::assertSame('Écran a=b - 24"', $this->lire($corps)[1][1], 'Pas d\'apostrophe en trop.');
    }

    public function testUnPointVirgulePrisEtUnRetourALaLigneNAbimentPasLaLigne(): void
    {
        $titre = "Écran ; \"grand\" 24\"\nsuite, C:\\temp\\";
        $materiel = $this->creerMateriel($titre);
        $this->creerLigne($materiel, null, $this->creerUtilisateur('Ada'), '2026-01-02 10:00', null);
        $this->creerLigne($materiel, $this->creerUtilisateur('Ada2'), $this->creerUtilisateur('Grace'), '2026-01-03 10:00', null);
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));

        $corps = $this->appelerLExport();

        $lignes = $this->lire($corps);
        self::assertCount(3, $lignes, 'Toujours une ligne par changement, plus l\'en-tête.');
        foreach ([1, 2] as $i) {
            self::assertCount(5, $lignes[$i], 'Toujours cinq cellules.');
            self::assertSame($titre, $lignes[$i][1], 'La valeur revient intacte une fois la ligne relue.');
        }
    }

    // ---- La passe de compilation des colonnes -------------------------------------------------------

    public function testLaPasseTrieLesColonnesParOrdre(): void
    {
        $conteneur = new ContainerBuilder();
        $conteneur->register('colonne_c', \stdClass::class)->addTag('app.colonne_historique', ['ordre' => 30]);
        $conteneur->register('colonne_a', \stdClass::class)->addTag('app.colonne_historique', ['ordre' => 5]);
        $conteneur->register('colonne_b', \stdClass::class)->addTag('app.colonne_historique', ['ordre' => 20]);
        $conteneur->register('colonne_dix', \stdClass::class)->addTag('app.colonne_historique', ['ordre' => 100]);
        $conteneur->setDefinition(ExportHistorique::class, new Definition(\stdClass::class));

        (new ColonnesHistoriquePass())->process($conteneur);

        $colonnes = $conteneur->getDefinition(ExportHistorique::class)->getArgument('$colonnes');
        self::assertSame(['colonne_a', 'colonne_b', 'colonne_c', 'colonne_dix'], array_map(static fn (Reference $r): string => (string) $r, $colonnes), 'Tri numérique : 100 après 30.');
    }

    public function testLaPasseRefuseUneEtiquetteSansOrdre(): void
    {
        $conteneur = new ContainerBuilder();
        $conteneur->register('colonne_sans_ordre', \stdClass::class)->addTag('app.colonne_historique');
        $conteneur->addCompilerPass(new ColonnesHistoriquePass());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/colonne_sans_ordre/');
        $this->expectExceptionMessageMatches('/ordre/');
        $conteneur->compile();
    }

    public function testLaPasseRefuseUnOrdreQuiNEstPasUnNombre(): void
    {
        $conteneur = new ContainerBuilder();
        $conteneur->register('colonne_bizarre', \stdClass::class)->addTag('app.colonne_historique', ['ordre' => 'premier']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/colonne_bizarre/');
        (new ColonnesHistoriquePass())->process($conteneur);
    }

    public function testLeConteneurDeLApplicationRecoitLesColonnesTrieesParOrdre(): void
    {
        NoyauObservateur::$observation = [];
        $dossier = sys_get_temp_dir().'/formation-noyau-observateur-'.bin2hex(random_bytes(4));
        try {
            $noyau = new NoyauObservateur('test', true, $dossier);
            $noyau->boot();
            $noyau->shutdown();
        } finally {
            (new Filesystem())->remove($dossier);
        }

        $ordres = NoyauObservateur::$observation;
        self::assertGreaterThanOrEqual(5, \count($ordres), 'Cinq colonnes au moins : date, matériel, ancien, nouvel utilisateur, auteur.');
        $tries = $ordres;
        sort($tries, \SORT_NUMERIC);
        self::assertSame($tries, $ordres, 'L\'argument $colonnes du service ExportHistorique suit l\'ordre des étiquettes, quel que soit l\'ordre des fichiers.');
    }

    public function testUnNoyauAvecUneColonneSansOrdreRefuseDeDemarrer(): void
    {
        $dossier = sys_get_temp_dir().'/formation-noyau-sans-ordre-'.bin2hex(random_bytes(4));
        try {
            $noyau = new NoyauAvecColonneSansOrdre('test', true, $dossier);
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessageMatches('/colonne_sans_ordre/');
            $noyau->boot();
        } finally {
            (new Filesystem())->remove($dossier);
        }
    }

    // ---- Outils -------------------------------------------------------------------------------------

    /**
     * Appelle l'export et rend le corps du CSV. Une réponse en flux est envoyée dès l'événement « réponse » (c'est
     * son but : écrire au fur et à mesure) : le client de test ne la voit pas, il faut capter la sortie.
     */
    private function appelerLExport(): string
    {
        ob_start();
        try {
            $this->client->request('GET', '/historique/export.csv');
        } finally {
            $corps = (string) ob_get_clean();
        }

        return $corps;
    }

    /** @param list<string> $roles */
    private function creerUtilisateur(string $nom, array $roles = []): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setNom($nom)
            ->setEmail(strtolower(preg_replace('/[^A-Za-z0-9]/', '', $nom)).bin2hex(random_bytes(3)).'@exemple.test')
            ->setRoles($roles);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function creerMateriel(string $nom): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique');
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function creerLigne(Materiel $materiel, ?Utilisateur $ancien, ?Utilisateur $nouvel, string $faitLe, ?Utilisateur $auteur): void
    {
        $this->em->persist(
            (new HistoriqueAffectation())
                ->setMateriel($materiel)
                ->setAncienUtilisateur($ancien)
                ->setNouvelUtilisateur($nouvel)
                ->setFaitLe(new \DateTimeImmutable($faitLe))
                ->setAuteur($auteur)
        );
        $this->em->flush();
    }

    /**
     * Relit un CSV comme un tableur : séparateur « ; », guillemets, sans caractère d'échappement ; le BOM est retiré.
     *
     * @return list<list<string|null>>
     */
    private function lire(string $corps): array
    {
        $flux = fopen('php://memory', 'w+');
        fwrite($flux, str_starts_with($corps, self::BOM) ? substr($corps, 3) : $corps);
        rewind($flux);
        $lignes = [];
        while (false !== ($ligne = fgetcsv($flux, 0, ';', '"', ''))) {
            $lignes[] = $ligne;
        }
        fclose($flux);

        return $lignes;
    }
}

/** Un noyau de test : il garde l'ordre des ordres que la passe de l'application a donné à $colonnes. */
class NoyauObservateur extends Kernel
{
    /** @var list<int|float|string> */
    public static array $observation = [];

    public function __construct(string $environment, bool $debug, private string $dossierCache)
    {
        parent::__construct($environment, $debug);
    }

    public function getCacheDir(): string
    {
        return $this->dossierCache;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        // Après la passe de l'application (priorité 0), avant le câblage automatique : l'argument est encore nommé.
        $container->addCompilerPass(new class() implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $etiquettes = $container->findTaggedServiceIds('app.colonne_historique');
                foreach ($container->getDefinition(ExportHistorique::class)->getArgument('$colonnes') as $reference) {
                    NoyauObservateur::$observation[] = $etiquettes[(string) $reference][0]['ordre'];
                }
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }
}

/** Un noyau minimal : l'application, plus une colonne que personne n'a numérotée. */
class NoyauAvecColonneSansOrdre extends Kernel
{
    public function __construct(string $environment, bool $debug, private string $dossierCache)
    {
        parent::__construct($environment, $debug);
    }

    public function getCacheDir(): string
    {
        return $this->dossierCache;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->register('colonne_sans_ordre', \stdClass::class)->addTag('app.colonne_historique');
    }
}
