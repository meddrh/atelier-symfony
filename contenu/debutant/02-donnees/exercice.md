## À faire

Les matériels sont en base. À toi de faire la même chose pour les **fournisseurs**, en suivant ta fiche de préparation.

1. Crée l'entité **`Fournisseur`** (`App\Entity\Fournisseur`) avec :
   - `nom` : texte, obligatoire ;
   - `email` : texte, obligatoire, **unique** ;
   - `telephone` : texte, **facultatif**.
2. Crée son dépôt, génère la migration et exécute-la.
3. Remplace les fournisseurs écrits en dur par la lecture dans la base :
   - `/fournisseurs` : la liste, **triée par nom** ;
   - `/fournisseurs/{id}` : la fiche, avec le nom, l'e-mail et le téléphone s'il existe ; **404** si l'identifiant est inconnu.

Les tests attendent exactement ces méthodes sur l'entité : `getId()`, `getNom()`, `setNom()`, `getEmail()`, `setEmail()`, `getTelephone()`, `setTelephone()`. Chaque méthode `set…` renvoie l'objet lui-même (`return $this;`), comme pour `Materiel`.

Les tests fabriquent eux-mêmes leurs fournisseurs : tu n'as pas besoin d'en ajouter dans la base pour qu'ils passent. Mais **exécute quand même la migration** et ajoute un fournisseur à la main : sans cela, tes pages ne fonctionneraient pas dans le navigateur.

## Squelette des fichiers

Ce sujet crée deux classes, l'entité et son dépôt, et modifie deux méthodes du contrôleur que tu as déjà écrit. Le squelette ne te donne pas la solution : les colonnes de l'entité, la lecture dans la base, le tri et la page introuvable restent à écrire. Les gabarits Twig et la migration ne sont pas dans ce squelette : ce ne sont pas des classes PHP, tu les crées (ou tu les modifies) toi-même.

L'entité d'abord. Elle ne montre que son attribut de classe et son identifiant : les colonnes `nom`, `email` et `telephone`, puis les méthodes d'accès que les tests attendent, c'est à toi de les ajouter, à la main ou avec `php bin/console make:entity`. Tant que les colonnes manquent, il n'y a rien à mettre dans la migration.

```php
<?php
// src/Entity/Fournisseur.php

namespace App\Entity;

use App\Repository\FournisseurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FournisseurRepository::class)]
class Fournisseur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // À toi : les colonnes nom, email et telephone, puis les méthodes d'accès.
}
```

Le dépôt : sa seule méthode est le constructeur, qui doit passer au constructeur du parent le gestionnaire reçu et la classe de l'entité.

```php
<?php
// src/Repository/FournisseurRepository.php

namespace App\Repository;

use App\Entity\Fournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fournisseur>
 */
class FournisseurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // À toi : appeler le constructeur du parent, en lui donnant le gestionnaire et la classe de l'entité.
    }
}
```

Le contrôleur existe déjà depuis le sujet précédent, et tu y as écrit du code : **ne colle rien à la place de ton fichier**. Ses deux méthodes gardent leur route et leur nom, mais reçoivent maintenant le dépôt, comme celles de `MaterielController` dans la démonstration. Ce bloc montre leur nouvelle signature : garde ton code, adapte-le (la signature, puis le corps qui lit la base au lieu du tableau écrit en dur). N'oublie pas le `use App\Repository\FournisseurRepository;` en haut du fichier.

```php
// à modifier dans src/Controller/FournisseurController.php

    // Ce qui change : reçoit maintenant le dépôt. Garde ton code et adapte-le.
    #[Route('/fournisseurs', name: 'fournisseur_liste')]
    public function liste(FournisseurRepository $fournisseurRepository): Response
    {
        // À toi : donner au gabarit les fournisseurs de la base, triés par nom, et rendre la page.
    }

    // Ce qui change : reçoit maintenant le dépôt. Garde ton code et adapte-le.
    #[Route('/fournisseurs/{id}', name: 'fournisseur_fiche', requirements: ['id' => '\d+'])]
    public function fiche(int $id, FournisseurRepository $fournisseurRepository): Response
    {
        // À toi : retrouver le fournisseur, rendre sa fiche, ou la page introuvable.
    }
```

Chaque fichier se range selon PSR-4 : `src/Entity/` donne `App\Entity`, `src/Repository/` donne `App\Repository`, et le nom du fichier donne le nom de la classe.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

## Tests attendus

- La liste `/materiels` affiche les matériels de la base, et plus ceux du tableau en dur.
- La fiche `/materiels/{id}` affiche le nom et la catégorie lus dans la base.
- `/materiels/999` répond 404.
- Un fournisseur enregistré puis relu depuis la base garde son nom, son e-mail et son téléphone.
- Le téléphone est facultatif : sans valeur, il est relu comme absent.
- Deux fournisseurs ne peuvent pas partager le même e-mail : la base refuse le second.
- La liste `/fournisseurs` est triée par ordre alphabétique de nom.
- La liste `/fournisseurs` ne contient plus les fournisseurs écrits en dur.
- La fiche `/fournisseurs/{id}` affiche le nom et l'e-mail.
- `/fournisseurs/999` répond 404.
- `/fournisseurs/abc` répond 404.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
