## Pourquoi ce rappel

Dans le sujet qui arrive, tu vas écrire des classes qui représentent des lignes d'une base de données, et d'autres qui reçoivent ce dont elles ont besoin sans le fabriquer elles-mêmes. Cela repose sur quatre notions de PHP que tu connais en théorie. Les voici en version courte, avec le code que tu retrouveras.

## Classe, objet, propriétés et méthodes

Une **classe** est un plan ; un **objet** est un exemplaire fabriqué avec ce plan.

```php
class Materiel
{
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }
}

$ecran = new Materiel();
$ecran->setNom('Écran 24 pouces');
```

Deux habitudes à garder :

- les propriétés sont **privées** ; on les lit et on les modifie par des méthodes publiques (`getNom()`, `setNom()`) ;
- une méthode qui renvoie `$this` permet d'enchaîner les appels : `(new Materiel())->setNom('Écran')->setCategorie('Informatique')`.

## Espaces de noms

Un **espace de noms** range les classes comme des dossiers rangent des fichiers. Dans un projet Symfony, l'espace de noms suit le dossier : le fichier `src/Entity/Materiel.php` déclare `namespace App\Entity;`, et son nom complet est `App\Entity\Materiel`.

```php
namespace App\Controller;

use App\Repository\MaterielRepository;   // j'annonce la classe que j'utilise
```

L'instruction `use` ne charge rien : elle évite seulement d'écrire le nom complet à chaque fois.

## Interfaces et classes abstraites

- Une **interface** est un contrat : elle liste des méthodes qu'une classe promet d'offrir, sans écrire leur contenu. Une classe peut en respecter plusieurs (`implements`).
- Une **classe abstraite** est une classe incomplète qu'on ne peut pas instancier : on en hérite (`extends`) pour la compléter. Une classe n'hérite que d'une seule classe.

Pourquoi c'est utile : une méthode qui demande une interface accepte n'importe quelle classe qui respecte le contrat, ce qui permet de remplacer une pièce sans toucher au reste.

## Les attributs

Depuis PHP 8, on peut ajouter des informations à une classe, une propriété ou une méthode avec un **attribut**, écrit entre `#[` et `]`. Il ne change pas ce que fait le code lui-même : une bibliothèque le lit pour s'en servir.

```php
#[Route('/materiels', name: 'materiel_liste')]   // « cette méthode répond à cette adresse »
public function liste(): Response
```

```php
#[ORM\Column(length: 120)]                       // « cette propriété est une colonne de 120 caractères »
private ?string $nom = null;
```

Dans ce parcours, on utilise **uniquement des attributs**. Si tu vois ailleurs des commentaires `@Route(...)`, ce sont des annotations : une ancienne écriture, que nous n'employons pas.

## Pour vérifier que tu as retenu

1. À quoi sert `use` en tête de fichier ?
2. Pourquoi une propriété est-elle privée ?
3. Quelle est la différence entre une interface et une classe abstraite ?
4. Que lit Symfony quand il voit `#[Route(...)]` ?

Quand tes réponses te paraissent évidentes, clique sur « Terminé ».
