<?php

namespace App\Tests\Formation\Controller;

use App\Formation\Parcours\StatutBloc;
use App\Tests\Formation\AvecProgressionVierge;
use App\Tests\Formation\FabriqueLancement;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Tests\Formation\PlateformeWebTestCase;

/**
 * Ordre du catalogue de test : rappel-a, s1-preparation, s1-demonstration, s1-exercice,
 * s2-preparation, s2-exercice (durées 15, 15, 15, 60, 15, 30).
 */
class LeconTest extends PlateformeWebTestCase
{
    use AvecProgressionVierge;
    use AvecLignesDeBloc;

    private const PHRASE_COMPARAISON = 'Compare ta fiche avec la correction. Ce n\'est pas noté : repère seulement ce que tu as oublié ou fait autrement, puis passe à la suite.';
    private const PHRASE_AIDE = 'Mohammed va t\'aider, demande-lui directement.';

    protected function setUp(): void
    {
        $this->repartirDeZero();
    }

    public function testUneFicheIncompleteEstRefusee(): void
    {
        $this->faire('rappel-a');
        $cas = [
            'un champ vide' => ['GET /x', ''],
            'un champ fait d\'espaces' => ['GET /x', "  \n\t  "],
            'tous vides' => ['', ''],
        ];
        foreach ($cas as $nom => [$routes, $ordre]) {
            self::ensureKernelShutdown();
            $client = static::createClient();
            $client->request('GET', '/formation/lecon/s1-preparation');

            $crawler = $client->submitForm('Enregistrer ma fiche', ['fiche[routes]' => $routes, 'fiche[ordre]' => $ordre]);

            self::assertStringContainsString('Remplis chaque partie de ta fiche avant de continuer.', $crawler->filter('body')->text(), $nom);
            self::assertFalse($this->parcours()->ficheComplete('s1-preparation'), $nom);
            self::assertSame(StatutBloc::EnCours, $this->parcours()->statut('s1-preparation'), $nom);
            self::assertSame(StatutBloc::Verrouille, $this->parcours()->statut('s1-exercice'), $nom);
            self::assertFalse($this->parcours()->peutTerminer('s1-preparation'), $nom);
        }
    }

    public function testUneReponseTropLongueEstRefuseeSansRienEnregistrerNiTronquer(): void
    {
        $this->faire('rappel-a');
        // Fiche incomplète déjà enregistrée : le formulaire est affiché, et ce contenu doit rester intact.
        $this->progression()->enregistrerFiche('s1-preparation', ['routes' => 'avant', 'ordre' => '']);
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/s1-preparation');
        $tropLongue = str_repeat('é', 5001);

        $crawler = $client->submitForm('Enregistrer ma fiche', ['fiche[routes]' => $tropLongue, 'fiche[ordre]' => 'courte']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Ta réponse « Routes à créer » dépasse 5 000 caractères : raccourcis-la.', $crawler->filter('body')->text());
        self::assertStringNotContainsString('Ordre des étapes » dépasse', $crawler->filter('body')->text());
        self::assertSame($tropLongue, $crawler->filter('textarea[name="fiche[routes]"]')->text(), 'la saisie est réaffichée telle quelle');
        self::assertSame('courte', $crawler->filter('textarea[name="fiche[ordre]"]')->text());
        self::assertSame(['routes' => 'avant', 'ordre' => ''], $this->progression()->fiche('s1-preparation'), 'rien n\'est enregistré');
    }

    public function testUneReponseDeExactement5000CaracteresEstAcceptee(): void
    {
        $this->faire('rappel-a');
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/s1-preparation');
        $limite = str_repeat('é', 5000);

        $client->submitForm('Enregistrer ma fiche', ['fiche[routes]' => $limite, 'fiche[ordre]' => 'b']);

        self::assertSame($limite, $this->progression()->fiche('s1-preparation')['routes']);
    }

    public function testUneFicheCompleteAfficheLaComparaison(): void
    {
        $this->faire('rappel-a');
        $client = static::createClient();
        $client->followRedirects();
        $client->request('GET', '/formation/lecon/s1-preparation');

        $crawler = $client->submitForm('Enregistrer ma fiche', ['fiche[routes]' => 'GET /ma-route', 'fiche[ordre]' => '1. Mon contrôleur']);

        $texte = $crawler->filter('body')->text();
        self::assertStringContainsString('Ta fiche', $texte);
        self::assertStringContainsString('Fiche corrigée', $texte);
        self::assertStringContainsString(self::PHRASE_COMPARAISON, $texte);
        self::assertStringContainsString('GET /ma-route', $texte);
        self::assertStringContainsString('GET /fournisseurs', $texte, 'la correction est affichée');
        self::assertCount(1, $crawler->selectButton('Passer à la suite'));
        self::assertTrue($this->parcours()->ficheComplete('s1-preparation'));
        // Passer à la suite est un acte distinct : le bloc n'est pas encore fait.
        self::assertSame(StatutBloc::EnCours, $this->parcours()->statut('s1-preparation'));
    }

    public function testUneFicheEnvoyeeSansJetonEstRefusee(): void
    {
        $this->faire('rappel-a');
        $this->progression()->enregistrerFiche('s1-preparation', ['routes' => 'avant', 'ordre' => 'avant']);
        $client = static::createClient();

        $client->request('POST', '/formation/lecon/s1-preparation/fiche', ['fiche' => ['routes' => 'apres', 'ordre' => 'apres']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['routes' => 'avant', 'ordre' => 'avant'], $this->progression()->fiche('s1-preparation'));
    }

    public function testUneFicheEnvoyeeAvecUnJetonForgeEstRefusee(): void
    {
        $this->faire('rappel-a');
        $this->progression()->enregistrerFiche('s1-preparation', ['routes' => 'avant', 'ordre' => 'avant']);
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/s1-preparation');

        $client->request('POST', '/formation/lecon/s1-preparation/fiche', ['_token' => 'jeton-forge', 'fiche' => ['routes' => 'apres', 'ordre' => 'apres']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['routes' => 'avant', 'ordre' => 'avant'], $this->progression()->fiche('s1-preparation'));
    }

    public function testUneFicheSurUnBlocVerrouilleEstRefusee(): void
    {
        $this->faire('rappel-a');
        $client = static::createClient();
        $jeton = $this->jetonFiche($client);
        $this->progression()->enregistrerFiche('s2-preparation', ['entites' => 'avant']);

        $client->request('POST', '/formation/lecon/s2-preparation/fiche', ['_token' => $jeton, 'fiche' => ['entites' => 'apres']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['entites' => 'avant'], $this->progression()->fiche('s2-preparation'));
    }

    public function testUneFicheSurUnBlocQuiNEstPasUnePreparationRenvoie404(): void
    {
        $this->faire('rappel-a');
        $client = static::createClient();
        $jeton = $this->jetonFiche($client);
        $this->faire('s1-preparation');

        // s1-demonstration est le bloc courant (ouvert) mais n'a pas de fiche.
        $client->request('POST', '/formation/lecon/s1-demonstration/fiche', ['_token' => $jeton, 'fiche' => ['routes' => 'apres']]);

        self::assertResponseStatusCodeSame(404);
        self::assertNull($this->progression()->fiche('s1-demonstration'));
    }

    public function testPasserALaSuiteTermineLaPreparation(): void
    {
        $this->faire('rappel-a');
        $client = static::createClient();
        $client->followRedirects();
        $client->request('GET', '/formation/lecon/s1-preparation');
        $client->submitForm('Enregistrer ma fiche', ['fiche[routes]' => 'a', 'fiche[ordre]' => 'b']);
        $client->submitForm('Passer à la suite');

        self::assertSame(StatutBloc::Fait, $this->parcours()->statut('s1-preparation'));
        self::assertSame(StatutBloc::EnCours, $this->parcours()->statut('s1-demonstration'));
    }

    public function testTerminerUnBlocFuturEstRefuse(): void
    {
        $client = static::createClient();
        $jeton = $this->jetonTerminer($client);
        $avant = $this->progression()->blocsFaits();

        $client->request('POST', '/formation/lecon/s1-demonstration/terminer', ['_token' => $jeton]);

        self::assertResponseStatusCodeSame(403);
        self::assertEquals($avant, $this->progression()->blocsFaits());
        self::assertSame([], $this->progression()->blocsFaits());
    }

    public function testTerminerDeuxFoisNeChangeRien(): void
    {
        $client = static::createClient();
        $jeton = $this->jetonTerminer($client);

        $client->request('POST', '/formation/lecon/rappel-a/terminer', ['_token' => $jeton]);
        self::assertResponseRedirects();
        $apresLePremier = $this->progression()->blocsFaits();
        self::assertSame(['rappel-a'], array_keys($apresLePremier));

        // Le message du premier clic est lu (puis consommé) ici : celui du second ne peut pas s'y confondre.
        self::assertStringContainsString('Bloc terminé.', $client->followRedirect()->filter('body')->text());

        // Double clic : le bloc est déjà fait, le courant est le suivant.
        $client->request('POST', '/formation/lecon/rappel-a/terminer', ['_token' => $jeton]);
        self::assertResponseRedirects();
        $texte = $client->followRedirect()->filter('body')->text();
        self::assertStringContainsString('Ce bloc est déjà terminé.', $texte);
        self::assertStringNotContainsString('Bloc terminé.', $texte);
        self::assertEquals($apresLePremier, $this->progression()->blocsFaits());
        self::assertSame(StatutBloc::EnCours, $this->parcours()->statut('s1-preparation'));
    }

    public function testUneSeanceDe15MinutesRenvoieALaSeanceApresUnBloc(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/rappel-a');
        $client->submitForm('Terminé');

        self::assertResponseRedirects('/formation');
    }

    public function testUneSeanceDe30MinutesEnchaineLeBlocSuivantPuisSArrete(): void
    {
        $this->progression()->changerDuree(30);
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/rappel-a');
        $client->submitForm('Terminé');
        self::assertResponseRedirects('/formation/lecon/s1-preparation?reste=15');

        // Le budget suit le bloc suivant : la fiche, puis « Passer à la suite », ferment la séance.
        $client->followRedirects();
        $client->request('GET', '/formation/lecon/s1-preparation?reste=15');
        $client->submitForm('Enregistrer ma fiche', ['fiche[routes]' => 'a', 'fiche[ordre]' => 'b']);
        $client->submitForm('Passer à la suite');
        self::assertSame(StatutBloc::Fait, $this->parcours()->statut('s1-preparation'));
        self::assertSame('/formation', $client->getRequest()->getPathInfo());
    }

    public function testLaFinDuDernierBlocAvantUnExerciceTropLongRenvoieALaSeance(): void
    {
        // durée réglée => minutes restantes avant la démonstration
        foreach ([30 => 30, 60 => 30] as $duree => $reste) {
            $this->repartirDeZero();
            $this->progression()->changerDuree($duree);
            $this->faire('rappel-a', 's1-preparation');
            self::ensureKernelShutdown();
            $client = static::createClient();
            $client->request('GET', '/formation/lecon/s1-demonstration?etape=2&reste='.$reste);
            $client->submitForm('Terminé');

            // Il reste 15 minutes : l'exercice de 60 minutes ne tient pas, l'accueil ne l'annonçait pas.
            self::assertResponseRedirects('/formation', null, sprintf('séance de %d minutes', $duree));
            self::assertSame(StatutBloc::Fait, $this->parcours()->statut('s1-demonstration'), sprintf('séance de %d minutes', $duree));
        }
    }

    public function testUnExerciceQuiTientDansLeBudgetEstEnchaine(): void
    {
        $this->progression()->changerDuree(60);
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration', 's1-exercice');
        $this->progression()->enregistrerFiche('s2-preparation', ['entites' => 'Fournisseur']);
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/s2-preparation');
        $client->submitForm('Passer à la suite');

        // 60 - 15 = 45 minutes restantes : l'exercice de 30 minutes tient.
        self::assertResponseRedirects('/formation/lecon/s2-exercice?reste=45');
    }

    public function testUnResteForgeNeDepassePasLesDureesPermises(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/rappel-a?reste=9999');
        $client->submitForm('Terminé');

        self::assertResponseRedirects('/formation');
    }

    public function testLeResteNEstAccepteQueJusquALaPlusLongueDureePermise(): void
    {
        // « reste » reçu => où va le « Terminé » du rappel (15 min), la durée réglée étant 15 :
        // accepté, 60 - 15 = 45 min restantes, la fiche (15 min) suit ; refusé, on retombe sur 15 - 15 = 0 : retour à la séance.
        $cas = [
            '60 (la plus longue durée)' => ['60', '/formation/lecon/s1-preparation?reste=45'],
            '61 (juste au-dessus)' => ['61', '/formation'],
            '99' => ['99', '/formation'],
            '100 (trois chiffres)' => ['100', '/formation'],
        ];
        foreach ($cas as $nom => [$reste, $destination]) {
            $this->repartirDeZero();
            self::ensureKernelShutdown();
            $client = static::createClient();
            $client->request('GET', '/formation/lecon/rappel-a?reste='.$reste);
            $client->submitForm('Terminé');

            self::assertResponseRedirects($destination, null, 'reste='.$nom);
        }
    }

    public function testTerminerUnExerciceEstRefuse(): void
    {
        $this->faire('rappel-a', 's1-preparation');
        $client = static::createClient();
        $jeton = $this->jetonTerminer($client, 's1-demonstration');
        $this->faire('s1-demonstration');
        $avant = $this->progression()->blocsFaits();

        $client->request('POST', '/formation/lecon/s1-exercice/terminer', ['_token' => $jeton]);

        self::assertEquals($avant, $this->progression()->blocsFaits());
        self::assertSame(StatutBloc::EnCours, $this->parcours()->statut('s1-exercice'));
    }

    public function testTerminerSansJetonNeChangeRien(): void
    {
        $client = static::createClient();
        $client->request('POST', '/formation/lecon/rappel-a/terminer');

        self::assertSame([], $this->progression()->blocsFaits());
    }

    public function testTerminerUnePreparationSansFicheCompleteEstRefuse(): void
    {
        $client = static::createClient();
        $jeton = $this->jetonTerminer($client);
        $this->faire('rappel-a');

        $client->request('POST', '/formation/lecon/s1-preparation/terminer', ['_token' => $jeton]);

        self::assertSame(['rappel-a'], array_keys($this->progression()->blocsFaits()));
        self::assertSame(StatutBloc::EnCours, $this->parcours()->statut('s1-preparation'));
    }

    public function testOuvrirUnBlocVerrouilleRenvoie403(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Ce bloc n\'est pas encore ouvert. Termine d\'abord : « Rappel HTTP ».', $crawler->filter('body')->text());
    }

    public function testUnBlocInconnuRenvoie404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/n-existe-pas');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnRappelEstRenduEnMarkdown(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/lecon/rappel-a');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.prose h1', 'Rappel HTTP');
        self::assertSelectorTextContains('.prose p', 'Une requête, une réponse.');
        self::assertCount(1, $crawler->selectButton('Terminé'));
    }

    public function testLaColonneDeGaucheListeLesBlocsDuSujet(): void
    {
        $this->faire('rappel-a', 's1-preparation');
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration');

        $colonne = $crawler->filter('.lecon__liste');
        self::assertSame('Fait', $this->etatDeLaLigne($colonne, 'Préparer le premier sujet', 'Fiche de préparation'));
        self::assertSame('En cours', $this->etatDeLaLigne($colonne, 'Démonstration', 'Démonstration'));
        self::assertSame('Verrouillé', $this->etatDeLaLigne($colonne, 'Exercice', 'Exercice'));
        self::assertCount(0, $colonne->filter('a[href="/formation/lecon/s1-exercice"]'));
    }

    public function testLaDemonstrationAvanceParEtapes(): void
    {
        $this->faire('rappel-a', 's1-preparation');
        $client = static::createClient();

        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration');
        self::assertStringContainsString('Première étape.', $crawler->filter('.prose')->text());
        self::assertStringNotContainsString('Deuxième étape.', $crawler->filter('.prose')->text());
        self::assertCount(0, $crawler->selectButton('Terminé'));
        self::assertSame('/formation/lecon/s1-demonstration?etape=2', $crawler->selectLink('Étape suivante')->attr('href'));

        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration?etape=2');
        self::assertStringContainsString('Deuxième étape.', $crawler->filter('.prose')->text());
        self::assertStringNotContainsString('Première étape.', $crawler->filter('.prose')->text());
        self::assertCount(1, $crawler->selectButton('Terminé'));

        // Étape hors bornes ou illisible : on retombe sur une étape existante.
        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration?etape=99');
        self::assertStringContainsString('Deuxième étape.', $crawler->filter('.prose')->text());
        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration?etape=abc');
        self::assertStringContainsString('Première étape.', $crawler->filter('.prose')->text());
    }

    public function testLEtapeMemoriseeEstToujoursUneEtapeExistante(): void
    {
        $this->faire('rappel-a', 's1-preparation');
        $client = static::createClient();

        $client->request('GET', '/formation/lecon/s1-demonstration?etape=99');
        self::assertSame(2, $this->progression()->etapeMemorisee('s1-demonstration'), 'hors bornes : la dernière étape (la démonstration en a 2)');

        $client->request('GET', '/formation/lecon/s1-demonstration?etape=abc');
        self::assertSame(1, $this->progression()->etapeMemorisee('s1-demonstration'), 'illisible : la première étape');

        $client->request('GET', '/formation/lecon/s1-demonstration?etape=0');
        self::assertSame(1, $this->progression()->etapeMemorisee('s1-demonstration'));
    }

    public function testRouvrirLaMemeEtapeNEcritRienDansLaProgression(): void
    {
        $this->faire('rappel-a', 's1-preparation');
        $client = static::createClient();
        $client->request('GET', '/formation/lecon/s1-demonstration?etape=2');
        // Toute écriture dans etape_demo échouerait désormais : seule une page qui n'écrit rien s'affiche encore.
        $pdo = new \PDO('sqlite:'.$this->progression()->chemin());
        $pdo->exec("CREATE TRIGGER aucune_ecriture BEFORE INSERT ON etape_demo BEGIN SELECT RAISE(ABORT, 'écriture inutile'); END");

        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration?etape=2');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Deuxième étape.', $crawler->filter('.prose')->text());
        $client->request('GET', '/formation/lecon/s1-demonstration');
        self::assertResponseIsSuccessful();
    }

    public function testRevenirSansEtapeRouvreLEtapeMemorisee(): void
    {
        $this->faire('rappel-a', 's1-preparation');
        $client = static::createClient();

        $client->request('GET', '/formation/lecon/s1-demonstration?etape=2');
        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration');

        self::assertStringContainsString('Deuxième étape.', $crawler->filter('.prose')->text());
        self::assertSame(2, $this->progression()->etapeMemorisee('s1-demonstration'));

        // Revenir à l'étape 1 explicitement la mémorise aussi.
        $client->request('GET', '/formation/lecon/s1-demonstration?etape=1');
        $crawler = $client->request('GET', '/formation/lecon/s1-demonstration');
        self::assertStringContainsString('Première étape.', $crawler->filter('.prose')->text());
    }

    public function testLeBoutonDAideApparaitAuQuatriemeRouge(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $client = static::createClient();

        for ($i = 0; $i < 3; ++$i) {
            $this->lancer('s1-exercice');
        }
        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');
        self::assertCount(0, $crawler->selectButton('Demander de l\'aide'));
        self::assertStringNotContainsString(self::PHRASE_AIDE, $crawler->filter('body')->text());

        $this->lancer('s1-exercice');
        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');
        self::assertCount(1, $crawler->selectButton('Demander de l\'aide'));
        self::assertStringContainsString(self::PHRASE_AIDE, $crawler->filter('dialog')->text());
    }

    public function testLExerciceAfficheLEnonceLesTestsAttendusEtLeTerminal(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $this->lancer('s1-exercice');
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        self::assertSelectorTextContains('.prose', 'Fais la page.');
        self::assertSelectorTextContains('.tests-attendus', 'La page répond');
        self::assertSelectorTextContains('.tests-attendus', 'La page affiche la liste');
        self::assertSelectorTextContains('.terminal', 'bin/exercice test');
        self::assertSelectorTextContains('.terminal', '1/2 tests passent. Un test échoue.');
        self::assertCount(1, $crawler->filter('.historique li'));
        self::assertCount(0, $crawler->selectButton('Terminé'), 'aucun bouton Terminé sur un exercice');
    }

    public function testLaPageAfficheLaListeDesTestsRougesDuDernierLancement(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $echecs = array_map(static fn (int $i) => sprintf('testRouge%d : raison %d < >', $i, $i), range(1, 12));
        $this->progression()->enregistrerLancement(FabriqueLancement::creer('s1-exercice', false, null, $echecs[0], $echecs));
        $client = static::createClient();

        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        $terminal = $crawler->filter('.terminal');
        self::assertStringContainsString('1/2 tests passent. Tests à corriger :', $terminal->text());
        $lignes = $terminal->filter('.terminal__rouges li')->each(static fn ($li) => $li->text());
        self::assertCount(11, $lignes, 'dix tests rouges puis la ligne de suite');
        self::assertSame('testRouge1 : raison 1 < >', $lignes[0]);
        self::assertSame('testRouge10 : raison 10 < >', $lignes[9]);
        self::assertSame('… et 2 autres', $lignes[10]);
        self::assertStringContainsString('docker compose exec app php bin/phpunit tests/Formation/fixtures/exercices/Vert', $terminal->text());
        self::assertStringNotContainsString('testRouge11', $terminal->text());
    }

    public function testUneRaisonAvecDuHtmlEstAfficheeEnTexteSurLaPage(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $echecs = ['testPiege : <b>x</b> et <script>alert(1)</script>'];
        $this->progression()->enregistrerLancement(FabriqueLancement::creer('s1-exercice', false, null, $echecs[0], $echecs));
        $client = static::createClient();

        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        self::assertCount(1, $crawler->filter('.terminal__rouges li'));
        self::assertCount(0, $crawler->filter('.terminal__rouges b'), 'aucun élément b : la raison est du texte');
        self::assertCount(0, $crawler->filter('.terminal script'));
        self::assertSame('testPiege : <b>x</b> et <script>alert(1)</script>', $crawler->filter('.terminal__rouges li')->text());
    }

    public function testExactementDixRougesSontListesSurLaPageSansLigneDeSuite(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $echecs = array_map(static fn (int $i) => 'testRouge'.$i.' : raison', range(1, 10));
        $this->progression()->enregistrerLancement(FabriqueLancement::creer('s1-exercice', false, null, $echecs[0], $echecs));
        $client = static::createClient();

        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        self::assertCount(10, $crawler->filter('.terminal__rouges li'));
        self::assertStringNotContainsString('… et', $crawler->filter('.terminal')->text());
    }

    public function testUnSeulAutreEstAccordeAuSingulierSurLaPage(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $echecs = array_map(static fn (int $i) => 'testRouge'.$i.' : raison', range(1, 11));
        $this->progression()->enregistrerLancement(FabriqueLancement::creer('s1-exercice', false, null, $echecs[0], $echecs));
        $client = static::createClient();

        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        $lignes = $crawler->filter('.terminal__rouges li')->each(static fn ($li) => $li->text());
        self::assertCount(11, $lignes);
        self::assertSame('… et 1 autre', $lignes[10]);
    }

    public function testSansListeDeTestsRougesLaPageGardeLeResume(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $this->lancer('s1-exercice');
        $client = static::createClient();

        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        self::assertSelectorTextContains('.terminal', '1/2 tests passent. Un test échoue.');
        self::assertCount(0, $crawler->filter('.terminal__rouges'));
    }

    public function testUnExerciceValideAnnonceLeBadge(): void
    {
        $this->faire('rappel-a', 's1-preparation', 's1-demonstration');
        $this->lancer('s1-exercice', true);
        $this->faire('s1-exercice');
        $client = static::createClient();
        $crawler = $client->request('GET', '/formation/lecon/s1-exercice');

        self::assertStringContainsString('Exercice validé — Badge débloqué : Premier badge', $crawler->filter('body')->text());
    }

    /** Jeton « terminer » lu sur la page d'un bloc qui expose le bouton Terminé. */
    private function jetonTerminer(KernelBrowser $client, string $blocId = 'rappel-a'): string
    {
        $crawler = $client->request('GET', 's1-demonstration' === $blocId ? '/formation/lecon/s1-demonstration?etape=2' : '/formation/lecon/'.$blocId);

        return $crawler->filter('form[action$="/terminer"] input[name="_token"]')->attr('value');
    }

    /** Jeton « fiche » lu sur la page de la préparation du premier sujet (rappel-a doit être fait). */
    private function jetonFiche(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/formation/lecon/s1-preparation');

        return $crawler->filter('form[action$="/fiche"] input[name="_token"]')->attr('value');
    }
}
