## Pourquoi ce rappel

Avant d'écrire tes premières pages, il faut savoir **où ranger chaque fichier** et **ce qui se passe entre l'arrivée d'une requête et l'envoi de la réponse**. Sans cette carte, on copie du code sans comprendre où il vit.

## Symfony est un ensemble de composants

Symfony n'est pas un bloc unique : c'est une collection de **composants** indépendants (routage, formulaires, validation, console…), que l'on installe avec Composer selon les besoins. Le projet que tu utilises en installe quelques-uns seulement. Chaque composant se travaille et se teste seul : c'est pour cela que d'autres projets PHP en réutilisent beaucoup.

## Symfony Flex : l'installation qui se configure toute seule

Quand tu ajoutes un composant avec `composer require`, **Flex** applique une « recette » : il crée les fichiers de configuration nécessaires, ajoute les variables d'environnement et enregistre le composant. C'est pourquoi un dossier `config/packages/` se remplit tout seul.

## Où est rangé quoi

| Dossier | Contenu |
|---|---|
| `src/` | Ton code PHP : contrôleurs (`src/Controller`), entités (`src/Entity`), dépôts (`src/Repository`)… |
| `templates/` | Les gabarits Twig, c'est-à-dire le HTML. |
| `config/` | La configuration : routes, services, composants. |
| `public/` | Le seul dossier accessible depuis le navigateur ; il contient `index.php`. |
| `migrations/` | Les migrations de la base de données. |
| `tests/` | Les tests automatisés. |
| `var/` | Fichiers produits pendant l'exécution (cache, journaux, base SQLite locale). On ne le range pas dans Git. |
| `vendor/` | Les bibliothèques installées par Composer. On n'y touche jamais. |

La règle de base : **tu ne modifies que `src/`, `templates/`, `config/`, `migrations/` et `tests/`**.

## Le chemin d'une requête

1. Le navigateur envoie une requête au serveur web.
2. Le serveur lance toujours le même fichier : `public/index.php`. On l'appelle le **contrôleur frontal**.
3. Ce fichier démarre le **noyau** de Symfony (la classe `Kernel`).
4. Le noyau transforme la requête en objet et cherche quelle **route** correspond à l'adresse.
5. La route désigne un **contrôleur** : une méthode de ta classe PHP.
6. Le contrôleur travaille (lit des données, prépare des variables) et **renvoie toujours une réponse**.
7. Le noyau envoie cette réponse au navigateur.

Si aucune route ne correspond à l'adresse, le noyau fabrique lui-même une réponse `404`.

## À retenir

- Une page, c'est **une route + une méthode de contrôleur + (souvent) un gabarit**.
- Les contrôleurs ne contiennent pas de HTML : ils préparent des données.
- Pour savoir où mettre un fichier, regarde la liste ci-dessus avant d'inventer un dossier.

Quand tu peux redessiner de mémoire les sept étapes du chemin d'une requête, clique sur « Terminé ».
