<?php

namespace App\Tests\Exercices\ServicesExternesCacheVerrou;

use App\Entity\Fournisseur;
use App\Entity\HistoriqueAffectation;
use App\Entity\Materiel;
use App\Entity\Utilisateur;
use App\Service\GarantieFournisseur;
use App\Tests\Exercices\ExerciceTestCase;
use App\Tests\Support\ReponsesSimulees;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Chaque test crée ses propres utilisateurs (noms et adresses fictifs) et se connecte par loginUser().
 * Aucun accès réseau : le client HTTP reçoit ses réponses de App\Tests\Support\ReponsesSimulees (mock_response_factory),
 * et toute demande sans réponse préparée fait échouer le test.
 * Le client ne redémarre pas le noyau entre deux requêtes (disableReboot) : le pool de cache et le faux service externe
 * gardent leur état d'une requête à l'autre, comme en production.
 */
class ServicesExternesTest extends ExerciceTestCase
{
    private const BOM = "\xEF\xBB\xBF";

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        // Le pool « cache.app » est un dossier de fichiers, partagé entre les tests : on le vide, sinon la date d'un test
        // précédent (même numéro de fournisseur) serait relue.
        static::getContainer()->get('cache.app')->clear();
    }

    // ---- Démonstration : la fiche du fournisseur lit la garantie sur l'API, avec un cache ------------

    public function testLaFicheAfficheLaFinDeGarantieLueSurLApi(): void
    {
        $fournisseur = $this->creerFournisseur('Dell');
        $this->reponses()->prevoir($this->garantie('2027-03-01'));
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.garantie', 'Fin de garantie : 01/03/2027');
        $demandes = $this->reponses()->demandes();
        self::assertCount(1, $demandes);
        self::assertSame('GET', $demandes[0]['methode']);
        self::assertStringStartsWith('https://api.fournisseur.exemple/', $demandes[0]['url'], 'Le client limité ajoute son adresse de base (API_FOURNISSEUR_URL, avec sa valeur par défaut) au chemin.');
        self::assertStringContainsString((string) $fournisseur->getId(), $demandes[0]['url']);
    }

    public function testLaDateEstGardeeUneHeureDansLePoolCacheApp(): void
    {
        $fournisseur = $this->creerFournisseur('Dell');
        $this->reponses()->prevoir($this->garantie('2027-03-01'));
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());
        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.garantie', '01/03/2027');
        self::assertCount(1, $this->reponses()->demandes(), 'La deuxième page lit la date dans la mémoire : une seule demande à l\'API.');

        $cache = static::getContainer()->get(CacheInterface::class);
        $cache->get(GarantieFournisseur::cle($fournisseur), static fn (): string => self::fail('La date devrait déjà être dans le pool.'), null, $metadonnees);
        $reste = $metadonnees['expiry'] - microtime(true);
        self::assertGreaterThan(3500, $reste, 'La date est gardée une heure.');
        // Le pool arrondit l'expiration à la seconde : on tolère une seconde de plus, pas davantage.
        self::assertLessThanOrEqual(3601, $reste, 'Et pas plus d\'une heure.');
    }

    public function testChaqueFournisseurAsaPropreDateEnMemoire(): void
    {
        $dell = $this->creerFournisseur('Dell');
        $hp = $this->creerFournisseur('HP');
        $this->reponses()->prevoir($this->garantie('2027-03-01'), $this->garantie('2028-06-15'));
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/fournisseurs/'.$dell->getId());
        self::assertSelectorTextContains('.garantie', '01/03/2027');
        $this->client->request('GET', '/fournisseurs/'.$hp->getId());
        self::assertSelectorTextContains('.garantie', '15/06/2028');
    }

    public function testUneApiEnPanneDonneUnMessageEtPasUneErreur500(): void
    {
        $fournisseur = $this->creerFournisseur('Dell');
        $this->reponses()->prevoir(new MockResponse('{"erreur":"panne"}', ['http_code' => 500]));
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());

        self::assertResponseStatusCodeSame(200, 'Une panne du fournisseur n\'est pas une panne de notre site.');
        self::assertSelectorTextContains('.garantie', 'indisponible');
        self::assertSelectorTextContains('h1', 'Dell');
    }

    public function testUnDelaiDepasseDonneUnMessageEtPasUneErreur500(): void
    {
        $fournisseur = $this->creerFournisseur('Dell');
        $this->reponses()->prevoir(new MockResponse('', ['error' => 'Délai dépassé']));
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.garantie', 'indisponible');
    }

    public function testUneConnexionImpossibleDonneUnMessageEtPasUneErreur500(): void
    {
        $fournisseur = $this->creerFournisseur('Dell');
        $this->reponses()->prevoir(static function (): never {
            throw new TimeoutException('Connexion impossible.');
        });
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.garantie', 'indisponible');
    }

    public function testUneReponseIllisibleDonneUnMessageEtPasUneErreur500(): void
    {
        $fournisseur = $this->creerFournisseur('Dell');
        $this->reponses()->prevoir(new MockResponse('<html>Maintenance</html>', ['http_code' => 200]));
        $this->client->loginUser($this->creerUtilisateur('Simple'));
        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.garantie', 'indisponible');

        $autre = $this->creerFournisseur('HP');
        $this->reponses()->prevoir($this->garantie('pas-une-date'));
        $this->client->request('GET', '/fournisseurs/'.$autre->getId());
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.garantie', 'indisponible');
    }

    public function testUnEchecNEstPasGardeEnMemoire(): void
    {
        $fournisseur = $this->creerFournisseur('Dell');
        $this->reponses()->prevoir(new MockResponse('', ['http_code' => 503]), $this->garantie('2027-03-01'));
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());
        self::assertSelectorTextContains('.garantie', 'indisponible');

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());
        self::assertSelectorTextContains('.garantie', '01/03/2027', 'La panne n\'a pas été mise en mémoire : la page suivante réessaie.');
        self::assertCount(2, $this->reponses()->demandes());
    }

    // ---- Démonstration : le cache HTTP de la page catalogue -----------------------------------------

    public function testLeCatalogueEstPublicEtPorteUnCacheHttp(): void
    {
        $this->creerMateriel('Imprimante', $this->creerUtilisateur('Ada'));

        $this->client->request('GET', '/catalogue');

        self::assertResponseIsSuccessful('Sans compte : la page est publique, exprès.');
        $reponse = $this->client->getResponse();
        self::assertTrue($reponse->headers->hasCacheControlDirective('public'));
        self::assertGreaterThan(0, (int) $reponse->headers->getCacheControlDirective('max-age'), 'Une durée de conservation, en secondes.');
        self::assertNotNull($reponse->getEtag(), 'Un ETag : la « version » de la page.');
        self::assertCount(0, $reponse->headers->getCookies(), 'Un cookie de session rendrait la page non partageable.');
        self::assertSelectorTextContains('li', 'Imprimante (Informatique)');
        self::assertStringNotContainsString('Ada', (string) $reponse->getContent(), 'Le catalogue ne montre jamais à qui est affecté un matériel.');
    }

    public function testLeCatalogueRepond304SiLEtagCorrespond(): void
    {
        $this->creerMateriel('Imprimante');
        $this->client->request('GET', '/catalogue');
        $etag = $this->client->getResponse()->headers->get('ETag');

        $this->client->request('GET', '/catalogue', [], [], ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(304);
        self::assertSame('', $this->client->getResponse()->getContent(), 'Un 304 n\'a pas de corps.');
        self::assertSame($etag, $this->client->getResponse()->headers->get('ETag'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('public'), 'Le 304 garde les directives de cache.');
    }

    public function testLeCatalogueChangeDEtagQuandUnMaterielChange(): void
    {
        $this->creerMateriel('Imprimante');
        $this->client->request('GET', '/catalogue');
        $ancien = $this->client->getResponse()->headers->get('ETag');

        $this->creerMateriel('Écran');
        $this->client->request('GET', '/catalogue', [], [], ['HTTP_IF_NONE_MATCH' => $ancien]);

        self::assertResponseStatusCodeSame(200, 'La page a changé : le navigateur reçoit la nouvelle version.');
        self::assertNotSame($ancien, $this->client->getResponse()->headers->get('ETag'));
        self::assertSelectorTextContains('body', 'Écran');
    }

    public function testUnEtagQuiNeCorrespondPasDonneLaPageComplete(): void
    {
        $this->creerMateriel('Imprimante');

        $this->client->request('GET', '/catalogue', [], [], ['HTTP_IF_NONE_MATCH' => '"autre"']);

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('li', 'Imprimante');
    }

    public function testLesPagesDesComptesNeSontJamaisEnCachePublic(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Simple'));

        $this->client->request('GET', '/materiels');

        self::assertResponseIsSuccessful();
        self::assertFalse($this->client->getResponse()->headers->hasCacheControlDirective('public'), 'Une page réservée à un compte ne se met pas en cache public.');
    }

    // ---- Exercice : l'ETag et le 304 de l'export CSV -------------------------------------------------

    public function testLExportPorteUnEtagEtResteUnFlux(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->creerLigne($this->creerMateriel('Imprimante'), null, null, '2026-11-01 09:00:00');

        $corps = $this->appelerLExport();

        self::assertResponseIsSuccessful();
        self::assertNotNull($this->client->getResponse()->getEtag(), 'L\'export porte un ETag.');
        self::assertInstanceOf(StreamedResponse::class, $this->client->getResponse());
        self::assertStringStartsWith(self::BOM, $corps);
        self::assertStringContainsString('Imprimante', $corps);
        self::assertFalse($this->client->getResponse()->headers->hasCacheControlDirective('public'), 'Un export réservé au gestionnaire n\'est jamais en cache public.');
    }

    public function testLExportRepond304SansCorpsSiLEtagCorrespond(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->creerLigne($this->creerMateriel('Imprimante'), null, null, '2026-11-01 09:00:00');
        $this->appelerLExport();
        $etag = $this->client->getResponse()->headers->get('ETag');

        $corps = $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(304);
        self::assertSame('', $corps, 'Rien n\'est écrit sous un code 304 : le flux n\'est même pas ouvert.');
        self::assertSame($etag, $this->client->getResponse()->headers->get('ETag'));
    }

    public function testLEtagChangeQuandUneLigneEstAjoutee(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $materiel = $this->creerMateriel('Imprimante');
        $this->creerLigne($materiel, null, null, '2026-11-01 09:00:00');
        $this->appelerLExport();
        $ancien = $this->client->getResponse()->headers->get('ETag');

        $this->creerLigne($materiel, null, null, '2026-11-01 09:00:00');
        $corps = $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $ancien]);

        self::assertResponseStatusCodeSame(200, 'Une ligne de plus, même à la même date : le contenu a changé.');
        self::assertNotSame($ancien, $this->client->getResponse()->headers->get('ETag'));
        self::assertSame(2, substr_count($corps, 'Imprimante'));
    }

    public function testLEtagChangeQuandUneDateChange(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $ligne = $this->creerLigne($this->creerMateriel('Imprimante'), null, null, '2026-11-01 09:00:00');
        $this->appelerLExport();
        $ancien = $this->client->getResponse()->headers->get('ETag');

        $ligne->setFaitLe(new \DateTimeImmutable('2026-11-02 10:30:00'));
        $this->em->flush();
        $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $ancien]);

        self::assertResponseStatusCodeSame(200);
        self::assertNotSame($ancien, $this->client->getResponse()->headers->get('ETag'));
    }

    public function testLEtagChangeQuandUnNomJointEstRenomme(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $ada = $this->creerUtilisateur('Ada');
        $materiel = $this->creerMateriel('Imprimante');
        $this->creerLigne($materiel, null, $ada, '2026-11-01 09:00:00');
        $this->appelerLExport();
        $ancien = $this->client->getResponse()->headers->get('ETag');

        // Ni le nombre de lignes ni les dates ne bougent : seul un nom lu par le CSV change.
        $materiel->setNom('Imprimante laser');
        $this->em->flush();
        $corps = $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $ancien]);

        self::assertResponseStatusCodeSame(200, 'Un matériel renommé : l\'ancienne version du fichier est périmée.');
        self::assertStringContainsString('Imprimante laser', $corps);
        self::assertNotSame($ancien, $this->client->getResponse()->headers->get('ETag'));

        $intermediaire = $this->client->getResponse()->headers->get('ETag');
        // Le noyau a redémarré entre deux requêtes : on relit l'utilisateur dans le gestionnaire d'entités courant.
        $this->em->getRepository(Utilisateur::class)->find($ada->getId())->setNom('Ada Lovelace');
        $this->em->flush();
        $corps = $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $intermediaire]);

        self::assertResponseStatusCodeSame(200, 'Un utilisateur renommé : de même.');
        self::assertStringContainsString('Ada Lovelace', $corps);
        self::assertNotSame($intermediaire, $this->client->getResponse()->headers->get('ETag'));

        $dernier = $this->client->getResponse()->headers->get('ETag');
        $corps = $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $dernier]);
        self::assertResponseStatusCodeSame(304, 'Rien n\'a changé depuis : 304 sans corps.');
        self::assertSame('', $corps);
    }

    public function testLEtagEstUneEmpreinteDuContenuDuCsv(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->creerLigne($this->creerMateriel('Imprimante'), null, null, '2026-11-01 09:00:00');

        $corps = $this->appelerLExport();

        self::assertContains(
            $this->client->getResponse()->headers->get('ETag'),
            ['"'.md5($corps).'"', '"'.sha1($corps).'"'],
            'L\'ETag est l\'empreinte (md5 ou sha1) du contenu du fichier, entre guillemets.',
        );
    }

    public function testUnEtagQuiNeCorrespondPasDonneLeCsvComplet(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->creerLigne($this->creerMateriel('Imprimante'), null, null, '2026-11-01 09:00:00');

        $corps = $this->appelerLExport(['HTTP_IF_NONE_MATCH' => '"autre"']);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('Imprimante', $corps);
    }

    public function testUnHistoriqueVideALUnEtagStableEtUn304(): void
    {
        $this->client->loginUser($this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']));
        $this->appelerLExport();
        self::assertResponseIsSuccessful();
        $etag = $this->client->getResponse()->headers->get('ETag');
        self::assertNotNull($etag);

        $corps = $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(304);
        self::assertSame('', $corps);
    }

    public function testLeRefusDesDroitsPasseAvantLeEtag(): void
    {
        // Ce test change d'utilisateur : le noyau doit redémarrer entre deux requêtes, sinon l'utilisateur de la
        // requête précédente reste en mémoire dans le conteneur.
        $this->client->enableReboot();
        $gestionnaire = $this->creerUtilisateur('Gestionnaire', ['ROLE_GESTIONNAIRE']);
        $this->creerLigne($this->creerMateriel('Imprimante'), null, null, '2026-11-01 09:00:00');
        $this->client->loginUser($gestionnaire);
        $this->appelerLExport();
        $etag = $this->client->getResponse()->headers->get('ETag');

        $this->client->loginUser($this->creerUtilisateur('Technicien', ['ROLE_TECHNICIEN']));
        $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(403, 'Un ETag connu ne remplace jamais le contrôle des droits.');

        $this->client->loginUser($this->creerUtilisateur('Simple'));
        $this->appelerLExport(['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(403);
    }

    // ---- Outils -------------------------------------------------------------------------------------

    private function reponses(): ReponsesSimulees
    {
        return static::getContainer()->get(ReponsesSimulees::class);
    }

    private function garantie(string $date): MockResponse
    {
        return new MockResponse(json_encode(['fin_garantie' => $date], \JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type: application/json']]);
    }

    /**
     * Appelle l'export et rend le corps du CSV. Une réponse en flux est envoyée dès l'événement « réponse » (c'est
     * son but : écrire au fur et à mesure) : le client de test ne la voit pas, il faut capter la sortie.
     *
     * @param array<string, string> $serveur
     */
    private function appelerLExport(array $serveur = []): string
    {
        ob_start();
        try {
            $this->client->request('GET', '/historique/export.csv', [], [], $serveur);
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

    private function creerFournisseur(string $nom): Fournisseur
    {
        $fournisseur = (new Fournisseur())->setNom($nom)->setEmail(strtolower($nom).bin2hex(random_bytes(3)).'@exemple.test');
        $this->em->persist($fournisseur);
        $this->em->flush();

        return $fournisseur;
    }

    private function creerMateriel(string $nom, ?Utilisateur $utilisateur = null): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique')->setUtilisateur($utilisateur);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function creerLigne(Materiel $materiel, ?Utilisateur $ancien, ?Utilisateur $nouvel, string $faitLe): HistoriqueAffectation
    {
        $ligne = (new HistoriqueAffectation())
            ->setMateriel($materiel)
            ->setAncienUtilisateur($ancien)
            ->setNouvelUtilisateur($nouvel)
            ->setFaitLe(new \DateTimeImmutable($faitLe))
            ->setAuteur(null);
        $this->em->persist($ligne);
        $this->em->flush();

        return $ligne;
    }
}
