## Ce que nous construisons

La partie 1 renvoyait du texte brut. Une vraie page est du HTML, et le HTML ne se range pas dans un contrôleur : **le contrôleur prépare des données, le gabarit les affiche**. Le gabarit s'écrit en **Twig**, un langage qui ressemble à du HTML avec quelques instructions en plus.

Trois fichiers vont naître : un gabarit de base commun à toutes les pages, un gabarit pour la liste et un pour la fiche.

---

## Étape 1 : le gabarit de base

Le projet contient déjà un fichier `templates/base.html.twig`, mais il appelle un outil de gestion des fichiers CSS et JavaScript que nous n'avons pas installé : l'ouvrir ferait une erreur. **Remplace tout son contenu** par ceci :

```twig
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>{% block title %}Parc informatique{% endblock %}</title>
    </head>
    <body>
        <header>
            <a href="{{ path('materiel_liste') }}">Parc informatique</a>
        </header>

        {% block body %}{% endblock %}
    </body>
</html>
```

Les trois notations de Twig à connaître :

- `{{ ... }}` **affiche** une valeur ;
- `{% ... %}` **exécute** une instruction (bloc, boucle, condition) ;
- `{# ... #}` est un commentaire.

Ici, `{% block %}` découpe la page en zones que les pages enfants pourront remplir. `path('materiel_liste')` fabrique l'adresse de la route portant ce **nom** : si l'adresse change un jour, les liens suivent.

---

## Étape 2 : le gabarit de la liste

Crée `templates/materiel/liste.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Matériels{% endblock %}

{% block body %}
    <h1>Matériels</h1>

    <ul>
        {% for id, materiel in materiels %}
            <li>
                <a href="{{ path('materiel_fiche', {id: id}) }}">{{ materiel.nom }}</a>
                ({{ materiel.categorie }})
            </li>
        {% endfor %}
    </ul>
{% endblock %}
```

- `{% extends %}` dit : « cette page **hérite** de la base ». Elle ne remplit que les blocs `title` et `body`.
- `{% for id, materiel in materiels %}` parcourt un tableau en récupérant la clé (`id`) et la valeur (`materiel`).
- `materiel.nom` lit la clé `nom` du tableau.
- `path('materiel_fiche', {id: id})` fabrique `/materiels/1`, `/materiels/2`… en remplissant le paramètre de la route.
- Twig **échappe automatiquement** ce qu'il affiche : un nom contenant `<script>` serait affiché comme du texte, pas exécuté.

---

## Étape 3 : le gabarit de la fiche

Crée `templates/materiel/fiche.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ materiel.nom }}{% endblock %}

{% block body %}
    <h1>{{ materiel.nom }}</h1>

    <p>Catégorie : {{ materiel.categorie }}</p>

    <p><a href="{{ path('materiel_liste') }}">Retour à la liste</a></p>
{% endblock %}
```

---

## Étape 4 : le contrôleur appelle les gabarits

Dans le contrôleur, les deux `new Response(...)` laissent la place à `$this->render()`, une méthode d'`AbstractController` : elle prend le nom du gabarit et les variables à lui transmettre, et renvoie la `Response`. Voici le fichier complet :

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

    #[Route('/materiels', name: 'materiel_liste')]
    public function liste(): Response
    {
        return $this->render('materiel/liste.html.twig', [
            'materiels' => self::MATERIELS,
        ]);
    }

    #[Route('/materiels/{id}', name: 'materiel_fiche', requirements: ['id' => '\d+'])]
    public function fiche(int $id): Response
    {
        $materiel = self::MATERIELS[$id] ?? throw $this->createNotFoundException('Matériel introuvable.');

        return $this->render('materiel/fiche.html.twig', [
            'id' => $id,
            'materiel' => $materiel,
        ]);
    }
}
```

Le tableau passé en second argument donne leurs noms aux variables que le gabarit verra : `materiels`, `id`, `materiel`.

---

## Étape 5 : vérifier

Recharge `http://localhost:8000/materiels` : tu vois une vraie liste avec des liens. Clique sur « Écran 24 pouces », puis sur « Retour à la liste ». Enfin, affiche le code source de la page (clic droit, « Afficher le code source ») : tu retrouves le gabarit de base, avec la balise `<title>` remplie par le bloc de la page.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Remplace `templates/base.html.twig`, crée les deux gabarits du dossier `templates/materiel/`, puis mets à jour ton contrôleur. Vérifie la liste, une fiche et l'adresse `/materiels/999` (qui doit toujours répondre 404). Ensuite, clique sur « Terminé » : l'exercice t'attend, avec les fournisseurs.
