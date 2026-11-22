## Pourquoi cette lecture

Tu as utilisé beaucoup de composants sans les nommer : l'un charge ton fichier de variables, un autre lance `bin/phpunit`. Cette lecture les passe en revue, **un par un, avec un exemple court**. Aucun exercice ne la demande, mais l'examen et la documentation les supposent connus. Les exemples sont écrits pour Symfony 5.4.

## Dotenv : charger les variables d'environnement

Le composant `symfony/dotenv` lit des fichiers `.env` et les met à disposition de l'application. Les fichiers se superposent, du moins prioritaire au plus prioritaire :

1. `.env` : les valeurs par défaut, versionnées ;
2. `.env.local` : les valeurs de **ta machine**, jamais versionnées (ignoré en environnement de test) ;
3. `.env.<environnement>` (par exemple `.env.test`), puis `.env.<environnement>.local`.

Une variable déjà définie par le **vrai** environnement (celui du système, ou de Docker) **n'est jamais écrasée** par un fichier. C'est ce qui permet de régler la production sans fichier.

```php
use Symfony\Component\Dotenv\Dotenv;

// Ce que fait le chargeur de l'application : lit .env puis les fichiers de l'environnement courant.
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
```

En production, `composer dump-env prod` écrit un seul fichier `.env.local.php` : rien n'est lu ni analysé à chaque requête. Les **secrets** (mots de passe, clés) ne vont jamais dans `.env` : ce fichier est versionné.

## Process : lancer un programme externe

```php
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

$processus = new Process(['git', 'log', '-1', '--format=%H']);
$processus->setTimeout(10);          // 60 secondes par défaut
$processus->run();

if (!$processus->isSuccessful()) {
    throw new ProcessFailedException($processus);
}
echo $processus->getOutput();
```

- On passe la commande **en tableau** : chaque morceau est un argument, rien n'est interprété par un interpréteur de commandes. C'est la protection contre l'**injection de commande** quand un morceau vient d'un utilisateur.
- `mustRun()` fait `run()` puis lève `ProcessFailedException` si le code de sortie n'est pas `0`.
- `Process::fromShellCommandline('… "$FICHIER"')` accepte une vraie ligne de commande, avec des variables à fournir (`->run(null, ['FICHIER' => $chemin])`) : à réserver aux cas où on ne peut pas faire autrement.

## Filesystem : les opérations sur les fichiers, sans surprise

```php
use Symfony\Component\Filesystem\Filesystem;

$fs = new Filesystem();
$fs->mkdir('var/exports');                       // crée aussi les dossiers parents
$fs->dumpFile('var/exports/a.csv', $contenu);    // écriture atomique : un lecteur ne voit jamais un fichier à moitié écrit
$fs->copy('a.csv', 'b.csv');
$fs->mirror('source/', 'destination/');          // copie récursive
$fs->remove(['var/exports/a.csv', 'var/tmp']);   // fichiers ou dossiers, sans erreur s'ils n'existent pas
```

Les fonctions de PHP (`mkdir`, `file_put_contents`, `copy`) rendent `false` en cas d'échec. Celles du composant lèvent une `IOException` : une erreur ne passe pas inaperçue. L'écriture atomique de `dumpFile()` écrit dans un fichier temporaire, puis le **renomme** : le même principe que le verrou de l'archivage, pour ne jamais laisser un état à moitié fait.

## Finder : chercher des fichiers

```php
use Symfony\Component\Finder\Finder;

$fichiers = (new Finder())
    ->files()
    ->in('var/exports')
    ->name('*.csv')
    ->size('< 1M')
    ->date('since yesterday')
    ->sortByName();

foreach ($fichiers as $fichier) {            // un SplFileInfo par fichier
    echo $fichier->getRelativePathname(), "\n";
}
```

Chaque filtre se **compose** avec les autres : `->name()`, `->notName()`, `->path()`, `->depth('< 2')`, `->contains('motif')`. Le résultat est un itérable, lu au fur et à mesure : un dossier de dix mille fichiers ne prend pas dix mille objets en mémoire d'un coup.

## Runtime : ce qui démarre l'application

Ouvre `public/index.php` et `bin/console` : tous deux commencent par `require_once … '/vendor/autoload_runtime.php'` et **rendent une fonction** qui reçoit le contexte (`$context['APP_ENV']`, `$context['APP_DEBUG']`) et renvoie le noyau (ou l'application de la console).

```php
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
```

Le composant `symfony/runtime` est celui qui **exécute** ce que la fonction rend : il sait traiter un noyau HTTP, une application de console, une réponse. Le même fichier fonctionne donc avec PHP-FPM, un serveur en processus long ou une fonction sans serveur : seul le « runtime » change, pas ton code.

## Extensions PHP : ce que Symfony attend de PHP

Une **extension** ajoute des fonctions écrites en C à PHP. Ce projet en exige certaines (ouvre `composer.json`, section `require`) : `ext-ctype`, `ext-iconv`, `ext-intl`. Les autres que tu croises souvent : `mbstring` (chaînes multi-octets), `xml`, `pdo_sqlite` ou `pdo_mysql` (la base), `opcache` (code compilé gardé en mémoire), `apcu` (cache en mémoire partagée).

```bash
php -m                                  # les extensions chargées
composer check-platform-reqs            # celles que les paquets exigent : manque-t-il quelque chose ?
```

```php
if (!extension_loaded('intl')) { /* le message d'erreur le plus utile est celui qu'on écrit ici */ }
```

Déclarer `ext-intl` dans `composer.json` fait échouer l'installation **tout de suite**, sur une machine qui ne l'a pas, plutôt qu'une erreur obscure à l'exécution.

## Le profileur et les collecteurs de données

En développement, Symfony enregistre pour chaque requête un **profil** : durée, requêtes SQL, événements, e-mails, messages. Chaque rubrique est produite par un **collecteur de données** (`DataCollectorInterface`). Le profileur se règle dans `config/packages/framework.yaml` (`framework.profiler`), et se désactive ou s'active selon l'environnement.

Un test peut lire le profil d'une requête (si le profileur est activé pour l'environnement de test) :

```php
$client->enableProfiler();
$client->request('GET', '/materiels');

$profil = $client->getProfile();
$sql = $profil->getCollector('db');                 // le collecteur de Doctrine
self::assertLessThan(5, $sql->getQueryCount());     // pas plus de 5 requêtes SQL pour cette page
```

Écrire son propre collecteur, c'est étendre `DataCollector` :

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class ArchivageCollector extends DataCollector
{
    public function collect(Request $requete, Response $reponse, \Throwable $exception = null): void
    {
        $this->data['archives'] = 3;   // ce qu'on veut retrouver dans le profil
    }

    public function getName(): string { return 'app.archivage'; }

    public function reset(): void { $this->data = []; }
}
```

Le collecteur est enregistré avec l'étiquette `data_collector` (un modèle Twig le dessine dans la barre de débogage). Tu l'écriras rarement ; tu liras souvent ceux des autres.

## La passerelle PHPUnit de Symfony

Ce projet lance ses tests avec `bin/phpunit`, pas avec `vendor/bin/phpunit` : ce script vient du paquet `symfony/phpunit-bridge`. Il installe **la version de PHPUnit demandée** (ici `SYMFONY_PHPUNIT_VERSION` dans `phpunit.xml.dist`) dans un dossier à part, pour ne pas la mélanger avec les dépendances de l'application. Il apporte aussi :

- la **gestion des dépréciations** : `SYMFONY_DEPRECATIONS_HELPER` dit combien on en tolère avant d'échouer (ce projet en tolère beaucoup, pour la formation) ;
- des **horloges simulées** (`ClockMock`, groupe `time-sensitive`) pour tester du code qui dépend du temps sans attendre ;
- `ExpectDeprecationTrait`, pour tester qu'une dépréciation est bien émise.

## Les inclusions ESI : une page, plusieurs durées de cache

Le catalogue de la démonstration est identique pour tous : on peut le garder cinq minutes. Mais une page réelle mélange souvent une partie **stable** et une partie **vivante** (un panier, un bandeau d'actualité). Les inclusions **ESI** (Edge Side Includes) permettent de les cacher **séparément** : la page contient une balise qui dit « un relais, va chercher ce morceau à telle adresse », et chaque morceau a **sa** durée.

```yaml
# config/packages/framework.yaml
framework:
    esi: true
    fragments: { path: /_fragment }
```

```twig
{# Le morceau est rendu par un autre contrôleur, avec son propre en-tête de cache #}
{{ render_esi(controller('App\\Controller\\BandeauController::actualites')) }}
```

Il faut un **relais capable d'ESI** devant l'application (Varnish, ou le `HttpCache` du composant HttpKernel). Sans relais, Symfony affiche simplement le morceau **dans** la page : rien ne casse, mais rien n'est mis en cache séparément.

## Le déploiement : la liste de ce qu'on vérifie

Aucune commande ne « déploie » à elle seule. Voici l'ordre habituel sur un serveur de production :

1. **Environnement** : `APP_ENV=prod` et `APP_DEBUG=0`, dans le vrai environnement (ou `composer dump-env prod`) ; jamais de secret dans un fichier versionné.
2. **Dépendances** : `composer install --no-dev --optimize-autoloader` (ni outils de test, ni débogage).
3. **Base de données** : `bin/console doctrine:migrations:migrate --no-interaction`, puis `doctrine:schema:validate`.
4. **Cache** : `bin/console cache:clear` puis `cache:warmup`, **avec le même utilisateur** que celui qui servira le site (sinon le serveur ne pourra pas écrire dans `var/`).
5. **Processus qui doivent tourner** : le serveur web, et `bin/console messenger:consume async` sous un superviseur qui le relance s'il s'arrête (sujet 14). Après un déploiement, `messenger:stop-workers` le fait redémarrer sur le nouveau code.
6. **Vérifier** : une page, la console (`bin/console about`), les journaux de `var/log`.
