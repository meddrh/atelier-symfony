## Pourquoi cette lecture

Tu viens de voir comment Symfony construit et compose ses services. Avant de continuer, voici le **cadre** dans lequel cette mécanique vit : les conventions que suit l'écosystème, et la façon dont un paquet expose sa configuration. C'est une lecture seulement : aucun exercice ne la demande, mais le sujet suivant et la documentation officielle la supposent connue.

## Les ponts vers les bibliothèques tierces

Symfony n'impose pas sa propre version de tout. Pour parler à une bibliothèque extérieure, il fournit un **pont** (`bridge`) : un petit paquet qui adapte la bibliothèque à ses interfaces.

- `symfony/doctrine-bridge` relie Doctrine au validateur (la contrainte `UniqueEntity` que tu as utilisée vient de là), à la sécurité et aux formulaires (`EntityType`) ;
- `symfony/twig-bridge` relie Twig aux formulaires et à la traduction ;
- `symfony/monolog-bridge` relie Monolog à la console et au profileur.

Le pont est la **colle** : ni la bibliothèque ni le composant ne se connaissent, et on peut remplacer l'une sans toucher à l'autre. Tu en utilises depuis le début sans l'avoir demandé.

## Les bonnes pratiques

La documentation officielle publie un guide de **bonnes pratiques**. Les plus utiles à ce stade :

- un seul dossier `src/` rangé par **rôle** (`Controller`, `Entity`, `Service`…), pas par fonctionnalité technique ;
- les **paramètres** propres à l'application dans `config/services.yaml`, les secrets dans les variables d'environnement ;
- **autowiring** et `autoconfigure` partout, et une configuration explicite **seulement** quand elle apporte quelque chose (un argument, une décoration, une fabrique) ;
- les **contrôleurs minces** : ils lisent la requête, appellent un service, rendent une réponse ; la logique vit ailleurs ;
- des **gabarits** pour l'affichage uniquement, des **services** pour le reste.

Tu as appliqué la plupart de ces règles sans qu'on te les dicte : par exemple, l'historique est écrit par un abonné, pas par le contrôleur.

## Les recommandations PSR

Les **PSR** (`PHP Standards Recommendations`) sont des normes communes aux projets PHP. Symfony en suit plusieurs, ce qui rend ses composants interchangeables avec d'autres :

| Norme | Sujet |
|---|---|
| PSR-1 et PSR-12 | style du code (le formatage que tu vois dans le projet) |
| PSR-3 | l'interface `LoggerInterface` du journal (celle que ton décorateur demande) |
| PSR-4 | le **chargement automatique** : l'espace de noms `App\` correspond au dossier `src/` |
| PSR-6 et PSR-16 | les interfaces de **cache** |
| PSR-7, PSR-17, PSR-18 | requêtes et réponses HTTP, fabriques, client HTTP |

Un exemple concret : ton décorateur d'export reçoit un `Psr\Log\LoggerInterface`, pas une classe Symfony. Le service du journal pourrait être remplacé par n'importe quelle bibliothèque qui respecte la PSR-3.

## Les conventions de nommage

Symfony publie ses conventions pour son propre code ; elles sont un bon guide pour le tien :

- les classes en `UpperCamelCase`, les méthodes et variables en `camelCase`, les paramètres de configuration en `snake_case` ;
- les **interfaces** portent le suffixe `Interface`, les classes abstraites le préfixe `Abstract`, les traits le suffixe `Trait`, les exceptions le suffixe `Exception` ;
- les identifiants de services de l'application : **le nom complet de la classe**, ou un nom court en minuscules séparé par des points (`app.journal`) pour un service technique.

Dans ce cours, le nom des classes est en français pour l'exercice, ce qui est un choix pédagogique : dans un projet réel, on suit la langue de l'équipe, sans mélange.

## La configuration sémantique d'un paquet

Quand tu écris `framework:` ou `security:` dans `config/packages/`, tu ne remplis pas un simple tableau : chaque paquet déclare, dans une **extension** de conteneur et une classe `Configuration`, ce qu'il accepte.

- la classe `Configuration` décrit un **arbre** : les clés, leur type, leurs valeurs par défaut, ce qui est obligatoire (`TreeBuilder`, `ArrayNodeDefinition`) ;
- l'**extension** lit la configuration validée et crée les définitions de services correspondantes ;
- c'est pour cela qu'une faute de frappe dans une clé donne un message d'erreur précis, et que `bin/console config:dump-reference security` liste tout ce qui est permis.

Tu n'écriras pas d'extension dans ce parcours : elle sert aux **paquets réutilisables**. Mais tu sais maintenant d'où viennent les clés que tu manipules, et tu peux lire le code d'un paquet pour savoir ce qu'une clé fait vraiment.

```bash
docker compose exec app php bin/console config:dump-reference framework
docker compose exec app php bin/console debug:config security
```

La première commande affiche **tout ce qu'un paquet accepte**, la seconde **ce qui est réellement configuré** dans ton projet, valeurs par défaut comprises.
