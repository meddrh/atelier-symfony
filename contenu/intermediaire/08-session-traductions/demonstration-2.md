## Ce que nous construisons

Une application utilisée dans deux langues ne peut pas avoir ses phrases écrites **dans** ses gabarits : il faudrait un gabarit par langue. On sépare donc le **texte** de la **page** : le gabarit écrit une **clé** (`parc.titre`), et un **catalogue de traductions** donne la phrase correspondante dans chaque langue.

Dans cette partie, une nouvelle page, **le résumé du parc**, disponible en français et en anglais :

- une page à l'adresse `/fr/parc` et `/en/parc` : la langue fait partie de l'adresse ;
- l'adresse `/parc`, sans langue, qui choisit celle que **le navigateur du visiteur préfère** ;
- un compteur qui accorde le **pluriel** : « Aucun matériel », « 1 matériel », « 3 matériels » ;
- le menu de toutes les pages, traduit lui aussi.

---

## Étape 1 : une langue dans l'adresse

Une route peut contenir le paramètre spécial `_locale` : Symfony en fait **la langue de la requête**. Il suffit ensuite de lire cette langue (`app.request.locale` dans Twig), et le traducteur l'utilise tout seul.

Une adresse comme `/xx/parc` ne doit pas être acceptée : on **limite** `_locale` aux langues que l'on propose, avec une **contrainte** sur le paramètre, comme tu l'as fait pour `id`.

Crée `src/Controller/ParcController.php` :

```php
<?php

namespace App\Controller;

use App\Repository\MaterielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParcController extends AbstractController
{
    private const LANGUES = ['fr', 'en'];

    // Sans langue dans l'adresse : on choisit celle que le navigateur préfère, parmi celles qu'on propose.
    #[Route('/parc', name: 'parc_accueil')]
    public function accueil(Request $request): Response
    {
        $langue = $request->getPreferredLanguage(self::LANGUES) ?? 'fr';

        return $this->redirectToRoute('parc_resume', ['_locale' => $langue]);
    }

    // « _locale » est un paramètre spécial : Symfony en fait la langue de la requête. On le limite aux langues proposées.
    #[Route('/{_locale}/parc', name: 'parc_resume', requirements: ['_locale' => 'fr|en'])]
    public function resume(MaterielRepository $materielRepository): Response
    {
        return $this->render('parc/resume.html.twig', [
            'nombre' => $materielRepository->count([]),
        ]);
    }
}
```

- `requirements: ['_locale' => 'fr|en']` n'accepte que `fr` ou `en` : `/de/parc` n'existe pas et répond 404 ;
- sur la route `/parc`, `$request->getPreferredLanguage(self::LANGUES)` regarde l'en-tête `Accept-Language` envoyé par le navigateur (la liste des langues que le visiteur a configurées, par ordre de préférence) et renvoie **la meilleure langue parmi celles que tu proposes**. Si aucune ne convient, ou si le navigateur n'envoie rien, c'est la première de la liste : `fr` ;
- `redirectToRoute('parc_resume', ['_locale' => $langue])` fabrique l'adresse `/fr/parc` ou `/en/parc` et redirige vers elle ;
- `$materielRepository->count([])` compte les lignes de la table.

---

## Étape 2 : le catalogue de traductions

Les catalogues sont des fichiers de `translations/`. Leur **nom** dit tout : `messages+intl-icu.fr.yaml` se lit « domaine `messages`, format `intl-icu`, langue `fr`, fichier YAML ».

- le **domaine** `messages` est celui que `trans` utilise quand on n'en précise pas ;
- `+intl-icu` indique que les phrases sont écrites au format **ICU**, celui qui sait gérer les pluriels. Il demande l'extension PHP `intl`, déjà présente dans ton conteneur ;
- la **langue** vient après.

Le fichier français, à la suite de ce que tu as écrit à la partie précédente :

```yaml
nav:
    materiels: Parc informatique
    utilisateurs: Utilisateurs
    resume: Résumé

parc:
    titre: Le parc informatique
    compteur: >-
        {nombre, plural,
        =0 {Aucun matériel}
        one {# matériel}
        other {# matériels}}
    francais: Français
    anglais: English
```

Et le fichier anglais, qui a **exactement les mêmes clés** :

```yaml
materiel:
    filtre:
        tous: All
        affectes: Assigned
        libres: Unassigned
    filtre_inconnu: "This filter does not exist: all devices are shown."

nav:
    materiels: IT equipment
    utilisateurs: Users
    resume: Summary

parc:
    titre: The IT equipment
    compteur: >-
        {nombre, plural,
        =0 {No devices}
        one {# device}
        other {# devices}}
    francais: Français
    anglais: English
```

Lis le pluriel :

```yaml
compteur: >-
    {nombre, plural,
    =0 {Aucun matériel}
    one {# matériel}
    other {# matériels}}
```

- `{nombre, plural, …}` : le message est un **pluriel** et dépend de la variable `nombre` ;
- `=0 {…}` : un cas **exact**. En français, 0 compte comme un singulier pour la règle de la langue (« 0 matériel »), alors on écrit explicitement « Aucun matériel » ;
- `one {…}` et `other {…}` : les catégories de la langue. `#` est remplacé par le nombre ;
- `>-` est la notation YAML d'un texte réparti sur plusieurs lignes, recollées avec une espace : le message reste lisible.

Deux pièges de ce format : une **apostrophe** juste devant une accolade (`{` ou `}`) sert à protéger le texte qui suit, on la double (`''`) pour la garder ; et la **même clé doit exister dans toutes les langues**. Si une clé manque en anglais, Symfony **retombe** sur le français (la langue de secours, déclarée dans `config/packages/translation.yaml`) : le visiteur lit un mélange sans qu'aucune erreur ne soit signalée. Un trou de traduction ne se voit pas : il se **vérifie**.

---

## Étape 3 : le gabarit de la page

Crée `templates/parc/resume.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ 'parc.titre'|trans }}{% endblock %}

{% block body %}
    <h1>{{ 'parc.titre'|trans }}</h1>

    <p class="compteur">{{ 'parc.compteur'|trans({nombre: nombre}) }}</p>

    <p>
        <a href="{{ path('parc_resume', {_locale: 'fr'}) }}">{{ 'parc.francais'|trans }}</a>
        <a href="{{ path('parc_resume', {_locale: 'en'}) }}">{{ 'parc.anglais'|trans }}</a>
    </p>
{% endblock %}
```

- `'parc.titre'|trans` cherche la clé dans le catalogue de la langue de la requête ;
- `trans({nombre: nombre})` fournit les valeurs du message : la variable `nombre` du pluriel prend la valeur de la variable Twig du même nom ;
- `path('parc_resume', {_locale: 'fr'})` fabrique le lien vers la même page dans l'autre langue ;
- les noms des langues restent écrits dans leur propre langue (« Français », « English ») : le visiteur doit les reconnaître même s'il ne comprend pas la page.

---

## Étape 4 : le gabarit de base

Remplace `templates/base.html.twig` : la langue du document est déclarée, et le menu est traduit.

```twig
<!DOCTYPE html>
<html lang="{{ app.request.locale }}">
    <head>
        <meta charset="UTF-8">
        <title>{% block title %}{{ 'nav.materiels'|trans }}{% endblock %}</title>
    </head>
    <body>
        <header>
            <a href="{{ path('materiel_liste') }}">{{ 'nav.materiels'|trans }}</a>
            <a href="{{ path('utilisateur_liste') }}">{{ 'nav.utilisateurs'|trans }}</a>
            <a href="{{ path('parc_resume', {_locale: app.request.locale}) }}">{{ 'nav.resume'|trans }}</a>
        </header>

        {% for type, messages in app.flashes %}
            {% for message in messages %}
                <p class="flash flash-{{ type }}">{{ message }}</p>
            {% endfor %}
        {% endfor %}

        {% block body %}{% endblock %}
    </body>
</html>
```

- `<html lang="{{ app.request.locale }}">` dit au navigateur, aux lecteurs d'écran et aux moteurs de recherche en quelle langue est la page ;
- les trois liens du menu passent par `trans` ;
- sur une page qui n'a **pas** de `_locale` dans son adresse (comme `/materiels`), la langue de la requête est la langue **par défaut** de l'application, `fr`. Elle est déclarée dans `config/packages/translation.yaml`, que Symfony a créé en installant le composant : `default_locale: fr` et `fallbacks: [fr]` ;
- on passe `{_locale: app.request.locale}` à `path('parc_resume', …)` : une route qui contient `_locale` a besoin qu'on lui donne la langue, sans quoi elle ne peut pas fabriquer son adresse.

---

## Étape 5 : vérifier

1. Ouvre `/fr/parc` : le compteur dit « 3 matériels » (ou le nombre que tu as). Ouvre `/en/parc` : « 3 devices ».
2. Supprime tes matériels jusqu'à n'en garder qu'un, puis aucun : « 1 matériel », « Aucun matériel », puis « 1 device », « No devices ».
3. Ouvre `/de/parc` : page introuvable.
4. Ouvre `/parc` : tu arrives sur `/fr/parc` ou `/en/parc` selon la langue de ton navigateur.
5. Sur `/en/parc`, le menu est en anglais. Sur `/materiels`, il est en français.
6. **Cherche un trou** : retire une clé du fichier anglais, recharge `/en/parc` : tu lis du français à la place, sans erreur. Remets la clé.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée le contrôleur, complète les deux catalogues, crée le gabarit de la page et remplace le gabarit de base. Vérifie les six points de l'étape 5, puis clique sur « Terminé ».
