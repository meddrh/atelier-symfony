## Ce que nous construisons

Les matériels sont pour l'instant dans un tableau écrit en dur. Nous allons les mettre dans une **table de base de données**, avec Doctrine. Cette partie prépare le terrain : une entité, son dépôt, une migration, quelques lignes de test. Les pages n'y touchent pas encore.

Doctrine est un outil qui relie des objets PHP et des lignes de table. Il ne fait pas partie du programme de la certification, mais presque tous les projets l'utilisent : il faut le connaître.

---

## Étape 1 : l'entité

Une **entité** est une classe PHP dont chaque objet correspond à **une ligne** d'une table. Ses propriétés correspondent aux colonnes. On le déclare avec des attributs.

Crée `src/Entity/Materiel.php` :

```php
<?php

namespace App\Entity;

use App\Repository\MaterielRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaterielRepository::class)]
class Materiel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $nom = null;

    #[ORM\Column(length: 60)]
    private ?string $categorie = null;

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

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;

        return $this;
    }
}
```

Lecture ligne par ligne :

- `#[ORM\Entity]` : « cette classe est une entité » ; `repositoryClass` indique son dépôt (étape suivante).
- `#[ORM\Id]` et `#[ORM\GeneratedValue]` : `id` est la clé primaire, numérotée automatiquement par la base. On ne le remplit jamais soi-même, c'est pourquoi il n'a pas de `setId()`.
- `#[ORM\Column(length: 120)]` : une colonne de texte de 120 caractères au plus. Sans autre précision, une colonne est **obligatoire** (elle refuse `NULL`).
- Le type `?string` accepte `null` : l'entité vient d'être créée et n'a pas encore reçu sa valeur.

---

## Étape 2 : le dépôt

Un **dépôt** est la classe qui sait **chercher** les objets d'une entité dans la base. Crée `src/Repository/MaterielRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\Materiel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Materiel>
 */
class MaterielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Materiel::class);
    }
}
```

Cette classe vide hérite déjà de méthodes prêtes à l'emploi : `find($id)`, `findAll()`, `findBy(...)`. Nous les utiliserons dans la partie 2.

Tu peux générer l'entité et son dépôt avec la commande `php bin/console make:entity`. Nous les écrivons à la main cette fois, pour que rien ne soit magique.

---

## Étape 3 : la migration

L'entité décrit ce que la table **doit** être. Mais la table n'existe pas encore. Une **migration** est un petit fichier PHP qui contient les requêtes SQL pour passer de l'état actuel de la base à l'état voulu. Elle est versionnée avec le code : toute l'équipe rejoue les mêmes.

Demande à Symfony de la fabriquer en comparant tes entités avec la base :

```bash
docker compose exec app php bin/console make:migration
```

Un fichier `migrations/VersionAAAAMMJJHHMMSS.php` apparaît. Ouvre-le et **relis-le** : c'est le réflexe à prendre. Tu dois y trouver :

```php
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE materiel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, categorie VARCHAR(60) NOT NULL)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE materiel');
}
```

`up()` fait avancer la base, `down()` la fait reculer. Exécute maintenant la migration :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
```

Réponds `yes` à la question. La table `materiel` existe.

---

## Étape 4 : quelques lignes de test

La table est vide. Ajoutons trois matériels avec une requête SQL directe :

```bash
docker compose exec app php bin/console doctrine:query:sql "INSERT INTO materiel (nom, categorie) VALUES ('Ordinateur portable', 'Informatique'), ('Écran 24 pouces', 'Informatique'), ('Chaise de bureau', 'Mobilier')"
```

Vérifie le contenu :

```bash
docker compose exec app php bin/console doctrine:query:sql "SELECT * FROM materiel"
```

Tu dois voir trois lignes, avec les identifiants 1, 2 et 3 attribués par la base.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée l'entité `Materiel` et son dépôt, génère et exécute la migration, puis ajoute les trois lignes. Pour vérifier, la requête `SELECT * FROM materiel` doit afficher trois matériels. Clique ensuite sur « Terminé » : la partie 2 branche les pages sur la base.
