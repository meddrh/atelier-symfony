<?php

namespace App\Tests\Formation\Contenu;

use App\Formation\Contenu\ChargeurCatalogue;
use App\Formation\Contenu\ContenuInvalide;
use App\Formation\Contenu\TypeBloc;
use App\Tests\Formation\AvecContenuModifiable;
use PHPUnit\Framework\TestCase;

class ChargeurCatalogueTest extends TestCase
{
    use AvecContenuModifiable;

    private const PROJET = __DIR__.'/../../..';
    private const MINI = __DIR__.'/../fixtures/contenu-mini';

    public function testLesBlocsSontDansLOrdreDuParcours(): void
    {
        $catalogue = (new ChargeurCatalogue(self::MINI, self::PROJET))->charger();

        self::assertSame(
            ['rappel-a', 's1-preparation', 's1-demonstration', 's1-exercice', 's2-preparation', 's2-exercice'],
            array_map(fn ($b) => $b->id, $catalogue->blocsOrdonnes())
        );
    }

    public function testLeCatalogueExposeSujetsNiveauxEtProgramme(): void
    {
        $catalogue = (new ChargeurCatalogue(self::MINI, self::PROJET))->charger();

        self::assertSame('01-s1', $catalogue->sujetDuBloc('s1-exercice')->id);
        self::assertSame(1, $catalogue->sujetDuBloc('s1-exercice')->numero);
        self::assertSame('niveau1', $catalogue->niveauDuBloc('rappel-a')->id);
        self::assertSame(TypeBloc::Exercice, $catalogue->bloc('s1-exercice')->type);
        self::assertSame('tests/Formation/fixtures/exercices/Vert', $catalogue->bloc('s1-exercice')->dossierTests);
        self::assertCount(2, $catalogue->bloc('s1-preparation')->champsFiche);
        self::assertCount(3, $catalogue->programme());
        self::assertStringContainsString('Première étape', $catalogue->contenuDuBloc('s1-demonstration'));
    }

    public function testUnIdentifiantDeBlocEnDoubleEstRefuse(): void
    {
        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['blocs'][0]['id'] = 's1-preparation';

            return $d;
        });

        $this->attendreRefus('Identifiant de bloc en double : « s1-preparation »');
    }

    public function testUnIdentifiantDeBlocHorsDeLaContrainteDeLaRouteEstRefuse(): void
    {
        $cas = [
            'une espace' => 's1 demonstration',
            'une barre oblique' => 's1/demonstration',
            'un point' => 's1.demonstration',
            'une lettre accentuée' => 'démonstration',
            'un saut de ligne final' => "s1-demonstration\n",
        ];
        foreach ($cas as $nom => $id) {
            $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) use ($id) {
                $d['blocs'][1]['id'] = $id;

                return $d;
            });

            $this->attendreRefus(sprintf('Identifiant de bloc « %s » invalide', $id), $nom);
        }
    }

    public function testUnIdentifiantDeBlocAvecTiretEtSoulignementEstAccepte(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][1]['id'] = 'S1_demonstration-2';

            return $d;
        });

        $catalogue = (new ChargeurCatalogue($this->temporaire, self::PROJET))->charger();

        self::assertTrue($catalogue->existe('S1_demonstration-2'));
    }

    public function testUnTypeDeBlocInconnuEstRefuse(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][1]['type'] = 'video';

            return $d;
        });

        $this->attendreRefus('type inconnu « video »');
    }

    public function testUnExerciceSansDossierDeTestsExistantEstRefuse(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][2]['tests'] = 'tests/Exercices/Inexistant';

            return $d;
        });

        $this->attendreRefus('dossier de tests absent « tests/Exercices/Inexistant »');
    }

    public function testUneFicheSansChampEstRefusee(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][0]['champs'] = [];

            return $d;
        });

        $this->attendreRefus('une fiche de préparation doit avoir au moins un champ');
    }

    public function testUneLigneDeProgrammeInconnueEstRefusee(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][1]['programme'] = ['http.inconnue'];

            return $d;
        });

        $this->attendreRefus('ligne de programme inconnue « http.inconnue »');
    }

    public function testUnSujetAvecExerciceSansBadgeEstRefuse(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            unset($d['badge']);

            return $d;
        });

        $this->attendreRefus('le sujet a un exercice mais pas de badge');
    }

    public function testUnFichierDeBlocAbsentEstRefuse(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][1]['fichier'] = 'absent.md';

            return $d;
        });

        $this->attendreRefus('fichier Markdown absent « absent.md »');
    }

    public function testUnSujetAvecBadgeSansExerciceEstRefuse(): void
    {
        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            unset($d['blocs'][1]);

            return $d;
        });

        $this->attendreRefus('le sujet a un badge mais pas d\'exercice');
    }

    public function testUneDureeQuiNEstPasUnEntierPositifEstRefusee(): void
    {
        $cas = ['zéro' => 0, 'négative' => -15, 'texte' => 'quinze', 'décimale' => 7.5];
        foreach ($cas as $nom => $duree) {
            $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) use ($duree) {
                $d['blocs'][1]['duree'] = $duree;

                return $d;
            });

            $this->attendreRefus('bloc « s1-demonstration » : la durée doit être un entier positif de minutes', 'durée '.$nom);
        }
    }

    public function testUnIntercalaireSePlaceAussiApresUnSujet(): void
    {
        $this->modifier('niveau1/niveau.yaml', function (array $d) {
            $d['intercalaires'] = [['apres' => '01-s1', 'blocs' => ['rappel-a']]];

            return $d;
        });

        $catalogue = (new ChargeurCatalogue($this->temporaire, self::PROJET))->charger();

        self::assertSame(
            ['s1-preparation', 's1-demonstration', 's1-exercice', 'rappel-a', 's2-preparation', 's2-exercice'],
            array_map(fn ($b) => $b->id, $catalogue->blocsOrdonnes())
        );
    }

    public function testUnIntercalaireNAPasDeSujetEtLeNiveauListeTousSesBlocs(): void
    {
        $catalogue = (new ChargeurCatalogue(self::MINI, self::PROJET))->charger();

        self::assertNull($catalogue->sujetDuBloc('rappel-a'));
        self::assertTrue($catalogue->existe('rappel-a'));
        self::assertFalse($catalogue->existe('inconnu'));
        self::assertCount(6, $catalogue->niveaux()[0]->blocs);
        self::assertTrue($catalogue->programme()[2]->horsProgramme);
        self::assertSame('hors-programme', $catalogue->programme()[2]->sectionId);
    }

    public function testUnBadgeEnDoubleEstRefuse(): void
    {
        // Deux sujets, puis un sujet et le niveau : l'unicité vaut pour tous les badges.
        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['badge']['id'] = 'badge-s1';

            return $d;
        });
        $this->attendreRefus('Identifiant de badge en double : « badge-s1 »', 'deux sujets');

        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['badge']['id'] = 'niveau-1';

            return $d;
        });
        $this->attendreRefus('Identifiant de badge en double : « niveau-1 »', 'un sujet et le niveau');
    }

    public function testUneLigneDeProgrammeEnDoubleEstRefusee(): void
    {
        $this->modifier('programme.yaml', function (array $d) {
            $d['sections'][1]['lignes'][] = ['id' => 'http.requete', 'libelle' => 'Encore la requête'];

            return $d;
        });

        $this->attendreRefus('Ligne de programme en double : « http.requete »');
    }

    public function testPlusDUnExerciceParSujetEstRefuse(): void
    {
        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['blocs'][] = ['id' => 's2-exercice-bis', 'type' => 'exercice', 'titre' => 'Encore un', 'duree' => 10, 'fichier' => 'exercice.md', 'tests' => 'tests/Formation/fixtures/exercices/Rouge', 'programme' => []];

            return $d;
        });

        $this->attendreRefus('sujet « 02-s2 » : au plus un exercice par sujet (2 trouvés)');
    }

    public function testUnIdentifiantDeNiveauOuDeSujetQuiNeCorrespondPasAuDossierEstRefuse(): void
    {
        $this->modifier('niveau1/niveau.yaml', function (array $d) {
            $d['id'] = 'niveau2';

            return $d;
        });
        $this->attendreRefus('l\'identifiant du fichier niveau.yaml doit être « niveau1 »', 'niveau');

        $this->modifier('niveau1/02-s2/sujet.yaml', function (array $d) {
            $d['id'] = '03-autre';

            return $d;
        });
        $this->attendreRefus('l\'identifiant du fichier sujet.yaml doit être « 02-s2 »', 'sujet');
    }

    public function testUnIntercalaireAvecAvantEtApresOuAucunDesDeuxEstRefuse(): void
    {
        $cas = [
            'avant et apres' => ['avant' => '01-s1', 'apres' => '02-s2', 'blocs' => ['rappel-a']],
            'ni avant ni apres' => ['blocs' => ['rappel-a']],
        ];
        foreach ($cas as $nom => $intercalaire) {
            $this->modifier('niveau1/niveau.yaml', function (array $d) use ($intercalaire) {
                $d['intercalaires'] = [$intercalaire];

                return $d;
            });

            $this->attendreRefus('un intercalaire porte exactement une clé « avant » ou « apres »', $nom);
        }
    }

    public function testUnIntercalaireVersUnSujetInconnuEstRefuse(): void
    {
        foreach (['avant', 'apres'] as $position) {
            $this->modifier('niveau1/niveau.yaml', function (array $d) use ($position) {
                $d['intercalaires'] = [[$position => '99-inconnu', 'blocs' => ['rappel-a']]];

                return $d;
            });

            $this->attendreRefus(sprintf('intercalaire placé %s un sujet inconnu (« 99-inconnu »)', $position), $position);
        }
    }

    public function testUnIntercalaireQuiNEstNiRappelNiLectureEstRefuse(): void
    {
        $this->modifier('rappels/rappel-a.yaml', function (array $d) {
            $d['type'] = 'demonstration';

            return $d;
        });

        $this->attendreRefus('un intercalaire est un rappel ou une lecture, pas « demonstration »');
    }

    public function testUneLectureEstUnIntercalaireValide(): void
    {
        $this->modifier('rappels/rappel-a.yaml', function (array $d) {
            $d['type'] = 'lecture';

            return $d;
        });

        $catalogue = (new ChargeurCatalogue($this->temporaire, self::PROJET))->charger();

        self::assertSame(TypeBloc::Lecture, $catalogue->bloc('rappel-a')->type);
    }

    public function testUnChampDeFicheEnDoubleEstRefuse(): void
    {
        $this->modifier('niveau1/01-s1/sujet.yaml', function (array $d) {
            $d['blocs'][0]['champs'][1]['cle'] = 'routes';

            return $d;
        });

        $this->attendreRefus('champ de fiche en double « routes »');
    }

    public function testUnYamlIllisibleEstRefuseAvecSonFichier(): void
    {
        $this->ecrire('niveau1/niveau.yaml', "id: [niveau1\nnom: : :\n");
        $this->attendreRefus('niveau1/niveau.yaml : YAML illisible', 'niveau.yaml');

        $this->ecrire('niveau1/01-s1/sujet.yaml', "id: 01-s1\nblocs: [\n  - {\n");
        $this->attendreRefus('niveau1/01-s1/sujet.yaml : YAML illisible', 'sujet.yaml');
    }

    public function testUnParcoursOuUnProgrammeVideEstRefuse(): void
    {
        $cas = [
            'parcours.yaml sans contenu' => ['parcours.yaml', '', 'parcours.yaml : le fichier doit contenir une table YAML'],
            'parcours.yaml sans niveau' => ['parcours.yaml', "niveaux: []\n", 'parcours.yaml : la liste « niveaux » est absente ou vide'],
            'programme.yaml sans contenu' => ['programme.yaml', '', 'programme.yaml : le fichier doit contenir une table YAML'],
            'programme.yaml sans section' => ['programme.yaml', "sections: []\n", 'programme.yaml : la liste « sections » est absente ou vide'],
            'programme.yaml, section sans ligne' => ['programme.yaml', "sections:\n  - { id: http, titre: HTTP, lignes: [] }\n", 'la liste « lignes » est absente ou vide'],
        ];
        foreach ($cas as $nom => [$fichier, $contenu, $fragment]) {
            $this->ecrire($fichier, $contenu);

            $this->attendreRefus($fragment, $nom);
        }
    }

    /** Le contenu de $this->temporaire est refusé, et le message porte $fragment (le motif du refus, pas seulement un id). */
    private function attendreRefus(string $fragment, string $cas = ''): void
    {
        try {
            (new ChargeurCatalogue($this->temporaire, self::PROJET))->charger();
            self::fail(trim('Le contenu aurait dû être refusé. '.$cas));
        } catch (ContenuInvalide $e) {
            self::assertStringContainsString($fragment, $e->getMessage(), $cas);
        }
    }
}
