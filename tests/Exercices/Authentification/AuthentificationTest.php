<?php

namespace App\Tests\Exercices\Authentification;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Entity\Utilisateur;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Chaque test crée ses propres utilisateurs : le mot de passe est haché par le service de hachage du conteneur,
 * puis le test se connecte soit par le formulaire (démonstration), soit par loginUser() (exercice).
 * Les mots de passe et les jetons ci-dessous sont fictifs.
 */
class AuthentificationTest extends ExerciceTestCase
{
    private const MOT_DE_PASSE = 'mot-de-passe-de-test';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : utilisateur de sécurité, connexion, déconnexion, commande -----------------

    public function testUtilisateurEstUnUtilisateurDeSecurite(): void
    {
        $utilisateur = $this->creerUtilisateur('ada@exemple.test');

        self::assertInstanceOf(UserInterface::class, $utilisateur);
        self::assertInstanceOf(PasswordAuthenticatedUserInterface::class, $utilisateur);
        self::assertSame('ada@exemple.test', $utilisateur->getUserIdentifier(), "L'identifiant de connexion est l'adresse e-mail.");
        self::assertContains('ROLE_USER', $utilisateur->getRoles(), 'Tout utilisateur a au moins ROLE_USER, même sans rôle enregistré.');
        self::assertNotSame(self::MOT_DE_PASSE, $utilisateur->getPassword());
        self::assertNotEmpty($utilisateur->getPassword());
    }

    public function testLeMotDePasseNEstJamaisStockeEnClair(): void
    {
        $utilisateur = $this->creerUtilisateur('ada@exemple.test');

        $enBase = $this->em->getConnection()->fetchOne('SELECT password FROM utilisateur WHERE id = ?', [$utilisateur->getId()]);

        self::assertNotSame(self::MOT_DE_PASSE, $enBase);
        self::assertTrue(static::getContainer()->get('security.user_password_hasher')->isPasswordValid($utilisateur, self::MOT_DE_PASSE));
    }

    public function testLesPagesDesMaterielsRenvoientVersLaConnexionSansConnexion(): void
    {
        $materiel = $this->creerMateriel();

        foreach (['/materiels', '/materiels/'.$materiel->getId(), '/materiels/nouveau'] as $adresse) {
            $this->client->request('GET', $adresse);

            self::assertResponseRedirects(null, 302, "$adresse doit exiger une connexion.");
            self::assertSame('/connexion', $this->cheminDeLaRedirection(), "$adresse doit renvoyer vers /connexion.");
        }
    }

    public function testUnAppelDirectNeSupprimePasLeMaterielSansConnexion(): void
    {
        $materiel = $this->creerMateriel();

        $this->client->request('POST', '/materiels/'.$materiel->getId().'/supprimer');

        self::assertSame('/connexion', $this->cheminDeLaRedirection());
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Materiel::class)->find($materiel->getId()), "Sans connexion, le matériel ne doit pas être supprimé.");
    }

    public function testLaPageDeConnexionEstPubliqueEtPorteLeFormulaire(): void
    {
        $crawler = $this->client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="_username"]'), 'Un champ « _username » (l\'adresse e-mail).');
        self::assertCount(1, $crawler->filter('input[name="_password"][type="password"]'));
        self::assertCount(1, $crawler->filter('input[name="_csrf_token"]'), 'Le formulaire porte un jeton CSRF.');
        self::assertCount(1, $crawler->filter('input[name="_remember_me"][type="checkbox"]'), 'Une case « se souvenir de moi ».');
        self::assertCount(1, $crawler->selectButton('Se connecter'));
    }

    public function testLaConnexionReussitEtOuvreLesMateriels(): void
    {
        $this->creerUtilisateur('ada@exemple.test');

        $this->seConnecterParLeFormulaire('ada@exemple.test', self::MOT_DE_PASSE);

        self::assertSame('/materiels', $this->cheminDeLaRedirection());
        $this->client->request('GET', '/materiels');
        self::assertResponseIsSuccessful();
    }

    public function testUnMauvaisMotDePasseRenvoieAuFormulaireAvecUneErreur(): void
    {
        $this->creerUtilisateur('ada@exemple.test');

        $this->seConnecterParLeFormulaire('ada@exemple.test', 'pas-le-bon');

        self::assertSame('/connexion', $this->cheminDeLaRedirection());
        $this->client->followRedirect();
        self::assertSelectorExists('.erreur', "Le formulaire affiche l'erreur dans un élément de classe « erreur ».");
        $this->client->request('GET', '/materiels');
        self::assertSame('/connexion', $this->cheminDeLaRedirection(), "Le mauvais mot de passe n'a rien ouvert.");
    }

    public function testUnJetonCsrfFauxRefuseLaConnexionMemeAvecLeBonMotDePasse(): void
    {
        $this->creerUtilisateur('ada@exemple.test');

        $this->seConnecterParLeFormulaire('ada@exemple.test', self::MOT_DE_PASSE, jetonCsrf: 'jeton-falsifie');

        self::assertSame('/connexion', $this->cheminDeLaRedirection());
        $this->client->request('GET', '/materiels');
        self::assertSame('/connexion', $this->cheminDeLaRedirection(), 'Un jeton CSRF faux ne doit pas connecter.');
    }

    public function testSeSouvenirDeMoiPoseUnCookieSeulementQuandLaCaseEstCochee(): void
    {
        $this->creerUtilisateur('ada@exemple.test');

        $this->seConnecterParLeFormulaire('ada@exemple.test', self::MOT_DE_PASSE, seSouvenir: false);
        self::assertNull($this->client->getCookieJar()->get('REMEMBERME'), 'Case non cochée : pas de cookie de souvenir.');

        $this->client->request('GET', '/deconnexion');
        $this->client->getCookieJar()->clear();

        $this->seConnecterParLeFormulaire('ada@exemple.test', self::MOT_DE_PASSE, seSouvenir: true);
        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'), 'Case cochée : le cookie REMEMBERME est posé.');
    }

    public function testLaDeconnexionFermeLaSession(): void
    {
        $this->creerUtilisateur('ada@exemple.test');
        $this->seConnecterParLeFormulaire('ada@exemple.test', self::MOT_DE_PASSE);
        $this->client->request('GET', '/materiels');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/deconnexion');

        self::assertResponseRedirects();
        $this->client->request('GET', '/materiels');
        self::assertSame('/connexion', $this->cheminDeLaRedirection(), 'Après la déconnexion, les matériels exigent de nouveau une connexion.');
    }

    public function testLaCommandeChangeLeMotDePasseEnLeHachant(): void
    {
        $utilisateur = $this->creerUtilisateur('ada@exemple.test');
        $ancien = $utilisateur->getPassword();

        $testeur = $this->executer('app:utilisateur:mot-de-passe', ['email' => 'ada@exemple.test', 'mot-de-passe' => 'tout-nouveau-secret']);

        self::assertSame(0, $testeur->getStatusCode());
        $this->em->clear();
        $recharge = $this->em->getRepository(Utilisateur::class)->find($utilisateur->getId());
        self::assertNotSame($ancien, $recharge->getPassword());
        self::assertNotSame('tout-nouveau-secret', $recharge->getPassword(), 'Le mot de passe est haché, jamais enregistré en clair.');
        self::assertTrue(static::getContainer()->get('security.user_password_hasher')->isPasswordValid($recharge, 'tout-nouveau-secret'));
    }

    public function testLaCommandeRefuseUneAdresseInconnueEtUnMotDePasseVide(): void
    {
        $utilisateur = $this->creerUtilisateur('ada@exemple.test');

        $inconnu = $this->executer('app:utilisateur:mot-de-passe', ['email' => 'personne@exemple.test', 'mot-de-passe' => 'secret-suffisant']);
        self::assertSame(1, $inconnu->getStatusCode());
        self::assertStringContainsString('personne@exemple.test', $inconnu->getDisplay());

        $ancien = $utilisateur->getPassword();
        $vide = $this->executer('app:utilisateur:mot-de-passe', ['email' => 'ada@exemple.test', 'mot-de-passe' => '']);
        self::assertSame(1, $vide->getStatusCode());
        $this->em->clear();
        self::assertSame($ancien, $this->em->getRepository(Utilisateur::class)->find($utilisateur->getId())->getPassword(), 'Un mot de passe vide ne change rien.');
    }

    // ---- Exercice : rôles, contrôle d'accès, Mon compte --------------------------------------------

    /**
     * @dataProvider accesParRole
     *
     * @param list<string> $roles
     */
    public function testLaHierarchieDesRolesOuvreLesBonnesPages(array $roles, string $adresse, int $attendu): void
    {
        $this->client->loginUser($this->creerUtilisateur('qui@exemple.test', $roles));

        $this->client->request('GET', $adresse);

        self::assertSame($attendu, $this->client->getResponse()->getStatusCode(), sprintf('%s sur %s', implode(',', $roles) ?: 'ROLE_USER', $adresse));
    }

    /** @return iterable<string, array{list<string>, string, int}> */
    public function accesParRole(): iterable
    {
        yield 'simple utilisateur, tickets' => [[], '/fr/tickets', 403];
        yield 'simple utilisateur, utilisateurs' => [[], '/utilisateurs', 403];
        yield 'technicien, tickets' => [['ROLE_TECHNICIEN'], '/fr/tickets', 200];
        yield 'technicien, utilisateurs' => [['ROLE_TECHNICIEN'], '/utilisateurs', 403];
        yield 'gestionnaire, tickets (hérite de technicien)' => [['ROLE_GESTIONNAIRE'], '/fr/tickets', 200];
        yield 'gestionnaire, utilisateurs' => [['ROLE_GESTIONNAIRE'], '/utilisateurs', 403];
        yield 'admin, tickets (hérite de gestionnaire)' => [['ROLE_ADMIN'], '/fr/tickets', 200];
        yield 'admin, utilisateurs' => [['ROLE_ADMIN'], '/utilisateurs', 200];
        yield 'simple utilisateur, matériels' => [[], '/materiels', 200];
    }

    public function testSansConnexionLesTicketsEtLesUtilisateursRenvoientVersLaConnexion(): void
    {
        foreach (['/tickets', '/fr/tickets', '/en/tickets', '/utilisateurs', '/mon-compte'] as $adresse) {
            $this->client->request('GET', $adresse);

            self::assertResponseRedirects(null, 302, $adresse);
            self::assertSame('/connexion', $this->cheminDeLaRedirection(), $adresse);
        }
    }

    public function testUnePageNonListeeExigeLaConnexion(): void
    {
        // Refuser par défaut : aucune règle ne cite /fournisseurs, la dernière règle (^/) s'applique.
        $this->client->request('GET', '/fournisseurs');
        self::assertResponseRedirects(null, 302);
        self::assertSame('/connexion', $this->cheminDeLaRedirection());

        $this->client->request('GET', '/connexion');
        self::assertResponseIsSuccessful('La page de connexion reste ouverte à tous.');

        $this->client->loginUser($this->creerUtilisateur('simple@exemple.test'));
        $this->client->request('GET', '/fournisseurs');
        self::assertResponseIsSuccessful();
    }

    public function testUnAppelDirectSansLeDroitDonne403MemeEnPost(): void
    {
        $ticket = $this->creerTicket();
        $this->client->loginUser($this->creerUtilisateur('simple@exemple.test'));

        $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/tickets/'.$ticket->getId().'/cloturer', ['cloture_ticket' => ['resolution' => 'Réglé.']]);
        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        self::assertSame(Ticket::STATUT_OUVERT, $this->em->getRepository(Ticket::class)->find($ticket->getId())->getStatut(), 'Le 403 doit arriver avant toute modification.');
    }

    public function testMonCompteAfficheLUtilisateurConnecte(): void
    {
        $this->client->loginUser($this->creerUtilisateur('ada@exemple.test', [], 'Ada Lovelace'));

        $crawler = $this->client->request('GET', '/mon-compte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ada Lovelace');
        self::assertSelectorTextContains('body', 'ada@exemple.test');
        self::assertCount(1, $crawler->filter('a[href="/deconnexion"]'), 'Un lien de déconnexion.');
    }

    public function testMonCompteMontreChaqueUtilisateurConnecte(): void
    {
        $this->client->loginUser($this->creerUtilisateur('ada@exemple.test', [], 'Ada Lovelace'));
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextNotContains('body', 'Grace Hopper');

        $this->client->loginUser($this->creerUtilisateur('grace@exemple.test', [], 'Grace Hopper'));
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextContains('body', 'Grace Hopper');
        self::assertSelectorTextNotContains('body', 'Ada Lovelace');
    }

    // ---- Exercice : authentificateur par jeton, pare-feu « flux » ----------------------------------

    public function testLeJetonOuvreLeFluxDeSesTicketsOuverts(): void
    {
        $ada = $this->creerUtilisateur('ada@exemple.test', [], 'Ada', 'jeton-de-ada');
        $autre = $this->creerUtilisateur('grace@exemple.test', [], 'Grace');
        $imprimante = $this->creerMateriel('Imprimante', $ada);
        $ecran = $this->creerMateriel('Écran', $ada);
        $scanner = $this->creerMateriel('Scanner', $autre);
        $this->creerTicket('Bourrage papier', $imprimante, Ticket::STATUT_OUVERT, '2026-01-02 10:00');
        $this->creerTicket('Pixel mort', $ecran, Ticket::STATUT_OUVERT, '2026-01-03 10:00');
        $this->creerTicket('Déjà réglé', $ecran, Ticket::STATUT_RESOLU, '2026-01-04 10:00');
        $this->creerTicket('En cours de réparation', $ecran, Ticket::STATUT_EN_COURS, '2026-01-05 10:00');
        $this->creerTicket('Scanner en panne', $scanner, Ticket::STATUT_OUVERT, '2026-01-06 10:00');

        $this->appelerLeFlux('jeton-de-ada');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/json', $this->client->getResponse()->headers->get('Content-Type') ?? '');
        $donnees = json_decode($this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['Pixel mort', 'Bourrage papier'], array_column($donnees['tickets'], 'titre'), 'Les tickets ouverts des matériels affectés à Ada, du plus récent au plus ancien.');
        self::assertSame(['id', 'materiel', 'statut', 'titre'], $this->clesTriees($donnees['tickets'][0]));
        self::assertSame('Écran', $donnees['tickets'][0]['materiel']);
        self::assertSame('ouvert', $donnees['tickets'][0]['statut']);
    }

    public function testUnJetonAbsentDonne401EnJsonFrancais(): void
    {
        $this->creerUtilisateur('ada@exemple.test', [], 'Ada', 'jeton-de-ada');

        $this->appelerLeFlux(null);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['erreur' => "Jeton absent : envoie l'en-tête X-Jeton."], $this->corpsJson());
    }

    public function testUnJetonFauxOuVideDonne401JamaisUneErreur(): void
    {
        $this->creerUtilisateur('ada@exemple.test', [], 'Ada', 'jeton-de-ada');

        foreach (['jeton-qui-n-existe-pas', '', 'JETON-DE-ADA', ' jeton-de-ada'] as $jeton) {
            $this->appelerLeFlux($jeton);

            self::assertResponseStatusCodeSame(401, sprintf('Jeton « %s »', $jeton));
            self::assertSame(['erreur' => 'Jeton invalide.'], $this->corpsJson(), sprintf('Jeton « %s »', $jeton));
        }
    }

    public function testLeFluxNeSeSertPasDeLaSessionEtNePoseAucunCookie(): void
    {
        $this->creerUtilisateur('ada@exemple.test', [], 'Ada', 'jeton-de-ada');

        // Connecté à l'application par le formulaire (cookie de session) : le flux, lui, exige quand même le jeton.
        $this->seConnecterParLeFormulaire('ada@exemple.test', self::MOT_DE_PASSE);
        $this->client->request('GET', '/mon-compte');
        self::assertResponseIsSuccessful();
        $this->appelerLeFlux(null);
        self::assertResponseStatusCodeSame(401);

        $this->client->getCookieJar()->clear();
        $this->appelerLeFlux('jeton-de-ada');
        self::assertResponseIsSuccessful();
        self::assertNull($this->client->getResponse()->headers->get('Set-Cookie'), 'Pare-feu sans état : aucun cookie de session posé par le flux.');
    }

    public function testLeJetonNOuvrePasLesPagesDeLApplication(): void
    {
        $this->creerUtilisateur('ada@exemple.test', [], 'Ada', 'jeton-de-ada');

        $this->client->request('GET', '/materiels', [], [], ['HTTP_X_JETON' => 'jeton-de-ada']);

        self::assertSame('/connexion', $this->cheminDeLaRedirection(), "Le jeton ne vaut que sur /flux : l'application garde son formulaire.");
    }

    public function testLeJetonEstUniqueMaisFacultatif(): void
    {
        $this->creerUtilisateur('un@exemple.test', [], 'Un', null);
        $this->creerUtilisateur('deux@exemple.test', [], 'Deux', null);
        $this->creerUtilisateur('trois@exemple.test', [], 'Trois', 'jeton-partage');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->creerUtilisateur('quatre@exemple.test', [], 'Quatre', 'jeton-partage');
    }

    // ---- Outils -------------------------------------------------------------------------------------

    /** @param list<string> $roles */
    private function creerUtilisateur(string $email, array $roles = [], string $nom = 'Utilisateur de test', ?string $jeton = null): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setNom($nom)->setEmail($email)->setRoles($roles)->setJetonApi($jeton);
        $utilisateur->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($utilisateur, self::MOT_DE_PASSE));
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function creerMateriel(string $nom = 'Imprimante', ?Utilisateur $utilisateur = null): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique')->setUtilisateur($utilisateur);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function creerTicket(string $titre = 'Bourrage', ?Materiel $materiel = null, string $statut = Ticket::STATUT_OUVERT, string $creeLe = '2026-01-01 10:00'): Ticket
    {
        $ticket = (new Ticket())
            ->setTitre($titre)
            ->setDescription('Description du ticket.')
            ->setMateriel($materiel ?? $this->creerMateriel())
            ->setStatut($statut)
            ->setCreeLe(new \DateTimeImmutable($creeLe));
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }

    private function seConnecterParLeFormulaire(string $email, string $motDePasse, bool $seSouvenir = false, ?string $jetonCsrf = null): void
    {
        $this->client->request('GET', '/connexion');
        $valeurs = ['_username' => $email, '_password' => $motDePasse];
        if (null !== $jetonCsrf) {
            $valeurs['_csrf_token'] = $jetonCsrf;
        }
        if ($seSouvenir) {
            $valeurs['_remember_me'] = true;
        }
        $this->client->submitForm('Se connecter', $valeurs);
    }

    private function appelerLeFlux(?string $jeton): void
    {
        $this->client->request('GET', '/flux/mes-tickets', [], [], null === $jeton ? [] : ['HTTP_X_JETON' => $jeton]);
    }

    /** @return array<string, mixed> */
    private function corpsJson(): array
    {
        self::assertStringContainsString('application/json', $this->client->getResponse()->headers->get('Content-Type') ?? '');

        return json_decode($this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $tableau
     *
     * @return list<string>
     */
    private function clesTriees(array $tableau): array
    {
        $cles = array_keys($tableau);
        sort($cles);

        return $cles;
    }

    private function cheminDeLaRedirection(): string
    {
        self::assertTrue($this->client->getResponse()->isRedirect(), 'Une redirection était attendue (code '.$this->client->getResponse()->getStatusCode().').');

        return (string) parse_url($this->client->getResponse()->headers->get('Location'), \PHP_URL_PATH);
    }

    /** @param array<string, mixed> $arguments */
    private function executer(string $nom, array $arguments): CommandTester
    {
        $testeur = new CommandTester((new Application(static::$kernel))->find($nom));
        $testeur->execute($arguments);

        return $testeur;
    }
}
