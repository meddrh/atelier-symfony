## Ce que nous construisons

Nous allons créer deux pages pour le **matériel** du parc : une liste (`/materiels`) et la fiche d'un matériel (`/materiels/2`). Dans cette première partie, il n'y a pas encore de HTML : nous voulons seulement comprendre comment **une adresse mène à du code PHP**.

Avant de commencer, lance le projet :

```bash
docker compose up -d
```

Le site répond alors sur `http://localhost:8000`. Chaque étape de cette démonstration se vérifie dans le navigateur.

---

## Étape 1 : la classe du contrôleur

Un **contrôleur** est une classe PHP dont chaque méthode publique répond à une page. Par convention :

- le fichier est rangé dans `src/Controller/` ;
- son nom se termine par `Controller` ;
- la classe hérite de `AbstractController`, qui offre des méthodes pratiques (nous allons en utiliser deux).

Crée le fichier `src/Controller/MaterielController.php` :

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MaterielController extends AbstractController
{
    private const MATERIELS = [
        1 => ['nom' => 'Ordinateur portable', 'categorie' => 'Informatique'],
        2 => ['nom' => 'Écran 24 pouces', 'categorie' => 'Informatique'],
        3 => ['nom' => 'Chaise de bureau', 'categorie' => 'Mobilier'],
    ];
}
```

La constante `MATERIELS` joue le rôle de la base de données, pour l'instant : trois matériels écrits en dur, avec leur identifiant comme clé.

---

## Étape 2 : une première route

Une **route** associe une adresse à une méthode. On la déclare avec l'attribut `#[Route]`, juste au-dessus de la méthode. Ajoute la méthode `liste()` dans la classe :

```php
#[Route('/materiels', name: 'materiel_liste')]
public function liste(): Response
{
    return new Response('Nombre de matériels : '.count(self::MATERIELS));
}
```

Trois choses à noter :

- le premier argument de `#[Route]` est l'**adresse** ;
- `name` donne un **nom** à la route. Ce nom sert à fabriquer des liens sans écrire l'adresse en dur (nous le ferons dans la partie 2) ;
- une méthode de contrôleur **renvoie toujours une `Response`**, jamais du texte brut.

Ouvre `http://localhost:8000/materiels` : tu dois lire « Nombre de matériels : 3 ».

---

## Étape 3 : une route avec un paramètre

Pour afficher un seul matériel, l'identifiant doit faire partie de l'adresse. On l'écrit entre accolades :

```php
#[Route('/materiels/{id}', name: 'materiel_fiche', requirements: ['id' => '\d+'])]
public function fiche(int $id): Response
{
    $materiel = self::MATERIELS[$id] ?? throw $this->createNotFoundException('Matériel introuvable.');

    return new Response($materiel['nom']);
}
```

- `{id}` devient automatiquement l'argument `$id` de la méthode (même nom).
- `requirements: ['id' => '\d+']` impose que `id` soit **un nombre** (`\d+` : un ou plusieurs chiffres). Sans cette restriction, `/materiels/abc` correspondrait à la route et ferait planter la méthode.

---

## Étape 4 : la page introuvable

Que se passe-t-il pour `/materiels/999` ? L'adresse correspond à la route, mais le matériel n'existe pas. L'expression

```php
self::MATERIELS[$id] ?? throw $this->createNotFoundException('Matériel introuvable.');
```

se lit : « prends le matériel ; s'il n'existe pas, lève une exception de page introuvable ». Symfony la transforme en réponse **404**.

Teste les quatre adresses :

| Adresse | Résultat attendu |
|---|---|
| `/materiels/2` | « Écran 24 pouces » |
| `/materiels/999` | page d'erreur 404 |
| `/materiels/abc` | page d'erreur 404 (aucune route ne correspond) |
| `/materiels` | « Nombre de matériels : 3 » |

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée `src/Controller/MaterielController.php` avec les deux routes, puis vérifie les quatre adresses du tableau dans ton navigateur. Quand tout répond comme prévu, clique sur « Terminé » : la partie 2 remplace ces textes bruts par de vraies pages HTML.
