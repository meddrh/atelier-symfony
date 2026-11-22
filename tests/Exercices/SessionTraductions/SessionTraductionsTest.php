<?php

namespace App\Tests\Exercices\SessionTraductions;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Entity\Utilisateur;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Yaml\Yaml;

/**
 * Chaque test qui a besoin d'une valeur de session la pose AVANT la requête, dans une vraie session,
 * puis lit la session que la requête a laissée : tes clés de session doivent donc être exactement
 * « materiels.filtre » (démonstration) et « tickets.statut » (exercice).
 */
class SessionTraductionsTest extends ExerciceTestCase
{
    private const CLE_MATERIELS = 'materiels.filtre';
    private const CLE_TICKETS = 'tickets.statut';
    // Instant de référence des tickets de ces tests (voir enregistrerTicket()).
    private const INSTANT_FIXE = '2026-11-10 12:00:00';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : le filtre des matériels, gardé en session -----------------------

    public function testSansFiltreTousLesMaterielsSontListes(): void
    {
        $this->troisMateriels();

        $this->client->request('GET', '/materiels');

        self::assertResponseIsSuccessful();
        self::assertSame(['Imprimante', 'Scanner', 'Écran'], $this->nomsAffiches());
    }

    public function testLeFiltreAffectesNeMontreQueLesMaterielsAffectes(): void
    {
        $this->troisMateriels();

        $this->client->request('GET', '/materiels?filtre=affectes');

        self::assertSame(['Écran'], $this->nomsAffiches());
    }

    public function testLeFiltreLibresNeMontreQueLesMaterielsSansUtilisateur(): void
    {
        $this->troisMateriels();

        $this->client->request('GET', '/materiels?filtre=libres');

        self::assertSame(['Imprimante', 'Scanner'], $this->nomsAffiches());
    }

    public function testLeFiltreChoisiEstGardeEnSessionPourLesPagesSuivantes(): void
    {
        $this->troisMateriels();

        $this->client->request('GET', '/materiels?filtre=affectes');
        self::assertSame('affectes', $this->client->getRequest()->getSession()->get(self::CLE_MATERIELS));

        $this->client->request('GET', '/materiels');
        self::assertSame(['Écran'], $this->nomsAffiches(), 'Sans paramètre, le filtre mémorisé en session s\'applique encore.');
    }

    public function testLeFiltreTousRemetLaListeComplete(): void
    {
        $this->troisMateriels();
        $this->client->request('GET', '/materiels?filtre=libres');

        $this->client->request('GET', '/materiels?filtre=tous');

        self::assertCount(3, $this->nomsAffiches());
        $this->client->request('GET', '/materiels');
        self::assertCount(3, $this->nomsAffiches());
    }

    public function testUnFiltreInconnuDansLAdresseRevientAuFiltreParDefautEtPrevient(): void
    {
        $this->troisMateriels();

        $this->client->request('GET', '/materiels?filtre=pirate');

        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->nomsAffiches());
        self::assertSelectorTextContains('body', "Ce filtre n'existe pas : tous les matériels sont affichés.");
        self::assertNotSame('pirate', $this->client->getRequest()->getSession()->get(self::CLE_MATERIELS));
    }

    /**
     * @dataProvider valeursDeSessionAlterees
     */
    public function testUneValeurDeSessionAltereeRevientAuFiltreParDefautSansErreur(mixed $valeur): void
    {
        $this->troisMateriels();
        $this->poserEnSession([self::CLE_MATERIELS => $valeur]);

        $this->client->request('GET', '/materiels');

        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->nomsAffiches());
    }

    /** @return iterable<string, array{mixed}> */
    public function valeursDeSessionAlterees(): iterable
    {
        yield 'texte inconnu' => ['n\'importe quoi'];
        yield 'texte vide' => [''];
        yield 'liste' => [['libres']];
        yield 'nombre' => [42];
    }

    public function testLaPageDuParcCompteLesMaterielsAvecLePlurielDeLaLangue(): void
    {
        foreach ([['fr', 0, 'Aucun matériel'], ['fr', 1, '1 matériel'], ['fr', 3, '3 matériels'], ['en', 0, 'No devices'], ['en', 1, '1 device'], ['en', 3, '3 devices']] as [$langue, $nombre, $attendu]) {
            $this->recreerLaBase();
            for ($i = 1; $i <= $nombre; ++$i) {
                $this->enregistrerMateriel('Matériel '.$i);
            }
            $this->em->clear();

            $this->client->request('GET', '/'.$langue.'/parc');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('.compteur', $attendu, sprintf('Langue « %s », %d matériel(s).', $langue, $nombre));
        }
    }

    public function testUneLangueInconnueRenvoie404(): void
    {
        $this->client->request('GET', '/de/parc');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLaPageDuParcSansLangueRedirigeVersLaLangueDuNavigateur(): void
    {
        // Le client de test envoie par défaut « en-us » : pour simuler un navigateur sans préférence, on vide l'en-tête.
        $this->client->request('GET', '/parc', [], [], ['HTTP_ACCEPT_LANGUAGE' => '']);
        self::assertResponseRedirects('/fr/parc', null, 'Sans préférence de langue, la première langue proposée (fr) est choisie.');

        $this->client->request('GET', '/parc', [], [], ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9,fr;q=0.5']);
        self::assertResponseRedirects('/en/parc');

        $this->client->request('GET', '/parc', [], [], ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9']);
        self::assertResponseRedirects('/fr/parc', null, 'Une langue non proposée retombe sur le français.');
    }

    public function testLaLangueDeLaPageEstDeclareeDansLeDocument(): void
    {
        $this->client->request('GET', '/en/parc');
        self::assertSelectorExists('html[lang="en"]');

        $this->client->request('GET', '/fr/parc');
        self::assertSelectorExists('html[lang="fr"]');

        $this->client->request('GET', '/materiels');
        self::assertSelectorExists('html[lang="fr"]');
    }

    public function testLeMenuEstTraduit(): void
    {
        $this->client->request('GET', '/en/parc');
        self::assertSelectorTextContains('header', 'Users');

        $this->client->request('GET', '/fr/parc');
        self::assertSelectorTextContains('header', 'Utilisateurs');
    }

    public function testLesDeuxCataloguesOntLesMemesCles(): void
    {
        $fr = $this->cles('fr');
        $en = $this->cles('en');

        self::assertNotSame([], $fr, 'Le catalogue français est vide ou absent.');
        self::assertSame([], array_values(array_diff($fr, $en)), 'Clés présentes en français mais absentes du catalogue anglais.');
        self::assertSame([], array_values(array_diff($en, $fr)), 'Clés présentes en anglais mais absentes du catalogue français.');
    }

    public function testAucuneCleBruteNApparaitSurLesPagesTraduites(): void
    {
        $this->troisMateriels();
        $adresses = ['/fr/parc', '/en/parc', '/materiels', '/materiels?filtre=pirate', '/fr/tickets', '/en/tickets'];

        foreach ($adresses as $adresse) {
            $crawler = $this->client->request('GET', $adresse);
            self::assertResponseIsSuccessful($adresse);
            $texte = $crawler->filter('body')->text(null, false);
            foreach (array_merge($this->cles('fr'), $this->cles('en')) as $cle) {
                self::assertStringNotContainsString($cle, $texte, sprintf('La clé de traduction « %s » est affichée telle quelle sur %s : traduction manquante.', $cle, $adresse));
            }
        }
    }

    // ---- Exercice : la liste des tickets, par statut, dans deux langues ------------------

    public function testLaListeDesTicketsSansLangueRedirigeVersLeFrancais(): void
    {
        $this->client->request('GET', '/tickets');

        self::assertResponseRedirects('/fr/tickets');
    }

    public function testUneLangueInconnueSurLesTicketsRenvoie404(): void
    {
        $this->client->request('GET', '/de/tickets');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @dataProvider compteurs
     */
    public function testLeCompteurDeTicketsAccordeLePlurielEtLeStatut(string $langue, string $filtre, int $nombre, string $attendu): void
    {
        $this->enregistrerTicketsDuStatut($filtre, $nombre);

        $this->client->request('GET', '/'.$langue.'/tickets?statut='.$filtre);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.compteur', $attendu);
        self::assertCount($nombre, $this->client->getCrawler()->filter('.ticket'), 'Seuls les tickets du statut demandé sont listés.');
    }

    /** @return iterable<string, array{string, string, int, string}> */
    public function compteurs(): iterable
    {
        $phrases = [
            'fr' => [
                'ouvert' => ['Aucun ticket ouvert', '1 ticket ouvert', '3 tickets ouverts'],
                'en_cours' => ['Aucun ticket en cours', '1 ticket en cours', '3 tickets en cours'],
                'resolu' => ['Aucun ticket résolu', '1 ticket résolu', '3 tickets résolus'],
                'tous' => ['Aucun ticket', '1 ticket', '3 tickets'],
            ],
            'en' => [
                'ouvert' => ['No open tickets', '1 open ticket', '3 open tickets'],
                'en_cours' => ['No tickets in progress', '1 ticket in progress', '3 tickets in progress'],
                'resolu' => ['No resolved tickets', '1 resolved ticket', '3 resolved tickets'],
                'tous' => ['No tickets', '1 ticket', '3 tickets'],
            ],
        ];
        foreach ($phrases as $langue => $parStatut) {
            foreach ($parStatut as $filtre => $trois) {
                foreach ([0, 1, 3] as $rang => $nombre) {
                    yield sprintf('%s, %s, %d', $langue, $filtre, $nombre) => [$langue, $filtre, $nombre, $trois[$rang]];
                }
            }
        }
    }

    public function testChaqueTicketAfficheSonStatutTraduit(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante');
        $this->enregistrerTicket($materiel, 'Bourrage papier', Ticket::STATUT_EN_COURS);

        $this->client->request('GET', '/fr/tickets?statut=en_cours');
        self::assertSelectorTextSame('.ticket .statut', 'en cours');

        $this->client->request('GET', '/en/tickets?statut=en_cours');
        self::assertSelectorTextSame('.ticket .statut', 'in progress');

        $this->client->request('GET', '/en/tickets?statut=tous');
        self::assertSelectorTextSame('.ticket .statut', 'in progress');
    }

    public function testLesStatutsOuvertEtResoluSontTraduits(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante');
        $this->enregistrerTicket($materiel, 'Bourrage papier', Ticket::STATUT_OUVERT);
        $this->enregistrerTicket($materiel, 'Écran noir', Ticket::STATUT_RESOLU);

        $this->client->request('GET', '/en/tickets?statut=tous');
        self::assertEqualsCanonicalizing(['open', 'resolved'], $this->textes('.ticket .statut'));

        $this->client->request('GET', '/fr/tickets?statut=tous');
        self::assertEqualsCanonicalizing(['ouvert', 'résolu'], $this->textes('.ticket .statut'));
    }

    public function testLesTicketsSontDuPlusRecentAuPlusAncien(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante');
        $this->enregistrerTicket($materiel, 'Premier ticket', Ticket::STATUT_OUVERT, '-3 days');
        $this->enregistrerTicket($materiel, 'Dernier ticket', Ticket::STATUT_OUVERT, '-1 hour');
        $this->enregistrerTicket($materiel, 'Ticket du milieu', Ticket::STATUT_OUVERT, '-1 day');

        $this->client->request('GET', '/fr/tickets');

        self::assertSame(['Dernier ticket', 'Ticket du milieu', 'Premier ticket'], $this->textes('.ticket strong'));
    }

    public function testSansFiltreLesTicketsOuvertsSontMontresParDefaut(): void
    {
        $this->deuxTicketsDeStatutsDifferents();

        $this->client->request('GET', '/fr/tickets');

        self::assertSame(['Alpha ouvert'], $this->textes('.ticket strong'));
        self::assertSelectorTextSame('.compteur', '1 ticket ouvert');
    }

    public function testLeFiltreDesTicketsEstGardeEnSessionDansToutesLesLangues(): void
    {
        $this->deuxTicketsDeStatutsDifferents();

        $this->client->request('GET', '/fr/tickets?statut=resolu');
        self::assertSame(['Omega résolu'], $this->textes('.ticket strong'));
        self::assertSame('resolu', $this->client->getRequest()->getSession()->get(self::CLE_TICKETS));

        $this->client->request('GET', '/en/tickets');
        self::assertSame(['Omega résolu'], $this->textes('.ticket strong'), 'Sans paramètre, le filtre mémorisé s\'applique, quelle que soit la langue.');
        self::assertSelectorTextSame('.compteur', '1 resolved ticket');

        $this->client->request('GET', '/fr/tickets?statut=tous');
        self::assertCount(2, $this->client->getCrawler()->filter('.ticket'));
    }

    public function testUnStatutInconnuDansLAdresseRevientAuFiltreParDefaut(): void
    {
        $this->deuxTicketsDeStatutsDifferents();

        $this->client->request('GET', '/fr/tickets?statut=pirate');

        self::assertResponseIsSuccessful();
        self::assertSame(['Alpha ouvert'], $this->textes('.ticket strong'));
        self::assertNotSame('pirate', $this->client->getRequest()->getSession()->get(self::CLE_TICKETS));
    }

    /**
     * @dataProvider valeursDeSessionAlterees
     */
    public function testUneValeurDeSessionAltereePourLesTicketsRevientAuFiltreParDefaut(mixed $valeur): void
    {
        $this->deuxTicketsDeStatutsDifferents();
        $this->poserEnSession([self::CLE_TICKETS => $valeur]);

        $this->client->request('GET', '/fr/tickets');

        self::assertResponseIsSuccessful('Une valeur de session inattendue ne doit jamais provoquer une erreur 500.');
        self::assertSame(['Alpha ouvert'], $this->textes('.ticket strong'));
    }

    public function testLaLangueDeLaListeDesTicketsEstDeclareeDansLeDocument(): void
    {
        $this->client->request('GET', '/en/tickets');

        self::assertSelectorExists('html[lang="en"]');
    }

    public function testLaFicheDuMaterielMeneALaListeDesTickets(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante');

        $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertSelectorExists('a[href="/fr/tickets"]');
    }

    // ---- Outils de test --------------------------------------------------------------

    private function troisMateriels(): void
    {
        $alice = (new Utilisateur())->setNom('Alice Martin')->setEmail('alice@exemple.test');
        $this->em->persist($alice);
        $this->enregistrerMateriel('Imprimante');
        $this->enregistrerMateriel('Scanner');
        $this->enregistrerMateriel('Écran')->setUtilisateur($alice);
        $this->em->flush();
        $this->em->clear();
    }

    private function deuxTicketsDeStatutsDifferents(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante');
        $this->enregistrerTicket($materiel, 'Alpha ouvert', Ticket::STATUT_OUVERT);
        $this->enregistrerTicket($materiel, 'Omega résolu', Ticket::STATUT_RESOLU);
        $this->em->clear();
    }

    /** Pour « tous », n tickets de statuts variés ; sinon n tickets du statut, plus deux autres qui ne doivent pas apparaître. */
    private function enregistrerTicketsDuStatut(string $filtre, int $nombre): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante');
        $statuts = Ticket::STATUTS;
        for ($i = 1; $i <= $nombre; ++$i) {
            $statut = 'tous' === $filtre ? $statuts[$i % 3] : $filtre;
            $this->enregistrerTicket($materiel, 'Ticket numéro '.$i, $statut);
        }
        if ('tous' !== $filtre) {
            foreach (array_values(array_diff($statuts, [$filtre])) as $autre) {
                $this->enregistrerTicket($materiel, 'Autre ticket '.$autre, $autre);
            }
        }
        $this->em->clear();
    }

    /** @param array<string, mixed> $valeurs */
    private function poserEnSession(array $valeurs): void
    {
        $session = static::getContainer()->get('session.factory')->createSession();
        foreach ($valeurs as $cle => $valeur) {
            $session->set($cle, $valeur);
        }
        $session->save();
        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    /** @return list<string> */
    private function nomsAffiches(): array
    {
        $noms = [];
        foreach ($this->client->getCrawler()->filter('ul li > a[href^="/materiels/"]:first-child') as $lien) {
            $noms[] = trim($lien->textContent);
        }

        return $noms;
    }

    /** @return list<string> */
    private function textes(string $selecteur): array
    {
        return array_map(static fn (\DOMNode $noeud): string => trim($noeud->textContent), iterator_to_array($this->client->getCrawler()->filter($selecteur), false));
    }

    /** @return list<string> */
    private function cles(string $langue): array
    {
        $fichier = static::getContainer()->getParameter('kernel.project_dir').'/translations/messages+intl-icu.'.$langue.'.yaml';
        if (!is_file($fichier)) {
            return [];
        }

        return $this->aplatir((array) Yaml::parseFile($fichier));
    }

    /**
     * @param array<string, mixed> $tableau
     *
     * @return list<string>
     */
    private function aplatir(array $tableau, string $prefixe = ''): array
    {
        $cles = [];
        foreach ($tableau as $cle => $valeur) {
            $nom = '' === $prefixe ? (string) $cle : $prefixe.'.'.$cle;
            if (\is_array($valeur)) {
                array_push($cles, ...$this->aplatir($valeur, $nom));
            } else {
                $cles[] = $nom;
            }
        }
        sort($cles);

        return $cles;
    }

    private function recreerLaBase(): void
    {
        $metadonnees = $this->em->getMetadataFactory()->getAllMetadata();
        $outil = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $outil->dropSchema($metadonnees);
        $outil->createSchema($metadonnees);
        $this->em->clear();
    }

    private function enregistrerMateriel(string $nom): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique');
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    /**
     * La date de création se compte à partir d'un instant FIXE, jamais de l'heure de la machine : le test donne le même
     * résultat à 23 h 59 et à minuit. Seul l'écart entre les tickets compte pour l'ordre (« -3 days », « -1 hour »).
     */
    private function enregistrerTicket(Materiel $materiel, string $titre, string $statut, string $decalage = '+0 seconds'): Ticket
    {
        $creeLe = (new \DateTimeImmutable(self::INSTANT_FIXE))->modify($decalage);
        $ticket = (new Ticket())->setTitre($titre)->setDescription('Description.')->setMateriel($materiel)->setStatut($statut)->setCreeLe($creeLe);
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }
}
