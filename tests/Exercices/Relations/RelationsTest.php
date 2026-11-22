<?php

namespace App\Tests\Exercices\Relations;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Entity\Utilisateur;
use App\Repository\MaterielRepository;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RelationsTest extends ExerciceTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : les utilisateurs et le matériel affecté ---------------------

    public function testUnUtilisateurEstEnregistreAvecSonEmail(): void
    {
        $utilisateur = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');

        $this->em->clear();
        $relu = $this->em->find(Utilisateur::class, $utilisateur->getId());
        self::assertSame('Alice Martin', $relu->getNom());
        self::assertSame('alice@exemple.test', $relu->getEmail());
    }

    public function testDeuxUtilisateursNePeuventPasAvoirLeMemeEmail(): void
    {
        $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');
        $doublon = (new Utilisateur())->setNom('Autre Alice')->setEmail('alice@exemple.test');

        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($doublon);

        self::assertCount(1, $violations, "L'adresse e-mail déjà prise doit produire une violation (règle d'unicité sur l'entité).");
    }

    public function testUnMaterielEstAffecteAUnUtilisateurEtLeCoteInverseLeVoit(): void
    {
        $alice = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique', $alice);

        $this->em->clear();
        $alice = $this->em->find(Utilisateur::class, $alice->getId());
        self::assertCount(1, $alice->getMateriels());
        self::assertSame('Écran', $alice->getMateriels()->first()->getNom());
        self::assertSame($alice->getId(), $this->em->find(Materiel::class, $materiel->getId())->getUtilisateur()->getId());
    }

    public function testLeDepotListeLesMaterielsAffectesTriesParNom(): void
    {
        $alice = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');
        $bruno = $this->enregistrerUtilisateur('Bruno Petit', 'bruno@exemple.test');
        $this->enregistrerMateriel('Souris', 'Informatique', $alice);
        $this->enregistrerMateriel('Clavier', 'Informatique', $alice);
        $this->enregistrerMateriel('Écran', 'Informatique', $bruno);
        $this->enregistrerMateriel('Chaise', 'Mobilier');

        $affectes = static::getContainer()->get(MaterielRepository::class)->findAffectesA($alice);

        self::assertSame(['Clavier', 'Souris'], array_map(static fn (Materiel $m) => $m->getNom(), $affectes));
    }

    public function testLaPageDUnUtilisateurListeSesMateriels(): void
    {
        $alice = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');
        $bruno = $this->enregistrerUtilisateur('Bruno Petit', 'bruno@exemple.test');
        $this->enregistrerMateriel('Clavier', 'Informatique', $alice);
        $this->enregistrerMateriel('Écran', 'Informatique', $bruno);

        $this->client->request('GET', '/utilisateurs/'.$alice->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Alice Martin');
        self::assertSelectorTextContains('body', 'Clavier');
        self::assertSelectorTextNotContains('body', 'Écran');
    }

    public function testLaPageDUnUtilisateurSeTrieParCategorieSurDemande(): void
    {
        $alice = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');
        $this->enregistrerMateriel('Agrafeuse', 'Papeterie', $alice);
        $this->enregistrerMateriel('Zébulon', 'Informatique', $alice);

        $crawler = $this->client->request('GET', '/utilisateurs/'.$alice->getId().'/categorie');

        self::assertResponseIsSuccessful();
        $liens = $crawler->filter('li a')->each(static fn ($noeud) => trim($noeud->text()));
        self::assertSame(['Zébulon', 'Agrafeuse'], $liens, 'Trié par catégorie : Informatique avant Papeterie.');
    }

    public function testUnOrdreDeTriInconnuRenvoie404(): void
    {
        $alice = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');

        $this->client->request('GET', '/utilisateurs/'.$alice->getId().'/prix');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLeDepotRefuseUnOrdreDeTriInconnu(): void
    {
        $alice = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');

        $this->expectException(\InvalidArgumentException::class);
        static::getContainer()->get(MaterielRepository::class)->findAffectesA($alice, 'nom; DROP TABLE materiel');
    }

    public function testUnUtilisateurInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/utilisateurs/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLeFormulaireDuMaterielProposeLesUtilisateurs(): void
    {
        $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');
        $this->enregistrerUtilisateur('Bruno Petit', 'bruno@exemple.test');

        $this->client->request('GET', '/materiels/nouveau');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="materiel[utilisateur]"]');
        self::assertSelectorTextContains('select[name="materiel[utilisateur]"]', 'Alice Martin');
        self::assertSelectorTextContains('select[name="materiel[utilisateur]"]', 'Bruno Petit');
    }

    public function testUnMaterielEstAffecteDepuisLeFormulaire(): void
    {
        $alice = $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');

        $this->soumettre('/materiels/nouveau', 'materiel', [
            'nom' => 'Scanner',
            'categorie' => 'Informatique',
            'utilisateur' => (string) $alice->getId(),
        ]);

        self::assertResponseStatusCodeSame(302);
        $materiel = $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner']);
        self::assertNotNull($materiel);
        self::assertSame($alice->getId(), $materiel->getUtilisateur()?->getId());
    }

    public function testUnMaterielPeutResterSansUtilisateur(): void
    {
        $this->enregistrerUtilisateur('Alice Martin', 'alice@exemple.test');

        $this->soumettre('/materiels/nouveau', 'materiel', [
            'nom' => 'Scanner',
            'categorie' => 'Informatique',
        ]);

        self::assertResponseStatusCodeSame(302);
        self::assertNull($this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner'])->getUtilisateur());
    }

    // ---- Exercice : les tickets d'incident -------------------------------------------

    public function testUnTicketEstLieAUnMaterielEtCommenceOuvert(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $ticket = $this->enregistrerTicket($materiel, 'Bourrage papier');

        $this->em->clear();
        $relu = $this->em->find(Ticket::class, $ticket->getId());
        self::assertSame($materiel->getId(), $relu->getMateriel()->getId());
        self::assertSame('ouvert', $relu->getStatut(), 'Un ticket neuf a le statut « ouvert » par défaut.');
        self::assertInstanceOf(\DateTimeImmutable::class, $relu->getCreeLe());
    }

    public function testUnStatutInconnuEstInvalide(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $ticket = (new Ticket())->setTitre('Bourrage papier')->setDescription('Le papier se coince.')->setMateriel($materiel)->setStatut('archive');

        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($ticket);

        self::assertGreaterThan(0, \count($violations), 'Seuls « ouvert », « en_cours » et « resolu » sont des statuts valides.');
    }

    public function testLeFormulaireDeCreationDUnTicketSAffiche(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/tickets/nouveau');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="ticket[titre]"]');
        self::assertSelectorExists('textarea[name="ticket[description]"]');
    }

    public function testLeFormulaireDUnMaterielInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/materiels/999/tickets/nouveau');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnTicketValideEstCreeLieAuMaterielEtLeVisiteurEstRedirige(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->soumettre('/materiels/'.$materiel->getId().'/tickets/nouveau', 'ticket', [
            'titre' => 'Bourrage papier',
            'description' => 'Le papier se coince au troisième passage.',
        ]);

        self::assertResponseRedirects('/materiels/'.$materiel->getId());
        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier']);
        self::assertNotNull($ticket, 'Le ticket valide doit être enregistré.');
        self::assertSame($materiel->getId(), $ticket->getMateriel()->getId(), 'Le ticket doit être lié au matériel de la page.');
        self::assertSame('ouvert', $ticket->getStatut());
        self::assertLessThan(120, time() - $ticket->getCreeLe()->getTimestamp(), 'La date de création doit être celle de maintenant.');
    }

    public function testUnMessageFlashConfirmeLaCreationDUnTicket(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->soumettre('/materiels/'.$materiel->getId().'/tickets/nouveau', 'ticket', [
            'titre' => 'Bourrage papier',
            'description' => 'Le papier se coince.',
        ]);
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash');
    }

    public function testUnTicketSansTitreEstRefuseAvecLeCode422(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->soumettre('/materiels/'.$materiel->getId().'/tickets/nouveau', 'ticket', [
            'titre' => '',
            'description' => 'Le papier se coince.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Ticket::class)->count([]));
    }

    public function testUnTicketSansDescriptionEstRefuseAvecLeCode422(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->soumettre('/materiels/'.$materiel->getId().'/tickets/nouveau', 'ticket', [
            'titre' => 'Bourrage papier',
            'description' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Ticket::class)->count([]));
    }

    public function testLaFicheDuMaterielListeSesTicketsDuPlusRecentAuPlusAncien(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $this->enregistrerTicket($materiel, 'Ticket du milieu', new \DateTimeImmutable('-2 days'));
        $this->enregistrerTicket($materiel, 'Ticket le plus ancien', new \DateTimeImmutable('-5 days'));
        $this->enregistrerTicket($materiel, 'Ticket le plus récent', new \DateTimeImmutable('-1 hour'));
        // Le matériel de ce test n'a pas relu ses tickets : on repart d'un gestionnaire vide, comme une vraie requête.
        $this->em->clear();

        $crawler = $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertResponseIsSuccessful();
        $titres = $crawler->filter('.ticket')->each(static fn ($noeud) => trim($noeud->text()));
        self::assertCount(3, $titres, 'Chaque ticket est dans un élément de classe « ticket ».');
        self::assertStringContainsString('Ticket le plus récent', $titres[0]);
        self::assertStringContainsString('Ticket du milieu', $titres[1]);
        self::assertStringContainsString('Ticket le plus ancien', $titres[2]);
    }

    public function testLaFicheNeListePasLesTicketsDUnAutreMateriel(): void
    {
        $imprimante = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $ecran = $this->enregistrerMateriel('Écran', 'Informatique');
        $this->enregistrerTicket($ecran, 'Pixel mort');

        $this->client->request('GET', '/materiels/'.$imprimante->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.ticket');
        self::assertSelectorTextNotContains('body', 'Pixel mort');
    }

    public function testLaFicheSansTicketLeDit(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertSelectorTextContains('body', 'Aucun ticket');
    }

    public function testUnMaterielQuiADesTicketsNEstPasSupprime(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $ticket = $this->enregistrerTicket($materiel, 'Bourrage papier');

        $crawler = $this->client->request('GET', '/materiels');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertResponseRedirects('/materiels', null, 'Le refus se termine par une redirection, pas par une erreur 500.');
        $this->client->followRedirect();
        self::assertSelectorExists('.flash', 'Un message doit expliquer le refus.');
        $this->em->clear();
        self::assertNotNull($this->em->find(Materiel::class, $materiel->getId()), 'Le matériel doit rester en base.');
        self::assertNotNull($this->em->find(Ticket::class, $ticket->getId()), 'Le ticket doit rester en base.');
    }

    public function testUnMaterielSansTicketEstToujoursSupprime(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $crawler = $this->client->request('GET', '/materiels');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        $this->em->clear();
        self::assertNull($this->em->find(Materiel::class, $materiel->getId()));
    }

    // ---- Outils de test --------------------------------------------------------------

    /**
     * Ouvre la page du formulaire, remplit les champs donnés et l'envoie, jeton CSRF compris.
     * Le bouton s'appelle « Enregistrer ».
     *
     * @param array<string, string> $champs
     */
    private function soumettre(string $adresse, string $nomFormulaire, array $champs): void
    {
        $crawler = $this->client->request('GET', $adresse);
        self::assertResponseIsSuccessful();

        $valeurs = [];
        foreach ($champs as $nom => $valeur) {
            $valeurs[$nomFormulaire.'['.$nom.']'] = $valeur;
        }

        $this->client->submit($crawler->selectButton('Enregistrer')->form($valeurs));
    }

    private function enregistrerUtilisateur(string $nom, string $email): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setNom($nom)->setEmail($email);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function enregistrerMateriel(string $nom, string $categorie, ?Utilisateur $utilisateur = null): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie($categorie)->setUtilisateur($utilisateur);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function enregistrerTicket(Materiel $materiel, string $titre, ?\DateTimeImmutable $creeLe = null): Ticket
    {
        $ticket = (new Ticket())->setTitre($titre)->setDescription('Description de « '.$titre.' ».')->setMateriel($materiel);
        if (null !== $creeLe) {
            $ticket->setCreeLe($creeLe);
        }
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }
}
