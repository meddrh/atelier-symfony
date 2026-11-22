## Ce que nous construisons

Jusqu'ici, chaque entité vivait seule dans sa table. Le parc informatique a pourtant besoin de dire **qui utilise quel matériel**. Nous allons créer une entité `Utilisateur` et **relier** les matériels aux utilisateurs : un matériel est affecté à **un seul** utilisateur (ou à personne), et un utilisateur peut avoir **plusieurs** matériels.

Cette première partie construit l'entité et la relation, puis la migration. La partie 2 les affiche et les modifie depuis le navigateur.

---

## Étape 1 : l'entité `Utilisateur`

Un utilisateur a un nom et une adresse e-mail **unique**. Crée `src/Entity/Utilisateur.php` :

```php
<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée par un autre utilisateur.')]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 120, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'adresse e-mail est obligatoire.")]
    #[Assert\Email(message: "« {{ value }} » n'est pas une adresse e-mail valide.")]
    #[Assert\Length(max: 180, maxMessage: "L'adresse e-mail ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $email = null;

    /** @var Collection<int, Materiel> */
    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Materiel::class)]
    private Collection $materiels;

    public function __construct()
    {
        $this->materiels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /** @return Collection<int, Materiel> */
    public function getMateriels(): Collection
    {
        return $this->materiels;
    }
}
```

Trois choses nouvelles par rapport à `Fournisseur` :

- `#[UniqueEntity]` vérifie l'unicité **au niveau du validateur** : l'utilisateur voit un message lisible. `unique: true` sur la colonne la garantit **aussi dans la base**, au cas où la règle serait contournée. Les deux ne se remplacent pas.
- la propriété `$materiels` est une **collection** : elle contiendra les matériels de l'utilisateur. Elle est créée dans le constructeur (un `ArrayCollection` vide), sinon Doctrine ne pourrait pas la remplir.
- `#[ORM\OneToMany(mappedBy: 'utilisateur', …)]` dit : « un utilisateur, plusieurs matériels ». Cette propriété ne correspond à **aucune colonne** : elle se lit, elle ne s'écrit pas. C'est le côté **inverse** de la relation.

Crée aussi son dépôt, `src/Repository/UtilisateurRepository.php`, sur le modèle de `MaterielRepository` :

```php
<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }
}
```

---

## Étape 2 : le côté « propriétaire » dans `Materiel`

Une relation a toujours **deux côtés**, mais un seul s'écrit dans la base : celui qui porte la **clé étrangère**. Ici, c'est le matériel : la table `materiel` aura une colonne `utilisateur_id`. On l'appelle le côté **propriétaire**. Dans `src/Entity/Materiel.php`, ajoute cette propriété sous `$categorie` (aucune ligne `use` à ajouter : `Utilisateur` est dans le même espace de noms) :

```php
#[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'materiels')]
private ?Utilisateur $utilisateur = null;
```

- `#[ORM\ManyToOne]` : « plusieurs matériels pour un utilisateur », vu depuis le matériel. `inversedBy: 'materiels'` désigne la propriété du côté inverse.
- Pas de `nullable: false` : un matériel peut n'être affecté à personne. La colonne acceptera donc `NULL`.

Ajoute les deux méthodes d'accès à la fin de la classe :

```php
public function getUtilisateur(): ?Utilisateur
{
    return $this->utilisateur;
}
```

```php
public function setUtilisateur(?Utilisateur $utilisateur): self
{
    $this->utilisateur = $utilisateur;

    return $this;
}
```

Une règle à retenir : **quand tu affectes un matériel, appelle `setUtilisateur()` sur le matériel**. C'est lui qui est enregistré. Modifier seulement la collection `$materiels` de l'utilisateur n'écrit rien dans la base.

---

## Étape 3 : la migration

Un nouveau tableau, une nouvelle colonne : il faut une migration. Avec SQLite, la génération automatique atteint une limite : `make:migration` échoue (« Sqlite platform does not support alter foreign key ») quand une table existante reçoit une clé étrangère vers une **nouvelle** table. Nous écrivons donc la migration en partant du SQL que Doctrine calcule lui-même :

```bash
docker compose exec app php bin/console doctrine:schema:update --dump-sql --complete
docker compose exec app php bin/console doctrine:migrations:generate
```

La première commande **affiche** le SQL nécessaire sans rien exécuter. La seconde crée un fichier de migration vide dans `migrations/` : recopie le SQL dans `up()`, une requête par `addSql()`, et écris `down()` pour défaire. Voici la migration terminée :

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les utilisateurs, et l'affectation d'un matériel à un utilisateur.
 *
 * Écrite à la main : sous SQLite, « make:migration » ne sait pas calculer le retour en arrière
 * (down) quand une table existante reçoit une clé étrangère vers une nouvelle table.
 * Le SQL de up() vient de « bin/console doctrine:schema:update --dump-sql --complete ».
 */
final class Version20261121160627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Utilisateurs et affectation du matériel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE utilisateur (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__materiel AS SELECT id, nom, categorie FROM materiel');
        $this->addSql('DROP TABLE materiel');
        $this->addSql('CREATE TABLE materiel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, utilisateur_id INTEGER DEFAULT NULL, nom VARCHAR(120) NOT NULL, categorie VARCHAR(60) NOT NULL, CONSTRAINT FK_18D2B091FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO materiel (id, nom, categorie) SELECT id, nom, categorie FROM __temp__materiel');
        $this->addSql('DROP TABLE __temp__materiel');
        $this->addSql('CREATE INDEX IDX_18D2B091FB88E14F ON materiel (utilisateur_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__materiel AS SELECT id, nom, categorie FROM materiel');
        $this->addSql('DROP TABLE materiel');
        $this->addSql('CREATE TABLE materiel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, categorie VARCHAR(60) NOT NULL)');
        $this->addSql('INSERT INTO materiel (id, nom, categorie) SELECT id, nom, categorie FROM __temp__materiel');
        $this->addSql('DROP TABLE __temp__materiel');
        $this->addSql('DROP TABLE utilisateur');
    }
}
```

Pourquoi SQLite recopie-t-il la table `materiel` (`__temp__materiel`) ? Parce qu'il ne sait pas ajouter une clé étrangère à une table existante : Doctrine contourne cela en créant une copie, en supprimant l'ancienne, en recréant la table avec la nouvelle colonne puis en y remettant les lignes. Tes matériels ne sont pas perdus.

Exécute-la, puis vérifie que la base et les entités sont d'accord :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console doctrine:schema:update --dump-sql --complete
```

La seconde commande doit répondre qu'il n'y a **rien à mettre à jour**.

---

## Étape 4 : des données d'essai

Ajoute deux utilisateurs, et affecte un matériel à l'un d'eux :

```bash
docker compose exec app php bin/console doctrine:query:sql "INSERT INTO utilisateur (nom, email) VALUES ('Alice Martin', 'alice@exemple.test'), ('Bruno Petit', 'bruno@exemple.test')"
docker compose exec app php bin/console doctrine:query:sql "UPDATE materiel SET utilisateur_id = 1 WHERE id = 2"
docker compose exec app php bin/console doctrine:query:sql "SELECT id, nom, utilisateur_id FROM materiel"
```

La colonne `utilisateur_id` du matériel 2 doit valoir 1. C'est la **clé étrangère** : elle pointe vers la ligne 1 de la table `utilisateur`.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée `Utilisateur` et son dépôt, ajoute la relation dans `Materiel`, écris et exécute la migration, puis ajoute les données d'essai. Vérifie que `doctrine:schema:update --dump-sql --complete` ne propose plus rien, puis clique sur « Terminé ». La partie 2 affiche les matériels affectés et branche le formulaire.
