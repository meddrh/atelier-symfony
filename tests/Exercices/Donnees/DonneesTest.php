<?php

namespace App\Tests\Exercices\Donnees;

use App\Entity\Fournisseur;
use App\Entity\Materiel;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class DonneesTest extends ExerciceTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testLaListeDesMaterielsVientDeLaBase(): void
    {
        $this->enregistrer((new Materiel())->setNom('Imprimante laser')->setCategorie('Informatique'));

        $this->client->request('GET', '/materiels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Imprimante laser');
        self::assertSelectorTextNotContains('body', 'Ordinateur portable');
    }

    public function testLaFicheDUnMaterielVientDeLaBase(): void
    {
        $materiel = (new Materiel())->setNom('Vidéoprojecteur')->setCategorie('Audiovisuel');
        $this->enregistrer($materiel);

        $this->client->request('GET', '/materiels/'.$materiel->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vidéoprojecteur');
        self::assertSelectorTextContains('body', 'Audiovisuel');
    }

    public function testUnMaterielInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/materiels/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnFournisseurSEnregistreEnBase(): void
    {
        $fournisseur = (new Fournisseur())
            ->setNom('Dupont Informatique')
            ->setEmail('contact@dupont-informatique.example')
            ->setTelephone('04 72 00 00 00');
        $this->enregistrer($fournisseur);
        $this->em->clear();

        $relu = $this->em->getRepository(Fournisseur::class)->find($fournisseur->getId());

        self::assertNotNull($relu, 'Le fournisseur doit être relu depuis la base.');
        self::assertSame('Dupont Informatique', $relu->getNom());
        self::assertSame('contact@dupont-informatique.example', $relu->getEmail());
        self::assertSame('04 72 00 00 00', $relu->getTelephone());
    }

    public function testLeTelephoneEstFacultatif(): void
    {
        $fournisseur = (new Fournisseur())->setNom('Bureau Express')->setEmail('bonjour@bureau-express.example');
        $this->enregistrer($fournisseur);
        $this->em->clear();

        $relu = $this->em->getRepository(Fournisseur::class)->find($fournisseur->getId());

        self::assertNull($relu->getTelephone());
    }

    public function testDeuxFournisseursNePartagentPasLeMemeEmail(): void
    {
        $this->enregistrer((new Fournisseur())->setNom('Premier')->setEmail('meme@fournisseur.example'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->enregistrer((new Fournisseur())->setNom('Second')->setEmail('meme@fournisseur.example'));
    }

    public function testLaListeDesFournisseursEstTrieeParNom(): void
    {
        $this->enregistrer(
            (new Fournisseur())->setNom('Zébrures et Cie')->setEmail('z@exemple.example'),
            (new Fournisseur())->setNom('Martin Papeterie')->setEmail('m@exemple.example'),
            (new Fournisseur())->setNom('Alpha Bureau')->setEmail('a@exemple.example'),
        );

        $this->client->request('GET', '/fournisseurs');

        self::assertResponseIsSuccessful();
        $contenu = $this->client->getResponse()->getContent();
        $alpha = strpos($contenu, 'Alpha Bureau');
        $martin = strpos($contenu, 'Martin Papeterie');
        $zebrures = strpos($contenu, 'Zébrures et Cie');
        self::assertNotFalse($alpha);
        self::assertNotFalse($martin);
        self::assertNotFalse($zebrures);
        self::assertTrue($alpha < $martin && $martin < $zebrures, 'Les fournisseurs doivent apparaître par ordre alphabétique de nom.');
    }

    public function testLaListeNeContientPlusLesFournisseursEnDur(): void
    {
        $this->client->request('GET', '/fournisseurs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Dupont Informatique');
    }

    public function testLaFicheDUnFournisseurAfficheSonNomEtSonEmail(): void
    {
        $fournisseur = (new Fournisseur())->setNom('Papeterie Martin')->setEmail('commandes@papeterie-martin.example');
        $this->enregistrer($fournisseur);

        $this->client->request('GET', '/fournisseurs/'.$fournisseur->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Papeterie Martin');
        self::assertSelectorTextContains('body', 'commandes@papeterie-martin.example');
    }

    public function testUnFournisseurInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/fournisseurs/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnIdentifiantNonNumeriqueRenvoie404(): void
    {
        $this->client->request('GET', '/fournisseurs/abc');

        self::assertResponseStatusCodeSame(404);
    }

    private function enregistrer(object ...$entites): void
    {
        foreach ($entites as $entite) {
            $this->em->persist($entite);
        }
        $this->em->flush();
    }
}
