## Ce que nous construisons

Une commande de la console peut être lancée **deux fois en même temps** : deux terminaux, une tâche planifiée qui démarre pendant que la précédente n'a pas fini, un collègue impatient. Pour une commande qui **lit** seulement, ce n'est pas grave. Pour une commande qui **écrit** quelque chose (un fichier, des lignes en base, un envoi), deux exemplaires simultanés se marchent dessus.

La parade s'appelle un **verrou** : un « je suis dessus » que le premier exemplaire pose, et que le second **voit**. Cette partie en pose un sur une commande qui touche aux matériels, `app:materiel:inventorier` : elle compte les matériels par catégorie et écrit le résultat dans un fichier.

| Élément | Rôle |
|---|---|
| `LockFactory` | la fabrique de verrous : elle est déjà branchée sur le **stockage** choisi par `LOCK_DSN` (un fichier du système en développement et en test) |
| `createLock('nom', durée)` | fabrique l'**objet verrou** pour une ressource **nommée** ; rien n'est encore pris |
| `acquire(false)` | **tente** de prendre le verrou et rend `true` ou `false` **tout de suite** |
| `release()` | rend le verrou |

---

## Étape 1 : la commande, sous verrou

Crée `src/Command/InventorierMaterielCommand.php` :

```php
<?php

namespace App\Command;

use App\Repository\MaterielRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'app:materiel:inventorier',
    description: 'Compte les matériels par catégorie et écrit le résultat dans un fichier (un seul inventaire à la fois).',
)]
class InventorierMaterielCommand extends Command
{
    /** Le nom du verrou : deux lancements qui veulent le même nom se gênent, deux noms différents ne se voient pas. */
    public const VERROU = 'inventaire-materiels';

    /** Durée de vie du verrou, en secondes : un inventaire qui dépasse ce délai perd son verrou. */
    private const DUREE_DU_VERROU = 120;

    public function __construct(
        private MaterielRepository $materielRepository,
        private LockFactory $fabriqueDeVerrous,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fichier', null, InputOption::VALUE_REQUIRED, 'Le fichier où écrire le résultat', 'var/inventaire.txt')
            ->addOption('pause', null, InputOption::VALUE_REQUIRED, 'Secondes de pause avant d\'écrire, pour simuler un traitement long', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // createLock() ne prend rien : il fabrique seulement l'objet verrou, avec son nom et sa durée de vie.
        // Le 3e argument (false) coupe la libération automatique : par défaut, l'objet rendrait le verrou tout seul en
        // disparaissant. Ici, on veut que ce soit NOTRE « finally » qui le rende, et que le test puisse le prouver.
        $verrou = $this->fabriqueDeVerrous->createLock(self::VERROU, self::DUREE_DU_VERROU, false);

        // acquire(false) : « prends le verrou s'il est libre, et rends false TOUT DE SUITE sinon ». C'est le réglage par défaut ;
        // acquire(true) attendrait, sans limite, que l'autre lancement ait fini.
        if (!$verrou->acquire(false)) {
            $io->error('Un inventaire est déjà en cours : ce lancement est refusé. Attends la fin du premier, puis relance la commande.');

            return Command::FAILURE;
        }

        try {
            $parCategorie = [];
            foreach ($this->materielRepository->findAll() as $materiel) {
                $categorie = $materiel->getCategorie() ?? 'Sans catégorie';
                $parCategorie[$categorie] = ($parCategorie[$categorie] ?? 0) + 1;
            }
            ksort($parCategorie);

            // Un traitement long, pour avoir le temps de lancer la commande une seconde fois pendant qu'elle tourne.
            sleep(max(0, (int) $input->getOption('pause')));

            $texte = '';
            foreach ($parCategorie as $categorie => $nombre) {
                $texte .= sprintf("%s : %d\n", $categorie, $nombre);
            }
            // Lève une exception si le fichier ne peut pas être écrit : le verrou doit être rendu malgré elle.
            (new Filesystem())->dumpFile((string) $input->getOption('fichier'), $texte);

            $io->success(sprintf('Inventaire écrit : %d catégorie(s), %d matériel(s).', count($parCategorie), array_sum($parCategorie)));

            return Command::SUCCESS;
        } finally {
            // Rendu quoi qu'il arrive : sans cela, une erreur laisserait le verrou pris jusqu'à la fin du processus.
            $verrou->release();
        }
    }
}
```

Lis-la dans l'ordre :

- **le nom** (`inventaire-materiels`) est ce qui relie deux lancements : deux verrous créés avec le **même nom** se gênent, avec deux noms **différents** ils s'ignorent. Une constante évite de le recopier à plusieurs endroits ;
- **`acquire(false)` ne bloque pas.** Il rend `false` aussitôt si quelqu'un tient le verrou : la commande **refuse** de tourner, le dit en une phrase, et sort avec `Command::FAILURE` (un code **non nul**, pour qu'un script qui l'appelle sache que rien n'a été fait). Avec `acquire(true)`, elle **attendrait** sans limite que l'autre ait fini : deux lancements en file au lieu d'un refus, ce qui n'est presque jamais ce qu'on veut pour une tâche planifiée ;
- **tout le travail** est dans un `try`, et `release()` est dans le `finally` qui le suit. Un `finally` s'exécute **quoi qu'il arrive** : après le `return` du succès, et aussi quand une **exception** sort du `try` (ici : le fichier qu'on ne peut pas écrire). Un `release()` placé simplement à la fin ne serait jamais atteint en cas d'exception ;
- **la durée de vie** (`120`, en secondes) est la limite du verrou : passé ce délai, les autres stockages (une base de données, Redis) le considèrent comme **abandonné** et le rendent. C'est la sécurité pour le cas où le processus **meurt sans passer par `finally`** (machine éteinte, processus tué). Choisis-la **un peu plus longue** que le travail le plus long attendu : trop courte, un second exemplaire pourrait entrer alors que le premier travaille encore. Avec le stockage `flock` de notre `LOCK_DSN`, c'est le **système** qui rend le verrou quand le processus s'arrête : la durée n'y change rien, mais elle compte dès qu'on change de stockage ;
- le **troisième argument** de `createLock()` (`false`) supprime la libération **automatique** : par défaut, l'objet verrou se rend tout seul quand il disparaît de la mémoire, ce qui masquerait un `release()` oublié. Ici, on veut que le `finally` soit **le seul** à rendre le verrou, et pouvoir le prouver dans un test ;
- `--pause` n'a qu'un but : ralentir la commande pour avoir le temps de la lancer une seconde fois.

Aucune configuration à écrire : `autoconfigure` enregistre la commande (grâce à `#[AsCommand]`), et `LockFactory` s'injecte tout seul.

---

## Étape 2 : prouver que le second lancement est refusé

Crée `tests/Junior/InventorierMaterielCommandTest.php` :

```php
<?php

namespace App\Tests\Junior;

use App\Command\InventorierMaterielCommand;
use App\Entity\Materiel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * La commande app:materiel:inventorier, lancée comme le fait la console, dans le noyau de test. Le verrou utilise le
 * stockage de .env.test (LOCK_DSN) : le test prend lui-même le verrou, sur le même nom et le même stockage, pour jouer
 * le rôle du « premier lancement » encore en cours.
 *
 * Les deux tests de la libération utilisent à la place un stockage EN MÉMOIRE, qu'ils gardent en main : avec un fichier
 * du système (flock), le verrou se libérerait de toute façon dès que l'objet verrou disparaît, et ces tests ne pourraient
 * pas voir si c'est bien le « finally » de la commande qui l'a rendu.
 */
class InventorierMaterielCommandTest extends KernelTestCase
{
    use AvecBaseVierge;

    // Le nom écrit en toutes lettres, exprès : si la commande change de nom de verrou, ce test le voit.
    private const VERROU = 'inventaire-materiels';

    private string $fichier;

    protected function setUp(): void
    {
        static::bootKernel();
        $entityManager = $this->creerUneBaseVierge();
        foreach (['Imprimante' => 'Informatique', 'Scanner' => 'Informatique', 'Perceuse' => 'Outillage'] as $nom => $categorie) {
            $entityManager->persist((new Materiel())->setNom($nom)->setCategorie($categorie));
        }
        $entityManager->flush();

        $this->fichier = sys_get_temp_dir().'/inventaire-'.uniqid().'.txt';
    }

    protected function tearDown(): void
    {
        @unlink($this->fichier);
        parent::tearDown();
    }

    public function testUnInventaireEcritLeFichier(): void
    {
        $commande = $this->lancer($this->fichier);

        self::assertSame(0, $commande->getStatusCode(), $this->texte($commande));
        self::assertSame("Informatique : 2\nOutillage : 1\n", file_get_contents($this->fichier));
    }

    public function testUnSecondLancementSimultaneEstRefuse(): void
    {
        $verrou = $this->fabrique()->createLock(self::VERROU);
        self::assertTrue($verrou->acquire(), 'Le test prend lui-même le verrou : un autre inventaire est « en cours ».');

        try {
            $commande = $this->lancer($this->fichier);
        } finally {
            $verrou->release();
        }

        self::assertNotSame(0, $commande->getStatusCode(), 'Un code de sortie non nul : un script appelant sait que rien n\'a été fait.');
        self::assertStringContainsString('déjà en cours', $this->texte($commande));
        self::assertFileDoesNotExist($this->fichier, 'Le lancement refusé n\'a rien écrit.');
    }

    public function testUnVerrouDUnAutreNomNeBloquePas(): void
    {
        $autre = $this->fabrique()->createLock('un-autre-travail');
        self::assertTrue($autre->acquire());

        try {
            $commande = $this->lancer($this->fichier);
        } finally {
            $autre->release();
        }

        self::assertSame(0, $commande->getStatusCode(), 'Deux noms différents ne se voient pas.');
    }

    public function testLeVerrouEstRenduApresUnSucces(): void
    {
        $stockage = new InMemoryStore();

        $this->lancerAvec(new LockFactory($stockage), $this->fichier);

        self::assertTrue($this->leVerrouEstLibre($stockage), 'Un second inventaire peut maintenant démarrer.');
    }

    public function testLeVerrouEstRenduMemeQuandLaCommandeLeveUneException(): void
    {
        $stockage = new InMemoryStore();
        // Un fichier ne peut pas contenir de sous-dossier : l'écriture échoue, avec une exception.
        file_put_contents($this->fichier, '');

        try {
            $this->lancerAvec(new LockFactory($stockage), $this->fichier.'/inventaire.txt');
            self::fail('L\'écriture aurait dû échouer.');
        } catch (IOException) {
            // Attendu : c'est le « finally » de la commande qui est mis à l'épreuve ici.
        }

        self::assertTrue($this->leVerrouEstLibre($stockage), 'Le verrou est rendu même quand une exception sort de la commande.');
    }

    private function lancer(string $fichier): CommandTester
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $testeur = new CommandTester($application->find('app:materiel:inventorier'));
        // Pas d'attrapeur d'exceptions : une exception qui sortirait de la commande remonterait jusqu'au test.
        $testeur->execute(['--fichier' => $fichier], ['decorated' => false]);

        return $testeur;
    }

    /** Comme lancer(), mais avec une fabrique de verrous choisie par le test, donc un stockage que le test garde en main. */
    private function lancerAvec(LockFactory $fabrique, string $fichier): CommandTester
    {
        $depot = static::getContainer()->get('doctrine')->getRepository(Materiel::class);
        $testeur = new CommandTester(new InventorierMaterielCommand($depot, $fabrique));
        $testeur->execute(['--fichier' => $fichier], ['decorated' => false]);

        return $testeur;
    }

    /** La sortie sur une seule ligne, sans les marques de blocs de SymfonyStyle : les phrases s'y lisent d'un trait. */
    private function texte(CommandTester $commande): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['!', '[ERROR]', '[OK]'], ' ', $commande->getDisplay())));
    }

    private function fabrique(): LockFactory
    {
        return static::getContainer()->get(LockFactory::class);
    }

    /** Le verrou est libre si on peut le prendre, sans attendre, dans ce stockage. */
    private function leVerrouEstLibre(InMemoryStore $stockage): bool
    {
        $verrou = (new LockFactory($stockage))->createLock(self::VERROU);
        $libre = $verrou->acquire(false);
        $verrou->release();

        return $libre;
    }
}
```

- le test du **refus** prend **lui-même** le verrou (même nom, même stockage), comme le ferait un premier lancement encore en cours, puis lance la commande : il attend un code non nul, la phrase « déjà en cours », et **aucun fichier écrit**. Le nom est écrit **en toutes lettres** dans le test : si quelqu'un change le nom du verrou dans la commande, le test devient rouge au lieu de suivre le changement sans rien dire ;
- le test « un autre nom ne bloque pas » montre que le verrou est attaché à un **nom**, pas à la commande ;
- les deux tests de la **libération** construisent la commande à la main avec un stockage **en mémoire** (`InMemoryStore`) qu'ils gardent en main, pour vérifier ensuite que le verrou est de nouveau **libre**. Avec le fichier du système, ils passeraient même si le `finally` était supprimé : le verrou y disparaît avec l'objet. **Un test qui ne peut pas échouer ne prouve rien** : retire le `release()` de la commande, relance, et tu verras ces deux tests devenir rouges (remets-le ensuite) ;
- l'échec est fabriqué avec un chemin impossible : un fichier ne peut pas contenir de sous-dossier, l'écriture lève une exception, et le test vérifie que le verrou est rendu **malgré elle**.

Lance-les :

```bash
docker compose exec app php bin/phpunit tests/Junior/InventorierMaterielCommandTest.php
```

---

## Étape 3 : le voir avec deux terminaux

Ouvre **deux** terminaux dans le dossier du projet.

1. Dans le premier, lance une commande lente :

```bash
docker compose exec app php bin/console app:materiel:inventorier --pause=20
```

2. **Pendant ces vingt secondes**, dans le second :

```bash
docker compose exec app php bin/console app:materiel:inventorier; echo "code de sortie : $?"
```

Le second lancement affiche « Un inventaire est déjà en cours », puis `code de sortie : 1`. Le premier, lui, finit normalement : « Inventaire écrit ».

3. Relance la commande **après** la fin du premier : elle passe. Le verrou a été rendu.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée la commande et son test, lance le test, puis fais l'essai à deux terminaux. Relance enfin `php bin/phpunit tests/Junior`, puis clique sur « Terminé ».
