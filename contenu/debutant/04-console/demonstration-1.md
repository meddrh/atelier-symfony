## Ce que nous construisons

Nous allons écrire la commande **`app:materiel:importer`**, qui lit un fichier CSV et ajoute des matériels en base. Cette première partie pose la commande et gère le cas du **fichier absent** ; la lecture des lignes vient en partie 2.

Tu connais déjà un programme de ce genre : tu lances `php bin/console make:migration` ou `php bin/console doctrine:migrations:migrate` depuis le début du parcours. Ce sont des **commandes**. Voyons comment écrire les nôtres.

```bash
docker compose exec app php bin/console list
```

La liste de toutes les commandes disponibles s'affiche. À la fin de la démonstration, la tienne y figurera.

---

## Étape 1 : un fichier à importer

Crée à la racine du projet un fichier `materiels.csv`. La première ligne contient les **en-têtes** ; les colonnes sont séparées par un **point-virgule** (c'est l'usage en France, parce que la virgule sert de séparateur décimal).

```text
nom;categorie
Ordinateur portable;Informatique
;Informatique
Écran 24 pouces;Informatique
Chaise de bureau;Mobilier
```

La ligne 3 a un nom vide : elle est **volontairement fausse**. Les lignes sont numérotées comme dans un tableur : l'en-tête est la ligne 1, « Ordinateur portable » la ligne 2, et ainsi de suite.

---

## Étape 2 : déclarer la commande

Une commande est une classe qui hérite de `Command`, rangée par convention dans `src/Command/`. Crée `src/Command/ImporterMaterielCommand.php` :

```php
<?php

namespace App\Command;

use App\Entity\Materiel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:materiel:importer',
    description: 'Importe des matériels depuis un fichier CSV (colonnes : nom;categorie).',
)]
class ImporterMaterielCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validateur,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier CSV à importer')
            ->addOption('simuler', null, InputOption::VALUE_NONE, 'Vérifie le fichier sans rien enregistrer')
        ;
    }
```

(La suite de la classe vient à l'étape suivante : ne ferme pas encore l'accolade finale.)

Lecture :

- `#[AsCommand(name: …, description: …)]` : l'**attribut** qui déclare la commande. Le nom suit l'usage `espace:sujet:action`. C'est tout : Symfony découvre la classe tout seul, il n'y a rien à enregistrer.
- Le **constructeur** reçoit les deux services dont la commande a besoin, par câblage automatique, exactement comme un contrôleur : l'`EntityManagerInterface` pour enregistrer, le `ValidatorInterface` pour appliquer les règles de l'entité. `parent::__construct()` est obligatoire dans une commande.
- `configure()` décrit ce que la commande accepte sur la ligne de commande :
  - un **argument** `fichier`, obligatoire (`REQUIRED`) : une valeur donnée **à sa place**, sans nom (`app:materiel:importer materiels.csv`) ;
  - une **option** `--simuler`, qui est un simple interrupteur (`VALUE_NONE`) : on la met ou non, elle sert à régler le comportement. Nous l'utilisons en partie 2.
- Les deux appels à `->addArgument()` et `->addOption()` s'enchaînent, comme les `set…` d'une entité : chaque méthode renvoie l'objet.

---

## Étape 3 : le début d'`execute()` et le code de retour

Toute commande a une méthode `execute()`, qui renvoie un **entier** : le **code de retour**. Par convention, **0** veut dire « tout s'est bien passé », et **toute autre valeur** veut dire « échec ». Symfony fournit deux constantes : `Command::SUCCESS` (0) et `Command::FAILURE` (1).

Voici le début de la méthode :

```php
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $chemin = $input->getArgument('fichier');
        $simulation = $input->getOption('simuler');

        $io->title('Import des matériels');

        $fichier = is_file($chemin) && is_readable($chemin) ? fopen($chemin, 'r') : false;
        if (false === $fichier) {
            $io->error(sprintf('Fichier introuvable ou illisible : %s', $chemin));

            return Command::FAILURE;
        }
```

- `InputInterface $input` représente **ce que l'utilisateur a tapé** ; `OutputInterface $output` représente **l'écran**.
- `SymfonyStyle` est une classe qui rend l'écran lisible : `title()` écrit un titre, `error()` un bloc rouge, `warning()` un bloc jaune, `success()` un bloc vert. Elle évite de composer toi-même des couleurs et des marges.
- `getArgument('fichier')` lit l'argument, `getOption('simuler')` lit l'option (`true` si elle est présente).
- `is_file()` et `is_readable()` vérifient que le fichier existe et qu'on peut le lire ; `fopen()` l'ouvre et renvoie `false` en cas d'échec.
- Si le fichier est inutilisable, on **explique le problème** avec `error()` et on **s'arrête tout de suite** avec `Command::FAILURE`. Ne jamais continuer en espérant que ça passe.

Pour essayer, termine provisoirement la méthode par `return Command::SUCCESS; }` puis par l'accolade de la classe, et lance :

```bash
docker compose exec app php bin/console app:materiel:importer fichier-absent.csv
echo $?
```

Tu vois un bloc rouge avec ton message, puis `1` : le code de retour de la dernière commande. Avec `materiels.csv` à la place, tu obtiens `0`.

Vérifie aussi que Symfony connaît ta commande, avec sa description :

```bash
docker compose exec app php bin/console list app
```

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée la classe avec son attribut, son constructeur, `configure()` et le début d'`execute()`. Vérifie que la commande apparaît dans `php bin/console list app` et que l'appel avec un fichier absent affiche l'erreur et donne le code 1. Clique ensuite sur « Terminé » : la partie 2 lit les lignes du fichier.
