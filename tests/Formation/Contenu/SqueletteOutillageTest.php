<?php

namespace App\Tests\Formation\Contenu;

use App\Tests\Formation\Squelette\AnalyseurPhp;
use App\Tests\Formation\Squelette\BlocSquelette;
use App\Tests\Formation\Squelette\ExtracteurDeSquelettes;
use App\Tests\Formation\Squelette\RemplaceurDeMethodes;
use App\Tests\Formation\Squelette\VerificationsSquelette;
use PHPUnit\Framework\TestCase;

/**
 * L'outillage des squelettes (extracteur, analyseur, contrôles) sur du code écrit ici : un squelette juste passe,
 * et chaque erreur typique d'un squelette (mauvais espace de noms, méthode renommée, type changé, …) est vue.
 * Sans ces mutations, un SqueletteTest vert ne prouverait pas que le contrôle sait trouver quelque chose.
 */
class SqueletteOutillageTest extends TestCase
{
    private const CORRIGE = <<<'PHP'
<?php

namespace App\Controller;

use App\Service\Annuaire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/annuaire')]
class AnnuaireController extends AbstractController implements \Countable
{
    private const NOMS = ['a' => 1];

    public function __construct(private Annuaire $annuaire, private int $limite = 10)
    {
    }

    #[Route('/{id}', name: 'annuaire_fiche', requirements: ['id' => '\d+'])]
    public function fiche(int $id, ?string $format = null, string ...$autres): Response
    {
        $f = function () { return "{$id}"; };

        return $this->render('annuaire/fiche.html.twig', ['id' => $id]);
    }

    public function count(): int
    {
        return \count(self::NOMS);
    }

    private function aide(): void
    {
    }
}
PHP;

    private const SQUELETTE = <<<'PHP'
<?php
// src/Controller/AnnuaireController.php

namespace App\Controller;

use App\Service\Annuaire as Carnet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/annuaire')]
class AnnuaireController extends AbstractController implements \Countable
{
    public function __construct(private Carnet $annuaire, private int $limite = 10)
    {
    }

    #[Route('/{id}', name: 'annuaire_fiche')]
    public function fiche(int $id, ?string $format = null, string ...$autres): Response
    {
        // À toi : afficher la fiche.
    }

    public function count(): int
    {
        // À toi : compter.
    }
}
PHP;

    public function testUnSqueletteJusteEstEnRegle(): void
    {
        $bloc = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'src/Controller/AnnuaireController.php', self::SQUELETTE);

        self::assertSame([], VerificationsSquelette::erreursDeCompilation($bloc));
        self::assertSame([], VerificationsSquelette::erreursPsr4($bloc));
        // Un alias de `use` différent (Carnet), une route sans `requirements:` et des corps vides ne sont pas des écarts.
        self::assertSame([], VerificationsSquelette::erreursDeCoherence($bloc, self::CORRIGE));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function mutationsDeCoherence(): iterable
    {
        yield 'méthode renommée' => ['public function fiche(', 'public function afficher(', 'la méthode publique afficher() n\'existe pas'];
        yield 'type de paramètre changé' => ['fiche(int $id', 'fiche(string $id', 'signature de fiche() différente'];
        yield 'paramètre en trop' => ['fiche(int $id,', 'fiche(int $id, int $page, ', 'signature de fiche() différente'];
        yield 'valeur par défaut changée' => ['?string $format = null', '?string $format = \'html\'', 'signature de fiche() différente'];
        yield 'type de retour changé' => ['): Response', '): string', 'signature de fiche() différente'];
        yield 'type de retour oublié' => ['public function count(): int', 'public function count()', 'signature de count() différente'];
        yield 'parent changé' => ['extends AbstractController', 'extends \Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait', 'parent'];
        yield 'parent oublié' => ['extends AbstractController ', '', 'parent'];
        yield 'interface oubliée' => [' implements \Countable', '', 'interfaces'];
        yield 'interface en trop' => ['implements \Countable', 'implements \Countable, \JsonSerializable', 'interfaces'];
        yield 'attribut de classe inconnu' => ["#[Route('/annuaire')]\nclass", "#[Route('/annuaire'), \\Attribute]\nclass", 'attribut de classe'];
        yield 'attribut de méthode inconnu' => ["#[Route('/{id}', name: 'annuaire_fiche')]", "#[Route('/{id}'), \\Deprecated]", 'attribut #[Deprecated] de fiche()'];
        yield 'ancien nom de classe d\'attribut' => ["#[Route('/{id}', name: 'annuaire_fiche')]\n    public function fiche", "#[\\Foo\\Route('/{id}')]\n    public function fiche", 'attribut #[Foo\Route] de fiche()'];
        yield 'type de classe d\'un autre espace' => ['private Carnet $annuaire', 'private \App\Autre\Annuaire $annuaire', 'signature de __construct() différente'];
    }

    /** @dataProvider mutationsDeCoherence */
    public function testUneMutationDuSqueletteEstVueParLaCoherence(string $cherche, string $remplace, string $erreur): void
    {
        $mutant = str_replace($cherche, $remplace, self::SQUELETTE);
        self::assertNotSame(self::SQUELETTE, $mutant, 'la mutation doit changer le squelette');
        $bloc = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'src/Controller/AnnuaireController.php', $mutant);

        $erreurs = implode("\n", VerificationsSquelette::erreursDeCoherence($bloc, self::CORRIGE));

        self::assertStringContainsString($erreur, $erreurs);
    }

    public function testUnMauvaisEspaceDeNomsOuUneClasseRenommeeSontVusParPsr4(): void
    {
        $chemin = 'src/Controller/AnnuaireController.php';

        $espace = new BlocSquelette('01-x', BlocSquelette::COMPLET, $chemin, str_replace('namespace App\Controller;', 'namespace App\Controllers;', self::SQUELETTE));
        $classe = new BlocSquelette('01-x', BlocSquelette::COMPLET, $chemin, str_replace('class AnnuaireController', 'class Annuaire', self::SQUELETTE));
        $dossier = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'src/Service/AnnuaireController.php', self::SQUELETTE);
        $hors = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'lib/AnnuaireController.php', self::SQUELETTE);

        self::assertStringContainsString('espace de noms « App\Controllers » au lieu de « App\Controller »', implode("\n", VerificationsSquelette::erreursPsr4($espace)));
        self::assertStringContainsString('classe « Annuaire » au lieu de « AnnuaireController »', implode("\n", VerificationsSquelette::erreursPsr4($classe)));
        self::assertStringContainsString('au lieu de « App\Service »', implode("\n", VerificationsSquelette::erreursPsr4($dossier)));
        self::assertStringContainsString('ne suit pas PSR-4', implode("\n", VerificationsSquelette::erreursPsr4($hors)));
    }

    public function testUneClasseDeTestsSuitLeNamespaceAppTests(): void
    {
        $code = "<?php\n// tests/Junior/TicketTest.php\n\nnamespace App\\Tests\\Junior;\n\nclass TicketTest\n{\n}\n";
        $juste = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'tests/Junior/TicketTest.php', $code);
        $fausse = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'tests/Junior/TicketTest.php', str_replace('App\\Tests\\Junior', 'App\\Junior', $code));

        self::assertSame([], VerificationsSquelette::erreursPsr4($juste));
        self::assertStringContainsString('au lieu de « App\Tests\Junior »', implode("\n", VerificationsSquelette::erreursPsr4($fausse)));
    }

    public function testUneClasseALaRacineDeSrcEstDansApp(): void
    {
        $bloc = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'src/Kernel.php', "<?php\n// src/Kernel.php\n\nnamespace App;\n\nclass Kernel\n{\n}\n");

        self::assertSame([], VerificationsSquelette::erreursPsr4($bloc));
    }

    public function testUnSqueletteQuiNeCompilePasEstVu(): void
    {
        $bloc = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'src/Controller/Casse.php', "<?php\n// src/Controller/Casse.php\n\nnamespace App\\Controller;\n\nclass Casse\n{\n    public function f(\n}\n");

        $erreurs = implode("\n", VerificationsSquelette::erreursDeCompilation($bloc));

        self::assertStringContainsString('Parse error', $erreurs);
        self::assertStringContainsString('src/Controller/Casse.php', $erreurs, 'le message nomme le chemin du squelette, pas un fichier temporaire');
    }

    public function testUnFichierAbsentDuCorrigeEstVu(): void
    {
        $bloc = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'src/Controller/AnnuaireController.php', self::SQUELETTE);

        self::assertStringContainsString('le corrigé du sujet n\'a pas de fichier', implode('', VerificationsSquelette::erreursDeCoherence($bloc, null)));
    }

    /** Un `Voter` impose `supports()` et `voteOnAttribute()`, qui sont protégées : leur signature doit être exacte aussi. */
    public function testLesMethodesProtegeesImposeesParLeParentSontVerifiees(): void
    {
        $corrige = "<?php\nnamespace App\Security;\n\nuse Symfony\\Component\\Security\\Core\\Authorization\\Voter\\Voter;\n\nclass V extends Voter\n{\n    protected function supports(string \$attribute, mixed \$subject): bool\n    {\n        return true;\n    }\n\n    private function aide(): void\n    {\n    }\n}\n";
        $juste = "<?php\n// src/Security/V.php\n\nnamespace App\\Security;\n\nuse Symfony\\Component\\Security\\Core\\Authorization\\Voter\\Voter;\n\nclass V extends Voter\n{\n    protected function supports(string \$attribute, mixed \$subject): bool\n    {\n        // À toi.\n    }\n}\n";
        $bloc = static fn (string $code): BlocSquelette => new BlocSquelette('11-x', BlocSquelette::COMPLET, 'src/Security/V.php', $code);

        self::assertSame(['supports'], array_keys(AnalyseurPhp::analyser($corrige)->classes[0]->methodes), 'la protégée est lue, la privée non');
        self::assertSame([], VerificationsSquelette::erreursDeCoherence($bloc($juste), $corrige));
        self::assertStringContainsString('signature de supports()', implode("\n", VerificationsSquelette::erreursDeCoherence($bloc(str_replace('mixed $subject', 'string $subject', $juste)), $corrige)));
        self::assertStringContainsString('signature de supports()', implode("\n", VerificationsSquelette::erreursDeCoherence($bloc(str_replace('protected function', 'public function', $juste)), $corrige)), 'la visibilité fait partie de la signature');
        self::assertStringContainsString('signature de supports()', implode("\n", VerificationsSquelette::erreursDeCoherence($bloc(str_replace('bool', 'int', $juste)), $corrige)));
    }

    public function testUnExtraitSeVerifieContreLeCorrigeAvecSesPropresUse(): void
    {
        $juste = "// à ajouter dans src/Controller/AnnuaireController.php\n\n    public function count(): int\n    {\n        // À toi.\n    }\n";
        $bloc = new BlocSquelette('01-x', BlocSquelette::EXTRAIT, 'src/Controller/AnnuaireController.php', $juste);
        self::assertSame([], VerificationsSquelette::erreursDeCoherence($bloc, self::CORRIGE));

        // `Response` et `Route` ne sont pas importés dans l'extrait : ils se résolvent par les `use` du fichier du corrigé.
        $route = "// à ajouter dans src/Controller/AnnuaireController.php\n\n    #[Route('/{id}', name: 'annuaire_fiche')]\n    public function fiche(int \$id, ?string \$format = null, string ...\$autres): Response\n    {\n    }\n";
        self::assertSame([], VerificationsSquelette::erreursDeCoherence(new BlocSquelette('01-x', BlocSquelette::EXTRAIT, 'src/Controller/AnnuaireController.php', $route), self::CORRIGE));

        $renomme = str_replace('count', 'total', $juste);
        $mauvaiseSignature = str_replace('): int', '): string', $juste);
        $vide = "// à ajouter dans src/Controller/AnnuaireController.php\n\n    private \$x;\n";
        self::assertStringContainsString('total() n\'existe pas', implode("\n", VerificationsSquelette::erreursDeCoherence(new BlocSquelette('01-x', BlocSquelette::EXTRAIT, 'src/Controller/AnnuaireController.php', $renomme), self::CORRIGE)));
        self::assertStringContainsString('signature de count()', implode("\n", VerificationsSquelette::erreursDeCoherence(new BlocSquelette('01-x', BlocSquelette::EXTRAIT, 'src/Controller/AnnuaireController.php', $mauvaiseSignature), self::CORRIGE)));
        self::assertStringContainsString('aucune méthode publique', implode("\n", VerificationsSquelette::erreursDeCoherence(new BlocSquelette('01-x', BlocSquelette::EXTRAIT, 'src/Controller/AnnuaireController.php', $vide), self::CORRIGE)));
    }


    private const AVANT = <<<'PHP'
<?php

namespace App\Controller;

use App\Service\Annuaire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnuaireController extends AbstractController
{
    #[Route('/annuaire', name: 'annuaire_liste')]
    public function liste(): Response
    {
        return $this->render('annuaire/liste.html.twig', ['x' => ['a' => 1]]);
    }

    #[Route('/annuaire/{id}', name: 'annuaire_fiche')]
    public function fiche(int $id): Response
    {
        if ($id > 3) {
            throw $this->createNotFoundException();
        }

        return $this->render('annuaire/fiche.html.twig');
    }
}
PHP;

    private const MODIFIEE = "// à modifier dans src/Controller/AnnuaireController.php\n\n    // Reçoit maintenant le dépôt : garde ton code et adapte-le.\n    #[Route('/annuaire', name: 'annuaire_liste')]\n    public function liste(Annuaire \$annuaire): Response\n    {\n        // À toi.\n    }\n";

    private const APRES = <<<'PHP'
<?php

namespace App\Controller;

use App\Service\Annuaire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnuaireController extends AbstractController
{
    #[Route('/annuaire', name: 'annuaire_liste')]
    public function liste(Annuaire $annuaire): Response
    {
        return $this->render('annuaire/liste.html.twig', ['x' => $annuaire->tous()]);
    }

    #[Route('/annuaire/{id}', name: 'annuaire_fiche')]
    public function fiche(int $id): Response
    {
        return $this->render('annuaire/fiche.html.twig');
    }
}
PHP;

    public function testUneModificationJusteEstEnRegle(): void
    {
        $bloc = new BlocSquelette('02-x', BlocSquelette::MODIFICATION, 'src/Controller/AnnuaireController.php', self::MODIFIEE);

        // Même signature que dans le corrigé du sujet ; la méthode existait déjà dans le corrigé du sujet précédent.
        self::assertSame([], VerificationsSquelette::erreursDeCoherence($bloc, self::APRES));
        self::assertSame([], VerificationsSquelette::erreursDeCorps($bloc));
        self::assertSame([], VerificationsSquelette::erreursDExistence($bloc, self::AVANT));
    }

    public function testUneModificationSeVerifieContreLeCorrigeDuSujet(): void
    {
        $mauvaise = str_replace('Annuaire $annuaire', 'string $annuaire', self::MODIFIEE);

        $erreurs = VerificationsSquelette::erreursDeCoherence(new BlocSquelette('02-x', BlocSquelette::MODIFICATION, 'src/Controller/AnnuaireController.php', $mauvaise), self::APRES);

        self::assertStringContainsString('signature de liste()', implode("\n", $erreurs));
    }

    public function testUneMethodeAModifierQuiNExistaitPasEstUnAAjouter(): void
    {
        $nouvelle = str_replace('public function liste(Annuaire $annuaire)', 'public function recherche(Annuaire $annuaire)', self::MODIFIEE);
        $bloc = new BlocSquelette('02-x', BlocSquelette::MODIFICATION, 'src/Controller/AnnuaireController.php', $nouvelle);

        $erreurs = implode("\n", VerificationsSquelette::erreursDExistence($bloc, self::AVANT));
        self::assertStringContainsString('recherche()', $erreurs);
        self::assertStringContainsString('« à ajouter »', $erreurs);
        self::assertStringContainsString('le corrigé du sujet précédent n\'a pas de fichier', implode("\n", VerificationsSquelette::erreursDExistence($bloc, null)));
    }

    public function testUneMethodeAAjouterQuiExistaitDejaEstUnAModifier(): void
    {
        $bloc = new BlocSquelette('02-x', BlocSquelette::EXTRAIT, 'src/Controller/AnnuaireController.php', str_replace('à modifier', 'à ajouter', self::MODIFIEE));

        self::assertStringContainsString('« à modifier »', implode("\n", VerificationsSquelette::erreursDExistence($bloc, self::AVANT)));
        self::assertSame([], VerificationsSquelette::erreursDExistence($bloc, null), 'un fichier absent au départ : rien à comparer pour un ajout (le test lent le dira)');
    }

    public function testUnBlocCompletNeVisePasUnFichierDejaPresentAuDepart(): void
    {
        $bloc = new BlocSquelette('02-x', BlocSquelette::COMPLET, 'src/Controller/AnnuaireController.php', self::SQUELETTE);

        $erreurs = implode("\n", VerificationsSquelette::erreursDExistence($bloc, self::AVANT));

        self::assertStringContainsString('existe déjà', $erreurs);
        self::assertStringContainsString('« à ajouter »', $erreurs);
        self::assertStringContainsString('« à modifier »', $erreurs);
        self::assertSame([], VerificationsSquelette::erreursDExistence($bloc, null), 'un fichier nouveau : en règle');
    }

    public function testLeRemplaceurRemplaceLaMethodeEtLuiSeuleAvecSonAttribut(): void
    {
        $resultat = RemplaceurDeMethodes::remplacerDansFichier(self::AVANT, self::MODIFIEE);

        self::assertStringContainsString("    public function liste(Annuaire \$annuaire): Response\n    {\n        // À toi.\n    }\n", $resultat);
        self::assertStringNotContainsString('annuaire/liste.html.twig', $resultat, 'l\'ancien corps est parti');
        self::assertSame(1, substr_count($resultat, "#[Route('/annuaire', name: 'annuaire_liste')]"), 'l\'attribut de l\'ancienne méthode est remplacé, pas dupliqué');
        self::assertStringContainsString('createNotFoundException', $resultat, 'l\'autre méthode reste intacte');
        self::assertSame([], VerificationsSquelette::erreursDeCompilation(new BlocSquelette('02-x', BlocSquelette::COMPLET, 'src/Controller/AnnuaireController.php', rtrim($resultat))));
    }

    public function testLeRemplaceurRefuseUneMethodeAbsente(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('recherche()');

        RemplaceurDeMethodes::remplacerDansFichier(self::AVANT, str_replace('liste(', 'recherche(', self::MODIFIEE));
    }

    public function testLeRemplaceurRemplacePlusieursMethodesDUnExtrait(): void
    {
        $extrait = "// à modifier dans src/Controller/AnnuaireController.php\n\n    #[Route('/annuaire', name: 'annuaire_liste')]\n    public function liste(Annuaire \$a): Response\n    {\n    }\n\n    #[Route('/annuaire/{id}', name: 'annuaire_fiche')]\n    public function fiche(int \$id, Annuaire \$a): Response\n    {\n    }\n";

        $resultat = RemplaceurDeMethodes::remplacerDansFichier(self::AVANT, $extrait);

        self::assertStringContainsString('liste(Annuaire $a)', $resultat);
        self::assertStringContainsString('fiche(int $id, Annuaire $a)', $resultat);
        self::assertStringNotContainsString('createNotFoundException', $resultat);
        self::assertSame(1, substr_count($resultat, 'function liste('));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function corps(): iterable
    {
        yield 'vide' => ["// À toi : afficher la fiche.", false];
        yield 'return null' => ['return null;', false];
        yield 'return vrai' => ['return true;', false];
        yield 'return 0' => ['return 0;', false];
        yield 'return chaîne vide' => ["return '';", false];
        yield 'return tableau vide' => ['return [];', false];
        yield 'return d\'une constante de classe' => ['return Command::SUCCESS;', false];
        yield 'return d\'une constante en nom complet' => ['return \\Symfony\\Component\\Console\\Command\\Command::SUCCESS;', false];
        yield 'return d\'un appel statique' => ['return Outils::calculer();', true];
        yield 'return d\'une constante calculée' => ['return Command::SUCCESS + $this->n;', true];
        yield 'throw provisoire' => ["throw new \\LogicException('À toi.');", false];
        yield 'return d\'un rendu' => ["return \$this->render('x.html.twig');", true];
        yield 'return d\'une variable' => ['return $reponse;', true];
        yield 'return de données' => ["return ['a' => 1];", true];
        yield 'une instruction' => ['$x = 1;', true];
        yield 'une condition' => ["if (\$id > 3) {\n            return null;\n        }", true];
        yield 'un rendu après un commentaire' => ["// À toi.\n        return \$this->render('x.html.twig');", true];
    }

    /** @dataProvider corps */
    public function testUnCorpsRempliEstVu(string $corps, bool $rempli): void
    {
        $code = "<?php\n// src/Controller/Corps.php\n\nnamespace App\\Controller;\n\nclass Corps\n{\n    public function f(\$id): ?int\n    {\n        ".$corps."\n    }\n}\n";
        $bloc = new BlocSquelette('01-x', BlocSquelette::COMPLET, 'src/Controller/Corps.php', $code);

        self::assertSame($rempli ? 1 : 0, \count(VerificationsSquelette::erreursDeCorps($bloc)));
    }

    public function testUnExtraitAvecUnCorpsRempliEstVu(): void
    {
        $extrait = "// à ajouter dans src/Controller/Corps.php\n\npublic function f(): int\n{\n    return \$this->x;\n}\n";

        self::assertCount(1, VerificationsSquelette::erreursDeCorps(new BlocSquelette('01-x', BlocSquelette::EXTRAIT, 'src/Controller/Corps.php', $extrait)));
    }

    public function testLAnalyseurNeVoitQueLesMethodesPubliquesDeLaClasse(): void
    {
        $classe = AnalyseurPhp::analyser(self::CORRIGE)->classes[0];

        self::assertSame(['__construct', 'fiche', 'count'], array_keys($classe->methodes), 'aide() est privée, la fonction anonyme du corps n\'est pas une méthode');
        self::assertSame('function fiche(int $id, ?string $format = null, string ...$autres): Symfony\Component\HttpFoundation\Response', $classe->methodes['fiche']->signature);
        self::assertSame('function __construct(App\Service\Annuaire $annuaire, int $limite = 10)', $classe->methodes['__construct']->signature, 'promotion de propriété retirée, type résolu');
        self::assertSame(['Symfony\Component\Routing\Annotation\Route'], $classe->methodes['fiche']->attributs);
        self::assertSame('Symfony\Bundle\FrameworkBundle\Controller\AbstractController', $classe->parent);
        self::assertSame(['Countable'], $classe->interfaces);
    }

    public function testLExtracteurLitLesDeuxFormesDeBlocEtSignaleLesAutres(): void
    {
        $markdown = <<<'MD'
## À faire

Consigne.

## Squelette des fichiers

Texte d'introduction.

```php
<?php
// src/Controller/A.php

namespace App\Controller;

class A
{
}
```

```php
// à ajouter dans src/Controller/B.php

public function f(): void
{
}
```

```php
// à modifier dans src/Controller/D.php

public function g(): void
{
}
```

```php
<?php
// à modifier dans src/Controller/E.php

public function h(): void
{
}
```

```php
<?php
// pas un chemin

class C {}
```

```bash
echo pas du php
```

## Tests attendus

```php
<?php
// src/Controller/Hors.php
```
MD;

        $blocs = ExtracteurDeSquelettes::blocsDe($markdown, '01-x');

        self::assertSame(
            [
                [BlocSquelette::COMPLET, 'src/Controller/A.php'],
                [BlocSquelette::EXTRAIT, 'src/Controller/B.php'],
                [BlocSquelette::MODIFICATION, 'src/Controller/D.php'],
                [BlocSquelette::MODIFICATION, 'src/Controller/E.php'],
                [BlocSquelette::INVALIDE, ''],
            ],
            array_map(static fn (BlocSquelette $b): array => [$b->type, $b->chemin], $blocs),
            'cinq blocs php ; le bloc bash et le bloc après « Tests attendus » ne comptent pas',
        );
        self::assertStringContainsString('à modifier dans', (string) $blocs[4]->erreur, 'le message d\'erreur cite aussi le troisième genre');
        self::assertStringContainsString('la deuxième ligne doit être', (string) $blocs[4]->erreur);
    }

    public function testLeLibelleDistingueLesGenresDeBlocsDUnMemeFichier(): void
    {
        $chemin = 'src/Controller/AnnuaireController.php';
        $libelles = array_map(
            static fn (string $type): string => (new BlocSquelette('02-x', $type, $chemin, ''))->libelle(),
            [BlocSquelette::COMPLET, BlocSquelette::EXTRAIT, BlocSquelette::MODIFICATION],
        );

        self::assertCount(3, array_unique($libelles), 'un fournisseur de données PHPUnit refuse deux clés identiques');
        self::assertSame('02-x src/Controller/AnnuaireController.php (à modifier)', $libelles[2]);
    }

    public function testSansSectionAucunBloc(): void
    {
        self::assertSame([], ExtracteurDeSquelettes::blocsDe("## À faire\n\nRien.\n", '01-x'));
        self::assertNull(ExtracteurDeSquelettes::section("```\n## Squelette des fichiers\n```\n"), 'le titre dans un bloc de code n\'est pas une section');
    }

    public function testUneSectionSansBlocPhpEstInvalide(): void
    {
        $blocs = ExtracteurDeSquelettes::blocsDe("## Squelette des fichiers\n\nPlus tard.\n", '01-x');

        self::assertCount(1, $blocs);
        self::assertSame(BlocSquelette::INVALIDE, $blocs[0]->type);
    }
}
