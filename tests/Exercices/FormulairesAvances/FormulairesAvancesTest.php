<?php

namespace App\Tests\Exercices\FormulairesAvances;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Tests\Exercices\ExerciceTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Les fichiers envoyés par ces tests sont fabriqués ici, dans un dossier temporaire du système.
 * Le dossier de rangement de TON application est le paramètre « app.dossier_televersements » :
 * en test, il doit pointer vers un dossier jetable (le cache du noyau), jamais vers ton vrai dossier.
 */
class FormulairesAvancesTest extends ExerciceTestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    // Une image PNG valide d'un pixel.
    private const PNG_EN_BASE_64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Filesystem $systemeDeFichiers;
    private string $dossierTemporaire;
    private string $dossierDeRangement = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemeDeFichiers = new Filesystem();
        $this->dossierTemporaire = sys_get_temp_dir().'/formation-televersements-'.bin2hex(random_bytes(6));
        $this->systemeDeFichiers->mkdir($this->dossierTemporaire);

        $this->client = static::createClient();
        $conteneur = static::getContainer();
        $this->em = $conteneur->get('doctrine')->getManager();

        if ($conteneur->hasParameter('app.dossier_televersements')) {
            $this->dossierDeRangement = (string) $conteneur->getParameter('app.dossier_televersements');
            $this->verifierQueLeDossierEstJetable($this->dossierDeRangement, (string) $conteneur->getParameter('kernel.cache_dir'));
            $this->systemeDeFichiers->remove($this->dossierDeRangement);
        }
    }

    protected function tearDown(): void
    {
        $this->systemeDeFichiers->remove($this->dossierTemporaire);
        if ('' !== $this->dossierDeRangement) {
            $this->systemeDeFichiers->remove($this->dossierDeRangement);
        }

        parent::tearDown();
    }

    // ---- Démonstration : la facture et le numéro de série d'un matériel ---------------

    public function testLeDossierDeRangementEstUnParametreRedirigeEnTest(): void
    {
        self::assertTrue(
            static::getContainer()->hasParameter('app.dossier_televersements'),
            'Le dossier de rangement doit être le paramètre « app.dossier_televersements » (config/services.yaml).',
        );
    }

    public function testLeFormulaireDuMaterielProposeUnChampFacture(): void
    {
        $this->client->request('GET', '/materiels/nouveau');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="file"][name="materiel[facture]"]');
        self::assertSelectorExists('form[enctype="multipart/form-data"]');
    }

    public function testUneFacturePdfEstRangeeSousUnNomGenere(): void
    {
        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique'], 'facture', $this->fichier(self::PDF, 'facture-juin.pdf', 'application/pdf'));

        self::assertResponseStatusCodeSame(302);
        $materiel = $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner']);
        self::assertNotNull($materiel);
        $this->assertNomRangeSansDangerEtFichierPresent($materiel->getFacture(), 'facture-juin');
    }

    public function testUnMaterielSansFactureResteValide(): void
    {
        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique']);

        self::assertResponseStatusCodeSame(302);
        self::assertNull($this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner'])->getFacture());
    }

    public function testUnNomDeFichierEnvoyeAvecDesPointsPointsNeSortPasDuDossier(): void
    {
        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique'], 'facture', $this->fichier(self::PDF, '../../x.php', 'application/pdf'));

        $this->assertRienNEstSortiDuDossier('x.php');
        $materiel = $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner']);
        if (null !== $materiel && null !== $materiel->getFacture()) {
            $this->assertNomRangeSansDangerEtFichierPresent($materiel->getFacture(), 'x.php');
        }
    }

    public function testUneExtensionTrompeuseNEstJamaisConservee(): void
    {
        // Le contenu est bien un PDF, mais le visiteur l'a nommé « facture.php ».
        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique'], 'facture', $this->fichier(self::PDF, 'facture.php', 'application/pdf'));

        $materiel = $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner']);
        if (null !== $materiel && null !== $materiel->getFacture()) {
            self::assertDoesNotMatchRegularExpression('/\.(php|phtml|phar)\d?$/i', $materiel->getFacture());
            self::assertMatchesRegularExpression('/\.pdf$/', $materiel->getFacture(), "L'extension doit venir du contenu réel du fichier.");
        }
        $this->assertAucunFichierPhpRange();
    }

    public function testUnFichierQuiNEstPasUnPdfEstRefuseMemeSiLeNavigateurLAnnonceCommeTel(): void
    {
        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique'], 'facture', $this->fichier('<?php echo "pirate";', 'facture.pdf', 'application/pdf'));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Materiel::class)->count([]));
        self::assertSame([], $this->fichiersRanges(), 'Un fichier refusé ne doit pas être rangé.');
    }

    public function testUneFactureDePlusDeDeuxMegaOctetsEstRefusee(): void
    {
        // « 2M » vaut 2 000 000 d'octets : ce fichier dépasse la règle de peu, mais pas la limite de PHP (2 Mio).
        $gros = self::PDF.str_repeat('0', 2_050_000);

        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique'], 'facture', $this->fichier($gros, 'gros.pdf', 'application/pdf'));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Materiel::class)->count([]));
        self::assertSame([], $this->fichiersRanges());
    }

    public function testUneFactureDeMoinsDeDeuxMegaOctetsEstAcceptee(): void
    {
        $presque = self::PDF.str_repeat('0', 1_900_000);

        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique'], 'facture', $this->fichier($presque, 'facture.pdf', 'application/pdf'));

        self::assertResponseStatusCodeSame(302);
    }

    public function testLeNumeroDeSerieEstMisEnMajusculesSansEspaces(): void
    {
        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique', 'numeroSerie' => '  ab 12 cd-9 ']);

        self::assertResponseStatusCodeSame(302);
        self::assertSame('AB12CD-9', $this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner'])->getNumeroSerie());
    }

    public function testUnNumeroDeSerieVideEstEnregistreCommeAbsent(): void
    {
        $this->envoyer('/materiels/nouveau', 'materiel', ['nom' => 'Scanner', 'categorie' => 'Informatique', 'numeroSerie' => '   ']);

        self::assertResponseStatusCodeSame(302);
        self::assertNull($this->em->getRepository(Materiel::class)->findOneBy(['nom' => 'Scanner'])->getNumeroSerie());
    }

    public function testLaDateDeRetourNApparaitQuEnModification(): void
    {
        $this->client->request('GET', '/materiels/nouveau');
        self::assertSelectorNotExists('input[name="materiel[dateRetour]"]', "À la création, il n'y a pas de date de retour.");

        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');
        $this->client->request('GET', '/materiels/'.$materiel->getId().'/modifier');
        self::assertSelectorExists('input[name="materiel[dateRetour]"]', 'En modification, le champ « date de retour » doit apparaître.');
    }

    public function testLaDateDeRetourSEnregistreEnModification(): void
    {
        $materiel = $this->enregistrerMateriel('Écran', 'Informatique');

        $this->envoyer('/materiels/'.$materiel->getId().'/modifier', 'materiel', ['dateRetour' => '2026-12-24']);

        self::assertResponseStatusCodeSame(302);
        $this->em->clear();
        self::assertSame('2026-12-24', $this->em->find(Materiel::class, $materiel->getId())->getDateRetour()->format('Y-m-d'));
    }

    public function testLeThemeEntoureChaqueChampDuFormulaireDuMateriel(): void
    {
        $this->client->request('GET', '/materiels/nouveau');

        self::assertSelectorExists('.champ input[name="materiel[nom]"]', 'Chaque champ doit être dans un élément de classe « champ » (thème de formulaire).');
        self::assertSelectorExists('.champ input[name="materiel[categorie]"]');
    }

    // ---- Exercice : la capture d'écran, les étiquettes et le statut d'un ticket -------

    public function testLeFormulaireDuTicketProposeUnChampCapture(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->client->request('GET', '/materiels/'.$materiel->getId().'/tickets/nouveau');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="file"][name="ticket[capture]"]');
        self::assertSelectorExists('form[enctype="multipart/form-data"]');
    }

    public function testUneCapturePngEstRangeeSousUnNomGenere(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(), 'capture', $this->fichier($this->png(), 'ma capture.png', 'image/png'));

        self::assertResponseStatusCodeSame(302);
        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier']);
        self::assertNotNull($ticket);
        $this->assertNomRangeSansDangerEtFichierPresent($ticket->getCapture(), 'ma capture');
    }

    public function testUneCaptureNommeeAvecDesPointsPointsNeSortPasDuDossier(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(), 'capture', $this->fichier($this->png(), '../../x.php', 'image/png'));

        $this->assertRienNEstSortiDuDossier('x.php');
        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier']);
        if (null !== $ticket && null !== $ticket->getCapture()) {
            $this->assertNomRangeSansDangerEtFichierPresent($ticket->getCapture(), 'x.php');
        }
    }

    public function testUneImageNommeeAvecUneExtensionTrompeuseNEstJamaisRangeeEnPhp(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(), 'capture', $this->fichier($this->png(), 'capture.php', 'image/png'));

        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier']);
        if (null !== $ticket && null !== $ticket->getCapture()) {
            self::assertDoesNotMatchRegularExpression('/\.(php|phtml|phar)\d?$/i', $ticket->getCapture());
            self::assertMatchesRegularExpression('/\.png$/', $ticket->getCapture(), "L'extension doit venir du contenu réel du fichier.");
        }
        $this->assertAucunFichierPhpRange();
    }

    public function testUnFichierQuiNEstPasUneImageEstRefuseMemeSiLeNavigateurLAnnonceCommeTelle(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(), 'capture', $this->fichier('<?php echo "pirate";', 'capture.png', 'image/png'));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Ticket::class)->count([]));
        self::assertSame([], $this->fichiersRanges());
    }

    public function testUneCaptureDePlusDeDeuxMegaOctetsEstRefusee(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');
        $grosse = $this->png().str_repeat('0', 2_050_000);

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(), 'capture', $this->fichier($grosse, 'grosse.png', 'image/png'));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Ticket::class)->count([]));
        self::assertSame([], $this->fichiersRanges());
    }

    public function testUnTicketSansCaptureResteValide(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket());

        self::assertResponseStatusCodeSame(302);
        self::assertNull($this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier'])->getCapture());
    }

    public function testLesEtiquettesSepareesParDesVirgulesDeviennentUneListe(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(['etiquettes' => 'réseau, urgent ,  imprimante']));

        self::assertResponseStatusCodeSame(302);
        self::assertSame(['réseau', 'urgent', 'imprimante'], $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier'])->getEtiquettes());
    }

    public function testDesEtiquettesVidesDonnentUneListeVide(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(['etiquettes' => ' , ,, ']));

        self::assertResponseStatusCodeSame(302);
        self::assertSame([], $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier'])->getEtiquettes());
    }

    public function testLeFormulaireDeModificationReecritLesEtiquettesSeparees(): void
    {
        $ticket = $this->enregistrerTicket($this->enregistrerMateriel('Imprimante', 'Informatique'), ['réseau', 'urgent']);

        $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('ticket[etiquettes]', 'réseau, urgent');
    }

    public function testLeStatutNApparaitPasALaCreation(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->client->request('GET', $this->pageNouveauTicket($materiel));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="ticket[statut]"]');
    }

    public function testLeStatutNePeutPasEtreImposeALaCreation(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->envoyer($this->pageNouveauTicket($materiel), 'ticket', $this->champsTicket(['statut' => 'resolu']));

        $ticket = $this->em->getRepository(Ticket::class)->findOneBy(['titre' => 'Bourrage papier']);
        self::assertTrue(null === $ticket || 'ouvert' === $ticket->getStatut(), 'Un ticket ne peut pas naître « resolu ».');
    }

    public function testLeStatutApparaitEnModificationEtSeChange(): void
    {
        $ticket = $this->enregistrerTicket($this->enregistrerMateriel('Imprimante', 'Informatique'));

        $this->client->request('GET', '/tickets/'.$ticket->getId().'/modifier');
        self::assertSelectorExists('[name="ticket[statut]"]');
        self::assertInputValueSame('ticket[titre]', 'Bourrage papier');

        $this->envoyer('/tickets/'.$ticket->getId().'/modifier', 'ticket', ['statut' => 'en_cours']);

        self::assertResponseRedirects('/materiels/'.$ticket->getMateriel()->getId());
        $this->em->clear();
        self::assertSame('en_cours', $this->em->find(Ticket::class, $ticket->getId())->getStatut());
    }

    public function testModifierUnTicketInconnuRenvoie404(): void
    {
        $this->client->request('GET', '/tickets/999/modifier');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLeThemeEntoureChaqueChampDuFormulaireDuTicket(): void
    {
        $materiel = $this->enregistrerMateriel('Imprimante', 'Informatique');

        $this->client->request('GET', $this->pageNouveauTicket($materiel));

        self::assertSelectorExists('.champ input[name="ticket[titre]"]');
        self::assertSelectorExists('.champ textarea[name="ticket[description]"]');
    }

    // ---- Outils de test --------------------------------------------------------------

    /**
     * Ouvre la page, y lit le jeton CSRF du formulaire, puis envoie les champs (et un fichier éventuel).
     * Les champs non donnés restent vides. Le nom du fichier est celui du visiteur : on peut le choisir hostile.
     *
     * @param array<string, string> $champs
     */
    private function envoyer(string $adresse, string $nomFormulaire, array $champs, ?string $champFichier = null, ?UploadedFile $fichier = null): void
    {
        $crawler = $this->client->request('GET', $adresse);
        self::assertResponseIsSuccessful();

        // Les champs que le formulaire propose déjà (prérempli en modification) gardent leur valeur.
        $formulaire = $crawler->selectButton('Enregistrer')->form();
        $envoi = [];
        foreach ($formulaire->getPhpValues()[$nomFormulaire] ?? [] as $nom => $valeur) {
            if (\is_string($valeur)) {
                $envoi[$nom] = $valeur;
            }
        }
        $envoi = array_merge($envoi, $champs);

        $fichiers = null === $fichier ? [] : [$nomFormulaire => [$champFichier => $fichier]];
        $this->client->request('POST', $adresse, [$nomFormulaire => $envoi], $fichiers);
    }

    /** @param array<string, string> $en_plus */
    private function champsTicket(array $en_plus = []): array
    {
        return array_merge(['titre' => 'Bourrage papier', 'description' => 'Le papier se coince.'], $en_plus);
    }

    private function pageNouveauTicket(Materiel $materiel): string
    {
        return '/materiels/'.$materiel->getId().'/tickets/nouveau';
    }

    private function fichier(string $contenu, string $nomDuVisiteur, string $typeAnnonce): UploadedFile
    {
        $chemin = $this->dossierTemporaire.'/envoi-'.bin2hex(random_bytes(4));
        file_put_contents($chemin, $contenu);

        return new UploadedFile($chemin, $nomDuVisiteur, $typeAnnonce, null, true);
    }

    private function png(): string
    {
        return (string) base64_decode(self::PNG_EN_BASE_64, true);
    }

    /** Le nom rangé est sûr, n'est pas le nom du visiteur, et le fichier existe dans le dossier de rangement. */
    private function assertNomRangeSansDangerEtFichierPresent(?string $nomRange, string $racineDuNomDuVisiteur): void
    {
        self::assertNotNull($nomRange, 'Le nom du fichier rangé doit être enregistré sur l\'entité.');
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[a-z0-9]{2,5}$/', $nomRange, 'Le nom rangé doit être généré : lettres, chiffres, tirets, une extension.');
        self::assertStringNotContainsString($racineDuNomDuVisiteur, $nomRange, "Le nom d'origine ne doit jamais être conservé.");
        self::assertFileExists($this->dossierDeRangement.'/'.$nomRange);
    }

    private function assertRienNEstSortiDuDossier(string $nomHostile): void
    {
        foreach ([$this->dossierDeRangement, \dirname($this->dossierDeRangement), \dirname($this->dossierDeRangement, 2)] as $dossier) {
            self::assertFileDoesNotExist($dossier.'/'.$nomHostile, 'Un fichier a été écrit avec le nom du visiteur : '.$dossier.'/'.$nomHostile);
        }
        foreach ($this->fichiersRanges() as $chemin) {
            self::assertStringNotContainsString($nomHostile, $chemin);
        }
    }

    private function assertAucunFichierPhpRange(): void
    {
        foreach ($this->fichiersRanges() as $chemin) {
            self::assertDoesNotMatchRegularExpression('/\.(php|phtml|phar)\d?$/i', $chemin);
        }
    }

    /** @return list<string> */
    private function fichiersRanges(): array
    {
        if ('' === $this->dossierDeRangement || !is_dir($this->dossierDeRangement)) {
            return [];
        }
        $fichiers = [];
        $parcours = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dossierDeRangement, \FilesystemIterator::SKIP_DOTS));
        foreach ($parcours as $fichier) {
            $fichiers[] = $fichier->getPathname();
        }

        return $fichiers;
    }

    /** Refuse de vider un dossier qui n'est pas jetable : on ne supprime jamais les vrais fichiers du junior. */
    private function verifierQueLeDossierEstJetable(string $dossier, string $dossierDuCache): void
    {
        $jetable = str_starts_with($dossier, rtrim($dossierDuCache, '/').'/') || str_starts_with($dossier, rtrim(sys_get_temp_dir(), '/').'/');
        if (!$jetable) {
            self::fail(sprintf(
                'En test, « app.dossier_televersements » doit pointer vers un dossier jetable (sous « %%kernel.cache_dir%% »), pas vers « %s ». Redéfinis-le dans la section « when@test » de config/services.yaml.',
                $dossier,
            ));
        }
    }

    private function enregistrerMateriel(string $nom, string $categorie): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie($categorie);
        $this->em->persist($materiel);
        $this->em->flush();

        return $materiel;
    }

    /** @param list<string> $etiquettes */
    private function enregistrerTicket(Materiel $materiel, array $etiquettes = []): Ticket
    {
        $ticket = (new Ticket())->setTitre('Bourrage papier')->setDescription('Le papier se coince.')->setMateriel($materiel)->setEtiquettes($etiquettes);
        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }
}
