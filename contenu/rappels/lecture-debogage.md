## Pourquoi cette lecture

Tes tests te disent **qu'**il y a un problème. Cette lecture dit **comment le trouver** : quels outils Symfony fournit pour regarder ce qui se passe dans l'application. Pas d'exercice : ce sont des outils à connaître, et tu les essaieras dans ton projet. Cette fiche ne donne pas de badge.

Une précision d'abord : **le Web Profiler n'est pas installé dans ce projet**. Sa description plus bas est une lecture ; les commandes de la console et les fonctions `dump`, elles, marchent chez toi.

## Le mode de débogage

Tous les outils ci-dessous reposent sur un interrupteur : le **mode de débogage** (`APP_DEBUG`, `kernel.debug`). Il est actif en développement, **coupé en production**. Quand il est actif, Symfony garde des informations sur chaque requête, affiche des pages d'erreur détaillées et recompile ce qui change ; coupé, il ne fait rien de tout cela, pour aller vite et ne rien révéler. Conséquence à retenir : **ce qu'on affiche pour déboguer ne doit jamais rester en production**.

## Voir une variable : `dump()` et `dd()`

En PHP, `dump($variable)` affiche une variable avec une mise en forme lisible (arborescence, types, valeurs) ; `dd($variable)` fait la même chose **puis arrête le script** (« dump and die »). Ces deux fonctions viennent du composant `VarDumper`.

```php
dump($materiel);        // affiche l'objet, le script continue
dd($request->query);    // affiche, puis arrête tout
```

Dans un gabarit Twig, c'est la fonction `dump()` du rappel précédent : `{{ dump(materiel) }}`, ou `{{ dump() }}` pour tout ce que le gabarit connaît. Elle n'existe qu'en mode de débogage : un `dump()` oublié dans un gabarit **plante** la page en production (« fonction inconnue »).

## La barre de débogage et le profileur

Le **Web Profiler** est un paquet de développement (`symfony/web-profiler-bundle`) qui ajoute à **chaque page** une barre en bas de l'écran et un **profileur**. Pour chaque requête, il a enregistré :

- la **requête** et la **réponse** : méthode, en-têtes, paramètres, session, cookies ;
- la **route** retrouvée et le contrôleur appelé ;
- les **requêtes SQL** de Doctrine, leur nombre et leur durée ;
- les **formulaires**, la **validation** (quelles contraintes ont échoué), les **traductions** utilisées ou manquantes ;
- le temps passé et la mémoire consommée, les événements déclenchés.

Chaque famille d'informations est produite par un **collecteur de données** : un petit objet qui regarde un aspect de la requête et le range dans le profil. Une page du profileur correspond à un collecteur. Le profileur garde l'historique des requêtes récentes : tu peux ouvrir celle d'il y a une minute, une **redirection** par exemple, qui a disparu de l'écran trop vite pour être lue.

Il n'est destiné qu'au développement : on l'installe comme dépendance de développement et on ne le livre jamais en production.

## Les commandes de la console

La console répond aux questions sur l'application **sans la lancer dans un navigateur**.

**`debug:router`** liste toutes les routes, avec leur nom, leurs méthodes et leur chemin :

```bash
docker compose exec app php bin/console debug:router
```

```text
 Name                       Method     Path
 materiel_liste             ANY        /materiels
 materiel_nouveau           GET|POST   /materiels/nouveau
 materiel_fiche             ANY        /materiels/{id}
 parc_resume                ANY        /{_locale}/parc
 ticket_liste               GET        /{_locale}/tickets
 ticket_nouveau             GET|POST   /materiels/{id}/tickets/nouveau
```

(Le tableau réel a plus de colonnes et de lignes.) Avec le nom d'une route, `debug:router ticket_liste` détaille ses paramètres, ses contraintes (`_locale: fr|en`), son contrôleur.

**`router:match`** répond à la question inverse : « quelle route traite cette adresse ? »

```bash
docker compose exec app php bin/console router:match /fr/tickets
docker compose exec app php bin/console router:match /de/tickets
```

La première affiche `Route "ticket_liste" matches` ; la seconde dit que la route « correspond presque » mais que la contrainte `_locale` n'est pas respectée. Quand une page répond 404 et que tu ne sais pas pourquoi, c'est la première commande à essayer.

**`debug:container`** parle des **services** : sans argument, il liste les services publics ; avec un nom, il détaille un service (sa classe, ses étiquettes, s'il est public) ; avec `--parameter=kernel.environment`, il affiche la valeur d'un paramètre, et `--parameters` les liste tous.

```bash
docker compose exec app php bin/console debug:container validator
docker compose exec app php bin/console debug:container --parameter=kernel.environment
```

**`debug:autowiring`** répond à : « quel type puis-je écrire dans l'argument d'un contrôleur pour recevoir tel service ? » Avec un mot, il filtre : `debug:autowiring Translator` renvoie `Symfony\Contracts\Translation\TranslatorInterface`, c'est-à-dire ce que tu as écrit au sujet précédent.

**`debug:translation`** est le détecteur de trous du sujet précédent : `debug:translation en` compare ce qui est utilisé dans le code à ce que contient le catalogue anglais, et signale ce qui manque.

N'utilise pas `debug:container --env-vars` devant quelqu'un : il affiche la valeur réelle des variables d'environnement, **secrets compris**.

## Une méthode, pas une liste d'outils

Face à un défaut, pose-toi toujours les mêmes questions, dans cet ordre :

1. **Quelle route** traite cette adresse ? (`router:match`)
2. **Quel contrôleur** s'exécute, avec **quelles valeurs** ? (`dump`, `dd`, profileur)
3. **Quel service** reçoit la demande, et est-ce le bon ? (`debug:container`, `debug:autowiring`)
4. **Que contient la vue** au moment de l'affichage ? (`dump` dans Twig)
5. Peux-tu en faire un **test** qui échoue, avant de corriger ? (le sujet précédent)

## À retenir

- Le mode de débogage est un interrupteur : actif en développement, coupé en production. Rien de ce qui sert à déboguer ne doit y rester.
- `dump()` montre, `dd()` montre et arrête ; en Twig, `dump()` n'existe qu'en mode de débogage.
- Le Web Profiler garde, pour chaque requête, ce que des collecteurs de données ont observé : routes, SQL, formulaires, validation, traductions, temps.
- `router:match` dit quelle route traite une adresse, `debug:router` liste les routes, `debug:container` et `debug:autowiring` parlent des services, `debug:translation` cherche les trous de traduction.
