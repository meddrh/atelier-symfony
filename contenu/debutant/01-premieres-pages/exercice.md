## À faire

Tu as vu comment créer des pages pour le matériel. À toi de faire la même chose pour les **fournisseurs**, en repartant de ta fiche de préparation.

Crée deux pages :

1. **`/fournisseurs`** : la liste des fournisseurs. Chaque nom est un lien vers la fiche du fournisseur.
2. **`/fournisseurs/{id}`** : la fiche d'un fournisseur, avec son nom et sa ville.

Les données sont écrites en dur dans le contrôleur (la base de données vient au sujet suivant) :

| Identifiant | Nom | Ville |
|---|---|---|
| 1 | Dupont Informatique | Lyon |
| 2 | Bureau Express | Nantes |
| 3 | Papeterie Martin | Lille |

Contraintes :

- l'identifiant doit être **un nombre** : une adresse comme `/fournisseurs/abc` ne correspond à aucune page ;
- un identifiant qui n'existe pas (`/fournisseurs/999`) répond par la page **introuvable** ;
- les deux pages **héritent** du gabarit `base.html.twig`, comme celles du matériel ;
- tes pages du matériel doivent continuer à fonctionner : le premier test les vérifie.

## Squelette des fichiers

Voici le début du contrôleur : le chemin du fichier, l'espace de noms, la classe et la forme de chaque méthode. Il ne te donne pas la solution : le corps des méthodes, la vérification de l'identifiant et la page introuvable restent à écrire. Les gabarits Twig ne sont pas dans ce squelette : ce ne sont pas des classes PHP, tu les crées toi-même.

```php
<?php
// src/Controller/FournisseurController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FournisseurController extends AbstractController
{
    #[Route('/fournisseurs')]
    public function liste(): Response
    {
        // À toi : donner la liste des fournisseurs au gabarit et rendre la page.
    }

    #[Route('/fournisseurs/{id}')]
    public function fiche(int $id): Response
    {
        // À toi : retrouver le fournisseur, rendre sa fiche, ou la page introuvable.
    }
}
```

Le dossier de ce fichier (`src/Controller/`) donne l'espace de noms (`App\Controller`), et le nom du fichier donne le nom de la classe : c'est la règle PSR-4 que Symfony utilise pour retrouver tes classes.

Tant qu'un corps de méthode est vide, la page correspondante répond par une erreur 500 (« Return value must be of type …Response, none returned ») : c'est normal, la méthode promet une `Response` qu'elle ne rend pas encore. L'erreur disparaît dès que tu écris le corps.

## Pour lancer les tests

Dans un terminal, à la racine du projet :

```bash
docker compose exec app bin/exercice test
```

La commande lance les tests, enregistre le résultat et te dit s'ils passent tous. Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Les pages du matériel de la démonstration fonctionnent : `/materiels` répond (code 200) et affiche « Ordinateur portable ».
- La fiche `/materiels/2` affiche « Écran 24 pouces ».
- `/materiels/999` répond 404.
- `/fournisseurs` répond et montre les trois noms de fournisseurs.
- La liste contient un lien par fournisseur, vers `/fournisseurs/{id}`.
- `/fournisseurs/2` affiche « Bureau Express ».
- `/fournisseurs/999` répond 404.
- `/fournisseurs/abc` répond 404 (et non 500).
- Les pages sont de vraies pages HTML, avec une balise `title`, héritées du gabarit de base.
