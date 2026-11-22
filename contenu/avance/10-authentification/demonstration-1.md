## Ce que nous construisons

Jusqu'ici, ton application est **ouverte** : n'importe qui peut lister, modifier et supprimer. À partir de ce sujet, elle sait **qui** est en face d'elle. Cette première partie prépare le terrain :

- `Utilisateur` devient un **utilisateur de sécurité** : Symfony saura le reconnaître, lui demander son mot de passe et lire ses **rôles** ;
- le mot de passe est enregistré **haché**, jamais en clair ;
- une commande de la console définit ou change le mot de passe d'un utilisateur.

La partie 2 branche ensuite le formulaire de connexion et ferme les pages des matériels.

Deux mots à bien distinguer, parce que toute la sécurité repose dessus :

- l'**authentification** répond à « **qui es-tu ?** » (un e-mail et un mot de passe, par exemple) ;
- le **contrôle d'accès** répond à « **as-tu le droit ?** » (un rôle suffisant pour cette page).

---

## Étape 1 : un utilisateur que Symfony comprend

Pour que le composant de sécurité manipule ton `Utilisateur`, la classe doit respecter deux **contrats** (des interfaces) :

- `UserInterface` : un identifiant, des rôles, de quoi effacer les données sensibles ;
- `PasswordAuthenticatedUserInterface` : un mot de passe **haché**, que Symfony compare à celui saisi.

Dans `src/Entity/Utilisateur.php`, ajoute ces deux `use` avec les autres :

```php
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
```

Déclare les deux contrats sur la classe :

```php
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
```

Ajoute ces deux propriétés, au-dessus de `$materiels` :

```php
    /** @var list<string> */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $roles = [];

    // Mot de passe HACHÉ (jamais le texte saisi). Sans mot de passe, on ne peut pas se connecter.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;
```

Puis ajoute ces méthodes à la fin de la classe :

```php
    /** L'identifiant de connexion est l'adresse e-mail. */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Exigée par UserInterface en 5.4, abandonnée en 6.0 : même valeur que getUserIdentifier().
     *
     * @deprecated depuis Symfony 5.3, utiliser getUserIdentifier()
     */
    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    /** Exigée par UserInterface en 5.4, abandonnée en 6.0 : les algorithmes modernes salent eux-mêmes le hachage. */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * Les rôles enregistrés, plus ROLE_USER que tout utilisateur possède.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /** Rien de sensible n'est gardé en clair sur l'objet : rien à effacer. */
    public function eraseCredentials(): void
    {
    }
```

Lis-les une à une :

- `getUserIdentifier()` : l'identifiant de connexion, ici l'**adresse e-mail**. C'est aussi la propriété que le fournisseur d'utilisateurs de la partie 2 cherchera en base ;
- `getRoles()` : les rôles enregistrés, **plus** `ROLE_USER`, que tout utilisateur connecté possède. `array_unique()` évite le doublon si `ROLE_USER` est déjà enregistré ;
- `getPassword()` : **l'empreinte** du mot de passe, jamais le texte saisi. La colonne accepte `null` : un utilisateur sans mot de passe existe, mais **ne peut pas se connecter** (c'est le cas de tes utilisateurs actuels) ;
- `eraseCredentials()` : Symfony l'appelle après la connexion pour effacer les données sensibles gardées en clair sur l'objet. Ici, il n'y en a pas : le corps est vide ;
- `getUsername()` et `getSalt()` : en Symfony 5.4, l'interface les exige encore, alors qu'elles sont abandonnées en 6.0. On les écrit pour que la classe soit valide, en une ligne chacune.

---

## Étape 2 : la migration

Deux nouvelles colonnes : une migration. Comme au sujet 6, `ALTER TABLE … ADD COLUMN` perdrait le commentaire `(DC2Type:json)` de la colonne des rôles. On **recrée** donc la table dans la migration, et on la corrige **avant** de l'exécuter (une migration déjà exécutée n'est jamais rejouée).

1. Génère la migration, **sans l'exécuter** :

```bash
docker compose exec app php bin/console make:migration
```

2. Ouvre le fichier créé dans `migrations/` (son nom contient la date et l'heure de ta génération) et **remplace tout son contenu** par la migration qui suit. Garde le nom de classe de **ton** fichier : `Version…` doit porter le même nom que le fichier.

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rôles et mot de passe haché d'un utilisateur.
 *
 * La colonne des rôles porte un commentaire « (DC2Type:json) » : « ALTER TABLE … ADD COLUMN » le perdrait, et le schéma ne
 * serait plus reconnu comme à jour. On recrée donc la table, comme pour les migrations précédentes. Les utilisateurs
 * existants sont recopiés : ils n'ont ni rôle, ni mot de passe (ils ne peuvent pas se connecter).
 */
final class Version20261122015603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Rôles et mot de passe haché d'un utilisateur";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__utilisateur AS SELECT id, nom, email FROM utilisateur');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql("CREATE TABLE utilisateur (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB DEFAULT '[]' NOT NULL --(DC2Type:json)\n, password VARCHAR(255) DEFAULT NULL)");
        $this->addSql('INSERT INTO utilisateur (id, nom, email) SELECT id, nom, email FROM __temp__utilisateur');
        $this->addSql('DROP TABLE __temp__utilisateur');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__utilisateur AS SELECT id, nom, email FROM utilisateur');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('CREATE TABLE utilisateur (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL)');
        $this->addSql('INSERT INTO utilisateur (id, nom, email) SELECT id, nom, email FROM __temp__utilisateur');
        $this->addSql('DROP TABLE __temp__utilisateur');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)');
    }
}
```

Les utilisateurs existants sont **recopiés** : ils gardent leur nom et leur e-mail, sans rôle enregistré et sans mot de passe.

3. Exécute-la, puis vérifie que la base et les entités sont d'accord :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console doctrine:schema:validate
```

La seconde commande doit répondre que le schéma est **synchronisé**.

---

## Étape 3 : ne jamais stocker un mot de passe

Si la base fuit, un mot de passe en clair est perdu pour toujours : les gens réutilisent les leurs. On enregistre donc une **empreinte** : le résultat d'un calcul à sens unique, lent exprès, qu'on ne peut pas inverser. À la connexion, Symfony calcule l'empreinte du mot de passe saisi et la **compare** à celle de la base.

Le calcul vient du service `UserPasswordHasherInterface`. L'algorithme est choisi par la configuration (`auto` : le meilleur disponible), et il **change tout seul** avec le temps : l'empreinte contient elle-même l'algorithme et son sel, donc les anciennes empreintes restent valides.

Crée `src/Command/DefinirMotDePasseCommand.php` :

```php
<?php

namespace App\Command;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:utilisateur:mot-de-passe',
    description: "Définit le mot de passe d'un utilisateur, enregistré haché.",
)]
class DefinirMotDePasseCommand extends Command
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hacheur,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "Adresse e-mail de l'utilisateur")
            ->addArgument('mot-de-passe', InputArgument::OPTIONAL, 'Nouveau mot de passe (demandé sans écho si absent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $utilisateur = $this->utilisateurRepository->findOneBy(['email' => $email]);
        if (null === $utilisateur) {
            $io->error(sprintf('Aucun utilisateur avec l\'adresse e-mail « %s ».', $email));

            return Command::FAILURE;
        }

        $motDePasse = $input->getArgument('mot-de-passe');
        if (null === $motDePasse) {
            // La saisie interactive évite de laisser le mot de passe dans l'historique du terminal.
            $motDePasse = $io->askHidden('Nouveau mot de passe');
        }
        if (null === $motDePasse || '' === $motDePasse) {
            $io->error('Le mot de passe ne peut pas être vide.');

            return Command::FAILURE;
        }

        $utilisateur->setPassword($this->hacheur->hashPassword($utilisateur, $motDePasse));
        $this->entityManager->flush();

        $io->success(sprintf('Le mot de passe de %s est enregistré (haché).', $email));

        return Command::SUCCESS;
    }
}
```

Lis `execute()` dans l'ordre :

- l'utilisateur est cherché par son e-mail ; absent, la commande **échoue** avec un message (`Command::FAILURE`) ;
- si le mot de passe n'est pas donné en argument, `askHidden()` le demande **sans l'afficher** : c'est la bonne façon de procéder, parce qu'un argument reste dans l'historique du terminal ;
- un mot de passe vide est refusé ;
- `hashPassword()` calcule l'empreinte, qu'on enregistre avec `setPassword()` ;
- le message de succès **ne répète pas** le mot de passe.

---

## Étape 4 : essayer

Définis un mot de passe pour Alice, puis regarde ce qui est enregistré :

```bash
docker compose exec app php bin/console app:utilisateur:mot-de-passe alice@exemple.test
docker compose exec app php bin/console doctrine:query:sql "SELECT email, password FROM utilisateur"
```

Entre par exemple `secret-12345` quand la commande le demande. La colonne `password` d'Alice contient une longue chaîne qui commence par `$` : ce n'est **pas** le mot de passe. Relance la commande avec le **même** mot de passe : l'empreinte est **différente**, parce que le sel change à chaque calcul. Une adresse inconnue doit donner un message d'erreur.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Fais de `Utilisateur` un utilisateur de sécurité, écris et exécute la migration, crée la commande et définis le mot de passe d'Alice. Vérifie que `doctrine:schema:validate` est satisfait, puis clique sur « Terminé ». La partie 2 branche le formulaire de connexion.
