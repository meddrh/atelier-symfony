<?php

namespace App\Tests\Exercices\Formulaires;

use App\Entity\Fournisseur;
use App\Entity\Materiel;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class FormulairesTest extends ExerciceTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : le matériel -------------------------------------------------

    public function testLeFormulaireDeCreationDUnMaterielSAffiche(): void
    {
        $this->client->request('GET', '/materiels/nouveau');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="materiel[nom]"]');
        self::assertSelectorExists('input[name="materiel[categorie]"]');
    }

    public function testUnMaterielValideEstCreeEtLeVisiteurEstRedirige(): void
    {
        $this->soumettre('/materiels/nouveau', 'materiel', [
            'nom' => 'Scanner de documents',
            'categorie' => 'Informatique',
        ]);

        self::assertResponseStatusCodeSame(302);
        $materiel = $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner de documents']);
        self::assertNotNull($materiel, 'Le matériel valide doit être enregistré.');
        self::assertSame('Informatique', $materiel->getCategorie());
    }

    public function testUnMessageFlashConfirmeLaCreationDUnMateriel(): void
    {
        $this->soumettre('/materiels/nouveau', 'materiel', [
            'nom' => 'Scanner de documents',
            'categorie' => 'Informatique',
        ]);
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash', 'Après la redirection, un message flash doit être affiché dans un élément de classe « flash ».');
    }

    public function testUnMaterielSansNomEstRefuseAvecLeCode422(): void
    {
        $this->soumettre('/materiels/nouveau', 'materiel', [
            'nom' => '',
            'categorie' => 'Informatique',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Materiel::class)->count([]));
    }

    public function testUnMaterielSeModifie(): void
    {
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');

        $this->soumettre('/materiels/'.$materiel->getId().'/modifier', 'materiel', [
            'nom' => 'Écran 27 pouces',
        ]);

        self::assertResponseStatusCodeSame(302);
        $this->em->clear();
        self::assertSame('Écran 27 pouces', $this->em->find(Materiel::class, $materiel->getId())->getNom());
    }

    public function testUnMaterielEstSupprimeAvecLeJetonDuFormulaire(): void
    {
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');

        $crawler = $this->client->request('GET', '/materiels');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        $this->em->clear();
        self::assertNull($this->em->find(Materiel::class, $materiel->getId()), 'Le formulaire de la liste, avec son jeton, doit supprimer le matériel.');
    }

    public function testLaSuppressionDUnMaterielSansJetonNeSupprimeRien(): void
    {
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');

        $this->client->request('POST', '/materiels/'.$materiel->getId().'/supprimer');

        $this->em->clear();
        self::assertNotNull($this->em->find(Materiel::class, $materiel->getId()));
    }

    // ---- Exercice : les fournisseurs -------------------------------------------------

    public function testLeFormulaireDAjoutDUnFournisseurSAffiche(): void
    {
        $this->client->request('GET', '/fournisseurs/nouveau');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="fournisseur[nom]"]');
        self::assertSelectorExists('input[name="fournisseur[email]"]');
        self::assertSelectorExists('input[name="fournisseur[telephone]"]');
    }

    public function testUnFournisseurValideEstCreeEtLeVisiteurEstRedirige(): void
    {
        $this->soumettre('/fournisseurs/nouveau', 'fournisseur', [
            'nom' => 'Dupont Informatique',
            'email' => 'contact@dupont-informatique.example',
            'telephone' => '04 72 00 00 00',
        ]);

        self::assertResponseStatusCodeSame(302);
        $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneBy(['email' => 'contact@dupont-informatique.example']);
        self::assertNotNull($fournisseur, 'Le fournisseur valide doit être enregistré.');
        self::assertSame('Dupont Informatique', $fournisseur->getNom());
        self::assertSame('04 72 00 00 00', $fournisseur->getTelephone());
    }

    public function testUnMessageFlashConfirmeLAjoutDUnFournisseur(): void
    {
        $this->soumettre('/fournisseurs/nouveau', 'fournisseur', [
            'nom' => 'Dupont Informatique',
            'email' => 'contact@dupont-informatique.example',
        ]);
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash', 'Après la redirection, un message flash doit être affiché dans un élément de classe « flash ».');
    }

    public function testLeTelephoneResteFacultatif(): void
    {
        $this->soumettre('/fournisseurs/nouveau', 'fournisseur', [
            'nom' => 'Bureau Express',
            'email' => 'bonjour@bureau-express.example',
            'telephone' => '',
        ]);

        self::assertResponseStatusCodeSame(302);
        $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneBy(['email' => 'bonjour@bureau-express.example']);
        self::assertNotNull($fournisseur);
        self::assertEmpty($fournisseur->getTelephone());
    }

    public function testUnEmailInvalideEstRefuseAvecLeCode422(): void
    {
        $this->soumettre('/fournisseurs/nouveau', 'fournisseur', [
            'nom' => 'Dupont Informatique',
            'email' => 'pas-une-adresse',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Fournisseur::class)->count([]));
    }

    public function testUnNomVideEstRefuseAvecLeCode422(): void
    {
        $this->soumettre('/fournisseurs/nouveau', 'fournisseur', [
            'nom' => '',
            'email' => 'contact@dupont-informatique.example',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Fournisseur::class)->count([]));
    }

    public function testUnEmailDejaPrisEstRefuseAvecLeCode422(): void
    {
        $this->enregistrerFournisseur('Premier', 'meme@fournisseur.example');

        $this->soumettre('/fournisseurs/nouveau', 'fournisseur', [
            'nom' => 'Second',
            'email' => 'meme@fournisseur.example',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->em->getRepository(Fournisseur::class)->count([]));
    }

    public function testLeFormulaireDeModificationEstPrerempli(): void
    {
        $fournisseur = $this->enregistrerFournisseur('Papeterie Martin', 'commandes@papeterie-martin.example');

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId().'/modifier');

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('fournisseur[nom]', 'Papeterie Martin');
        self::assertInputValueSame('fournisseur[email]', 'commandes@papeterie-martin.example');
    }

    public function testUnFournisseurSeModifieEtGardeSonEmail(): void
    {
        $fournisseur = $this->enregistrerFournisseur('Papeterie Martin', 'commandes@papeterie-martin.example');

        $this->soumettre('/fournisseurs/'.$fournisseur->getId().'/modifier', 'fournisseur', [
            'nom' => 'Papeterie Martin et fils',
        ]);

        self::assertResponseStatusCodeSame(302, 'Garder son propre e-mail ne doit pas être refusé comme un doublon.');
        $this->em->clear();
        self::assertSame('Papeterie Martin et fils', $this->em->find(Fournisseur::class, $fournisseur->getId())->getNom());
    }

    public function testUnFournisseurNePeutPasPrendreLEmailDUnAutre(): void
    {
        $this->enregistrerFournisseur('Premier', 'premier@fournisseur.example');
        $second = $this->enregistrerFournisseur('Second', 'second@fournisseur.example');

        $this->soumettre('/fournisseurs/'.$second->getId().'/modifier', 'fournisseur', [
            'email' => 'premier@fournisseur.example',
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->em->clear();
        self::assertSame('second@fournisseur.example', $this->em->find(Fournisseur::class, $second->getId())->getEmail());
    }

    public function testModifierUnFournisseurInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/fournisseurs/999/modifier');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnFournisseurEstSupprimeAvecLeJetonDuFormulaire(): void
    {
        $fournisseur = $this->enregistrerFournisseur('Bureau Express', 'bonjour@bureau-express.example');

        $crawler = $this->client->request('GET', '/fournisseurs');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->find(Fournisseur::class, $fournisseur->getId()), 'Le formulaire de la liste, avec son jeton, doit supprimer le fournisseur.');
    }

    public function testLaSuppressionSansJetonNeSupprimeRien(): void
    {
        $fournisseur = $this->enregistrerFournisseur('Bureau Express', 'bonjour@bureau-express.example');

        $this->client->request('POST', '/fournisseurs/'.$fournisseur->getId().'/supprimer');

        $this->em->clear();
        self::assertNotNull($this->em->find(Fournisseur::class, $fournisseur->getId()), 'Sans jeton, le fournisseur doit rester en base.');
    }

    public function testLaSuppressionAvecUnJetonFauxNeSupprimeRien(): void
    {
        $fournisseur = $this->enregistrerFournisseur('Bureau Express', 'bonjour@bureau-express.example');

        $this->client->request('POST', '/fournisseurs/'.$fournisseur->getId().'/supprimer', ['_token' => 'jeton-fabrique']);

        $this->em->clear();
        self::assertNotNull($this->em->find(Fournisseur::class, $fournisseur->getId()), 'Avec un faux jeton, le fournisseur doit rester en base.');
    }

    public function testLaSuppressionNEstPasPossibleParUnSimpleLien(): void
    {
        $fournisseur = $this->enregistrerFournisseur('Bureau Express', 'bonjour@bureau-express.example');

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId().'/supprimer');

        self::assertResponseStatusCodeSame(405);
        $this->em->clear();
        self::assertNotNull($this->em->find(Fournisseur::class, $fournisseur->getId()));
    }

    // ---- Outils de test --------------------------------------------------------------

    /**
     * Ouvre la page du formulaire, remplit les champs donnés (les autres gardent leur valeur
     * de départ) et l'envoie, jeton CSRF du formulaire compris. Le bouton s'appelle « Enregistrer ».
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

    private function enregistrerMateriel(string $nom, string $categorie): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie($categorie);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    private function enregistrerFournisseur(string $nom, string $email): Fournisseur
    {
        $fournisseur = (new Fournisseur())->setNom($nom)->setEmail($email);
        $this->em->persist($fournisseur);
        $this->em->flush();

        return $fournisseur;
    }
}
