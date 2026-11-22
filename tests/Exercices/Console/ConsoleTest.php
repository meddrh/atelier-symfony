<?php

namespace App\Tests\Exercices\Console;

use App\Entity\Fournisseur;
use App\Entity\Materiel;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Les fichiers CSV utilisés ici sont dans le même dossier que ce test : ouvre-les pour voir
 * ce que ta commande doit lire (séparateur « ; », première ligne = en-têtes).
 */
class ConsoleTest extends ExerciceTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // ---- Démonstration : l'import du matériel ----------------------------------------

    public function testLaCommandeDImportDuMaterielExisteEtEstDecrite(): void
    {
        $commande = $this->commande('app:materiel:importer');

        self::assertNotSame('', $commande->getDescription(), 'La commande doit avoir une description.');
        self::assertTrue($commande->getDefinition()->hasArgument('fichier'), 'La commande doit avoir un argument « fichier ».');
    }

    public function testLImportDuMaterielEnregistreLesLignesValides(): void
    {
        $testeur = $this->executer('app:materiel:importer', ['fichier' => __DIR__.'/materiels-valides.csv']);

        self::assertSame(0, $testeur->getStatusCode());
        self::assertSame(3, $this->em->getRepository(Materiel::class)->count([]));
        self::assertNotNull($this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Chaise de bureau', 'categorie' => 'Mobilier']));
    }

    public function testLImportDuMaterielSignaleLesLignesRefuseesAvecLeurNumero(): void
    {
        $testeur = $this->executer('app:materiel:importer', ['fichier' => __DIR__.'/materiels-mixtes.csv']);

        self::assertSame(0, $testeur->getStatusCode());
        self::assertSame(2, $this->em->getRepository(Materiel::class)->count([]), 'Les deux lignes valides doivent être importées.');
        self::assertMatchesRegularExpression('/ligne\s+3\b/i', $testeur->getDisplay(), 'La ligne 3 du fichier (nom vide) doit être signalée.');
        self::assertDoesNotMatchRegularExpression('/ligne\s+2\b/i', $testeur->getDisplay(), 'La ligne 2 est valide : elle ne doit pas être signalée.');
    }

    public function testLOptionSimulerNEnregistreRien(): void
    {
        $testeur = $this->executer('app:materiel:importer', [
            'fichier' => __DIR__.'/materiels-valides.csv',
            '--simuler' => true,
        ]);

        self::assertSame(0, $testeur->getStatusCode());
        self::assertSame(0, $this->em->getRepository(Materiel::class)->count([]));
    }

    public function testLImportDuMaterielSurUnFichierAbsentDonneLeCode1(): void
    {
        $testeur = $this->executer('app:materiel:importer', ['fichier' => __DIR__.'/n-existe-pas.csv']);

        self::assertSame(1, $testeur->getStatusCode());
        self::assertSame(0, $this->em->getRepository(Materiel::class)->count([]));
    }

    // ---- Exercice : l'import des fournisseurs ----------------------------------------

    public function testLaCommandeDImportDesFournisseursExisteEtEstDecrite(): void
    {
        $commande = $this->commande('app:fournisseur:importer');

        self::assertNotSame('', $commande->getDescription(), 'La commande doit avoir une description.');
        self::assertTrue($commande->getDefinition()->hasArgument('fichier'), 'La commande doit avoir un argument « fichier ».');
    }

    public function testLesLignesValidesSontImportees(): void
    {
        $testeur = $this->executer('app:fournisseur:importer', ['fichier' => __DIR__.'/fournisseurs-valides.csv']);

        self::assertSame(0, $testeur->getStatusCode(), $testeur->getDisplay());
        self::assertSame(3, $this->em->getRepository(Fournisseur::class)->count([]));

        $dupont = $this->em->getRepository(Fournisseur::class)->findOneBy(['email' => 'contact@dupont-informatique.example']);
        self::assertNotNull($dupont);
        self::assertSame('Dupont Informatique', $dupont->getNom());
        self::assertSame('04 72 00 00 00', $dupont->getTelephone());
    }

    public function testUnTelephoneVideOuAbsentEstEnregistreCommeAbsent(): void
    {
        $this->executer('app:fournisseur:importer', ['fichier' => __DIR__.'/fournisseurs-valides.csv']);

        $videDansLeFichier = $this->em->getRepository(Fournisseur::class)->findOneBy(['email' => 'bonjour@bureau-express.example']);
        $absentDuFichier = $this->em->getRepository(Fournisseur::class)->findOneBy(['email' => 'commandes@papeterie-martin.example']);
        self::assertNotNull($videDansLeFichier);
        self::assertNotNull($absentDuFichier);
        self::assertNull($videDansLeFichier->getTelephone());
        self::assertNull($absentDuFichier->getTelephone());
    }

    public function testLesLignesInvalidesSontSignaleesAvecLeurNumeroEtLesAutresImportees(): void
    {
        $testeur = $this->executer('app:fournisseur:importer', ['fichier' => __DIR__.'/fournisseurs-mixtes.csv']);

        self::assertSame(0, $testeur->getStatusCode(), 'Des lignes refusées ne changent pas le code de retour : le fichier a pu être lu.');
        self::assertSame(2, $this->em->getRepository(Fournisseur::class)->count([]), 'Seules les lignes 2 et 5 sont valides.');

        $affichage = $testeur->getDisplay();
        self::assertMatchesRegularExpression('/ligne\s+3\b/i', $affichage, 'La ligne 3 (adresse e-mail invalide) doit être signalée.');
        self::assertMatchesRegularExpression('/ligne\s+4\b/i', $affichage, 'La ligne 4 (nom vide) doit être signalée.');
        self::assertDoesNotMatchRegularExpression('/ligne\s+2\b/i', $affichage, 'La ligne 2 est valide : elle ne doit pas être signalée.');
        self::assertDoesNotMatchRegularExpression('/ligne\s+5\b/i', $affichage, 'La ligne 5 est valide : elle ne doit pas être signalée.');
    }

    public function testUneAdresseDejaPriseEnBaseEstSignalee(): void
    {
        $this->em->persist((new Fournisseur())->setNom('Déjà là')->setEmail('contact@dupont-informatique.example'));
        $this->em->flush();

        $testeur = $this->executer('app:fournisseur:importer', ['fichier' => __DIR__.'/fournisseurs-valides.csv']);

        self::assertSame(0, $testeur->getStatusCode());
        self::assertSame(3, $this->em->getRepository(Fournisseur::class)->count([]), 'Le fournisseur existant, plus les deux autres lignes.');
        self::assertMatchesRegularExpression('/ligne\s+2\b/i', $testeur->getDisplay(), 'La ligne 2 (adresse déjà en base) doit être signalée.');
    }

    public function testUneAdresseRepeteeDansLeFichierEstSignalee(): void
    {
        $testeur = $this->executer('app:fournisseur:importer', ['fichier' => __DIR__.'/fournisseurs-doublon.csv']);

        self::assertSame(0, $testeur->getStatusCode());
        self::assertSame(1, $this->em->getRepository(Fournisseur::class)->count([]), 'Seule la première des deux lignes est importée.');
        self::assertMatchesRegularExpression('/ligne\s+3\b/i', $testeur->getDisplay(), 'La ligne 3 (adresse déjà vue plus haut) doit être signalée.');
    }

    public function testUnFichierAvecSeulementLEnTeteNImporteRien(): void
    {
        $testeur = $this->executer('app:fournisseur:importer', ['fichier' => __DIR__.'/fournisseurs-en-tete-seul.csv']);

        self::assertSame(0, $testeur->getStatusCode());
        self::assertSame(0, $this->em->getRepository(Fournisseur::class)->count([]));
    }

    public function testUnFichierAbsentDonneLeCode1(): void
    {
        $testeur = $this->executer('app:fournisseur:importer', ['fichier' => __DIR__.'/n-existe-pas.csv']);

        self::assertSame(1, $testeur->getStatusCode());
        self::assertSame(0, $this->em->getRepository(Fournisseur::class)->count([]));
    }

    // ---- Outils de test --------------------------------------------------------------

    private function commande(string $nom): Command
    {
        return (new Application(self::$kernel))->find($nom);
    }

    /**
     * @param array<string, mixed> $entrees arguments et options, comme dans CommandTester::execute()
     */
    private function executer(string $nom, array $entrees): CommandTester
    {
        $testeur = new CommandTester($this->commande($nom));
        $testeur->execute($entrees);

        return $testeur;
    }
}
