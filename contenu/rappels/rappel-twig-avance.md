## Pourquoi ce rappel

Le sujet qui arrive **traduit** des pages. Cela revient à écrire dans les gabarits beaucoup plus de petites opérations : transformer une valeur, appeler une fonction, inclure un morceau de page, parfois comprendre ce qu'une variable contient vraiment. Voici, en version courte, les quatre outils de Twig dont tu vas te servir : les filtres et les fonctions, les inclusions, le rendu d'un contrôleur, et le débogage des variables.

## Filtres et fonctions

Un **filtre** transforme une valeur. On l'écrit après la valeur, séparé par `|`, avec des arguments éventuels entre parenthèses :

```twig
{{ materiel.nom|upper }}
{{ ticket.creeLe|date('d/m/Y à H:i') }}
{{ ticket.etiquettes|join(', ') }}
{{ materiel.numeroSerie|default('non renseigné') }}
{{ tickets|length }}
```

- `upper` met en majuscules, `date` formate une date, `join` colle les éléments d'une liste, `default` remplace une valeur vide ou absente, `length` compte les éléments d'une liste ou les caractères d'un texte ;
- on peut **enchaîner** les filtres : `{{ titre|trim|lower|capitalize }}` s'exécute de gauche à droite ;
- `trans`, qui traduit un texte, est un filtre comme les autres : tu le rencontreras dans la démonstration.

Une **fonction** se **calcule** et se lit comme en PHP : `nom(arguments)`.

```twig
<a href="{{ path('materiel_fiche', {id: materiel.id}) }}">Fiche</a>
{{ max(3, 7, 5) }}
{{ range(1, 5)|join(' - ') }}
```

- `path()` fabrique l'adresse d'une route à partir de son nom (tu l'emploies déjà) ;
- `max()` et `range()` sont des fonctions de Twig. Quand le résultat d'une fonction est une liste, on peut lui appliquer un filtre.

Un **filtre** reçoit une valeur et la transforme ; une **fonction** n'a pas de valeur de départ, elle en produit une. Cette différence te dit lequel utiliser : « je veux transformer ce que j'ai » → filtre ; « je veux obtenir quelque chose » → fonction.

## Inclusions

Un gabarit qui grossit se découpe. `include()` insère un autre gabarit à cet endroit :

```twig
<ul>
    {% for ticket in materiel.tickets %}
        {{ include('ticket/_ticket.html.twig') }}
    {% endfor %}
</ul>
```

- le gabarit inclus **voit** toutes les variables du gabarit qui l'inclut (ici `ticket`) ;
- pour n'en passer que certaines : `include('ticket/_ticket.html.twig', {ticket: ticket}, with_context = false)` ;
- par convention, un gabarit qui n'est qu'un morceau de page commence par `_` (`_ticket.html.twig`, `_formulaire.html.twig`).

Ne confonds pas avec l'**héritage** (`extends` et `block`) : un gabarit hérité **remplit des trous** laissés par son parent, alors qu'un gabarit inclus est **posé** tel quel.

## Rendre un contrôleur depuis un gabarit

Parfois, un morceau de page a besoin de **ses propres données** : un bloc « derniers tickets » sur toutes les pages. Plutôt que de faire passer ces données par chaque contrôleur, le gabarit peut **demander à un contrôleur de produire ce bloc** :

```twig
{{ render(controller('App\\Controller\\TicketController::derniers', {nombre: 3})) }}
```

- `controller()` désigne la méthode à appeler (cette méthode `derniers` n'existe pas dans ton projet : c'est un exemple) ; les arguments sont ceux de la méthode ;
- `render()` exécute une **sous-requête** et insère la réponse du contrôleur à cet endroit ;
- chaque appel déroule une requête interne complète : à réserver aux blocs qui ont vraiment des données à eux, pas à de simples boucles.

## Voir ce que contient une variable

Quand un gabarit n'affiche pas ce que tu attends, regarde la variable plutôt que de deviner :

```twig
{{ dump(materiel) }}
{{ dump() }}
```

- `dump(variable)` affiche le contenu d'une variable, `dump()` sans argument affiche **toutes** les variables du gabarit ;
- la fonction n'existe qu'en **mode de débogage** : en production, un `dump()` oublié dans un gabarit provoque une **erreur** (« fonction inconnue »). Retire-le avant de livrer ;
- dans ce projet, les tests tournent avec `strict_variables: true` : utiliser une variable qui n'existe pas provoque une **erreur** au lieu d'un blanc silencieux. C'est un bon réflexe de développement.

## À retenir

- Un filtre transforme une valeur (`|date`, `|join`, `|default`), une fonction en produit une (`path()`, `range()`).
- `include()` pose un gabarit tel quel ; `extends` et `block` remplissent des trous.
- `render(controller(…))` demande à un contrôleur un morceau de page, au prix d'une sous-requête.
- `dump()` montre ce que contient une variable ; elle n'existe qu'en mode de débogage.
