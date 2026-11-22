## Pourquoi ce rappel

Tu connais les routes, les contrôleurs, Twig et les formulaires. Avant d'attaquer la sécurité, voici le reste de la boîte à outils, en lecture seulement : des outils que tu rencontreras dans la documentation et dans du code d'autres équipes. Aucun exercice ne les demande, mais le niveau Avancé les utilise sans les réexpliquer.

## Rediriger sans écrire de contrôleur

Une route peut ne rien faire d'autre que **renvoyer ailleurs**. Symfony fournit pour cela un contrôleur tout fait, `RedirectController` : on lui donne la destination dans les valeurs par défaut de la route.

```yaml
# config/routes.yaml
ancienne_liste:
    path: /parc/materiels
    controller: Symfony\Bundle\FrameworkBundle\Controller\RedirectController
    defaults:
        route: materiel_liste
        permanent: true
```

- `route` désigne la destination par son **nom** (pour une adresse extérieure, on utilise `path` à la place) ;
- `permanent: true` répond 301 (« déplacé pour toujours ») au lieu de 302 : les moteurs de recherche retiennent la nouvelle adresse ;
- le même contrôleur sait garder la méthode de la requête (`keepRequestMethod`) et la chaîne de requête (`keepQueryParams`).

Dans un contrôleur, tu connais déjà `redirectToRoute()`, qui produit une **redirection HTTP** : le navigateur fait une seconde requête. Une **redirection interne** (`forward()`) est différente : le contrôleur en appelle **un autre**, dans la même requête, et renvoie sa réponse. Le navigateur ne voit rien, l'adresse ne change pas.

```php
public function ancienneFiche(int $id): Response
{
    return $this->forward(MaterielController::class.'::fiche', ['id' => $id]);
}
```

## Un contrôleur dans un gabarit

Un gabarit peut afficher le résultat d'un **contrôleur interne** : un morceau de page qui a sa propre logique (un compteur, un menu) sans que chaque page ait à lui fournir ses données.

```twig
{{ render(controller('App\\Controller\\ParcController::compteur')) }}
```

Les deux barres obliques inversées sont voulues : dans un texte Twig, une seule serait lue comme un caractère d'échappement. Chaque `render()` exécute un contrôleur complet : on l'utilise avec modération.

## Les attributs spéciaux d'une route

Certains paramètres de route ont un sens pour Symfony lui-même :

| Paramètre | Rôle |
|---|---|
| `_locale` | la langue de la requête (tu l'as utilisé au sujet 8) |
| `_format` | le format de la réponse : `/materiels.json` met `json` dans `$request->getRequestFormat()` |
| `_fragment` | l'ancre ajoutée à l'adresse générée : `path('materiel_liste', {_fragment: 'bas'})` donne `/materiels#bas` |
| `_controller` | le contrôleur à appeler, quand on écrit la route en YAML |

## Restreindre une route par domaine ou par condition

Deux autres restrictions s'ajoutent à la méthode et aux contraintes de paramètres :

```php
// Seulement sur ce nom de domaine.
#[Route('/atelier', name: 'atelier', host: 'atelier.exemple.test')]

// Seulement si une expression est vraie (le composant ExpressionLanguage évalue le texte).
#[Route('/mobile', name: 'mobile', condition: "request.headers.get('User-Agent') matches '/Android/i'")]
```

La condition est écrite dans un petit langage d'expressions, avec `request` et `context` comme variables. Elle est pratique, mais **opaque** : on ne la voit pas dans la liste des routes. À réserver aux cas qu'une contrainte de paramètre ne sait pas exprimer.

## Twig : interpolation et ressources statiques

L'**interpolation** met le résultat d'une expression dans un texte entre guillemets doubles, avec `#{…}` :

```twig
{{ "Ticket n° #{ticket.id} : #{ticket.titre}" }}
```

C'est équivalent à coller avec `~`, en plus lisible. Pour les fichiers statiques (images, feuilles de style, scripts rangés dans `public/`), on passe par `asset()` :

```twig
<img src="{{ asset('images/logo.png') }}" alt="Logo de l'atelier">
```

`asset()` ajoute au besoin un préfixe commun (un dossier, un domaine de distribution) : si les fichiers déménagent un jour, une seule configuration change, pas chaque gabarit.

## Étendre un type de formulaire

Pour ajouter un comportement à **tous** les champs d'un type (un attribut HTML, une option), on n'écrit pas un nouveau type : on écrit une **extension de type**.

```php
class AideDeChampExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [TextType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined('aide');
    }
}
```

Grâce à l'autoconfiguration, la classe est reconnue comme extension de formulaire dès qu'elle est dans `src/`. Tous les champs texte acceptent alors l'option `aide`.

## À retenir

- Une redirection **HTTP** passe par le navigateur ; une redirection **interne** reste côté serveur.
- Les paramètres qui commencent par `_` (`_locale`, `_format`, `_fragment`) ont un sens pour Symfony.
- `host` et `condition` restreignent une route de plus : à utiliser seulement quand rien de plus simple ne convient.
- `asset()` et l'interpolation rendent les gabarits plus faciles à déplacer et à lire.
