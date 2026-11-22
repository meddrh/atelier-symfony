## À faire

Tu sais appeler un service externe par un client limité, garder une réponse en mémoire et répondre 304 quand rien n'a changé. À toi de l'appliquer à **l'historique des affectations**, en suivant ta fiche de préparation. Tes tests ne font **aucun accès réseau** : le client HTTP rend les réponses préparées par `App\Tests\Support\ReponsesSimulees` (le faux service de la démonstration), et une demande sans réponse préparée fait échouer le test. Il n'y a **pas de migration**.

1. **L'ETag de l'export CSV** (`/historique/export.csv`, sujet 13).
   - L'export porte un en-tête `ETag`, qui est l'**empreinte du contenu du fichier** : `md5` (ou `sha1`) du CSV généré, entre guillemets. Le CSV contient des noms de matériels et d'utilisateurs, qu'on peut modifier sans ajouter de ligne à l'historique : seule l'empreinte du **contenu** est toujours juste. Écris le CSV dans un flux temporaire (`php://temp`), calcule son empreinte (`hash_init()` et `hash_update_stream()`, sans le charger d'un bloc en mémoire), puis recopie ce flux dans la réponse.
   - Si la requête porte un `If-None-Match` identique, la réponse est un **304 sans corps** : rien du CSV n'est écrit. Si l'étiquette diffère, ou si quelque chose a changé dans le fichier (une ligne ajoutée, une date, le nom d'un matériel ou d'un utilisateur), tu rends le CSV complet avec la **nouvelle** étiquette.
   - L'export reste une `StreamedResponse` (le sujet 13 l'exige) et **privé** : jamais `public`. Teste le 304 sur ce type de réponse : le corps doit être vide.
   - Le contrôle des droits passe **avant** celui de l'étiquette : un technicien qui connaît l'ETag reçoit 403, pas 304.
2. **La commande `app:historique:archiver`** (`App\Command\ArchiverHistoriqueCommand`, `#[AsCommand]`).
   - Elle fabrique le **même CSV que le téléchargement** (le service `ExportHistoriqueInterface`, BOM compris) et l'envoie par un **`POST`** au client limité `api_archivage` (déjà configuré à la démonstration, argument `HttpClientInterface $apiArchivage`), avec `Content-Type: text/csv`.
   - **Succès** : un message qui dit « Historique archivé », code de sortie `0`.
   - **L'API répond hors de la famille 2xx** (par exemple 500) : un message clair qui contient le code et dit que l'historique n'a **pas** été archivé, code de sortie **non nul**.
   - **Délai dépassé, connexion impossible** (`TransportException`, `TimeoutException`) : un message clair, code de sortie non nul, **aucune trace brute** à l'écran (aucun nom d'exception, aucun chemin de fichier). Le détail va au journal.
   - Un cas d'échec, un test : la personne qui lance la commande ne doit lire qu'une phrase, jamais une trace.
3. **Le verrou.** La commande prend un verrou nommé **`archivage-historique`** (`LockFactory`, `createLock()`), **sans attendre** : si un autre archivage le tient, elle affiche un message qui contient « déjà en cours », sort avec un code **non nul** et **n'envoie rien** à l'API. Le verrou est **rendu** quoi qu'il arrive : après un succès comme après un échec (`finally`), et il a une durée de vie, pour le cas où le processus mourrait sans le rendre.

Contraintes :

- le stockage du verrou vient de `LOCK_DSN` : tu n'écris aucun nom de fichier. Par défaut, c'est `flock`, un fichier du système ; **le test de l'exercice** le remplace, pour ses besoins, par un stockage en mémoire (partagé entre la commande et le test) et garde une référence à chaque verrou créé : un verrou que ta commande ne rend pas elle-même reste donc pris jusqu'à la fin du test, quels que soient les arguments de `createLock()` ;
- c'est ton `finally` qui rend le verrou (`release()`) : le test le vérifie après un succès comme après un échec. Tu peux, comme à la démonstration 3, créer le verrou sans libération automatique (`createLock($nom, $duree, false)`) : le test n'en dépend pas ;
- les tests prennent **eux-mêmes** le verrou (même nom, même stockage) avant de lancer la commande, et attendent le refus ;
- un conseil : lance aussi tes tests des démonstrations (la fiche du fournisseur, le catalogue, l'inventaire sous verrou) et ceux des sujets précédents (`tests/Junior` compris), pour voir tout de suite si un changement les casse.

Deux conseils :

- **Le 304 économise le transfert, pas le calcul.** Pour savoir si le fichier a changé, il faut le générer : tu assumes ce coût pour ne pas renvoyer des mégaoctets déjà reçus. Une étiquette « bon marché » (un nombre de lignes, une dernière date) serait fausse dès qu'un nom change : un 304 périmé est pire qu'un téléchargement de trop.
- **Chaque échec est un cas.** Une réponse 500 et un délai dépassé ne lèvent pas la même chose : l'une est une réponse, l'autre une exception du client. Lis la documentation de `ExceptionInterface` et de `getStatusCode()` pour savoir lequel se produit quand.

## Squelette des fichiers

Ce sujet crée une classe, la commande d'archivage, et modifie une méthode de `HistoriqueController`. Le squelette ne te donne pas la solution : le CSV envoyé, les messages selon la réponse de l'API, le contrôle d'un seul archivage à la fois et le calcul de l'ETag restent à écrire. La configuration des clients et du verrou existe déjà (démonstrations) et il n'y a pas de gabarit : rien d'autre n'est à créer.

La commande. `#[AsCommand]` lui donne son nom ; la description est à toi. Le constructeur aussi : la consigne nomme ce dont elle a besoin (le service d'export, le client limité, un moyen de ne lancer qu'un archivage à la fois), à toi de décider comment le recevoir, sans oublier le constructeur du parent.

```php
<?php
// src/Command/ArchiverHistoriqueCommand.php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:historique:archiver')]
class ArchiverHistoriqueCommand extends Command
{
    // À toi : le constructeur qui reçoit ce dont la commande a besoin, sans oublier celui du parent.

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // À toi : fabriquer le CSV de l'historique, l'envoyer à l'API d'archivage et répondre selon ce que celle-ci rend ;
        // un second lancement simultané est refusé (la consigne dit comment le reconnaître et quoi afficher).

        return Command::SUCCESS; // provisoire : à remplacer par le code que demande la consigne
    }
}
```

`HistoriqueController` existe déjà, avec ton code du sujet 13. **Ne le remplace pas.** Sa méthode `export()` garde sa route, mais reçoit maintenant la requête (pour lire `If-None-Match`) : ce bloc montre sa nouvelle signature. Garde ton code et adapte-le (la signature, puis le corps qui calcule l'empreinte et répond 304).

```php
// à modifier dans src/Controller/HistoriqueController.php

    // Ce qui change : reçoit maintenant la requête. Garde ton code et adapte-le.
    #[Route('/historique/export.csv', name: 'historique_export', methods: ['GET'])]
    public function export(Request $requete, ExportHistoriqueInterface $export, HistoriqueAffectationRepository $historiqueRepository): Response
    {
        // À toi : calculer l'empreinte du contenu, la donner en ETag, répondre 304 sans corps si le navigateur la connaît déjà.
    }
```

N'oublie pas le `use Symfony\Component\HttpFoundation\Request;` en haut de `HistoriqueController`. Le dossier `src/Command/` donne l'espace de noms `App\Command` : c'est la règle PSR-4.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Un succès affiche un message et envoie le CSV (en-tête, ligne de l'historique, BOM) par un `POST` au client de l'archivage.
- Un second lancement simultané est refusé : message « déjà en cours », code non nul, rien d'envoyé.
- Le verrou est rendu après un succès.
- Une API en panne (500) donne un message clair et un code non nul, sans trace brute.
- Un délai dépassé donne un message clair et un code non nul, sans trace brute.
- Une connexion impossible donne un message clair et un code non nul, sans trace brute.
- Le verrou est rendu après un échec.
- Un historique vide envoie quand même l'en-tête.
- La fiche du fournisseur affiche la fin de garantie lue sur l'API, par le client limité.
- La date est gardée une heure dans le pool de cache : une seule demande à l'API pour deux affichages.
- Chaque fournisseur a sa propre date en mémoire.
- Une API en panne (500) donne un message à l'écran et pas une erreur 500.
- Un délai dépassé donne un message à l'écran et pas une erreur 500.
- Une connexion impossible donne un message à l'écran et pas une erreur 500.
- Une réponse illisible donne un message à l'écran et pas une erreur 500.
- Un échec n'est pas gardé en mémoire : la page suivante réessaie.
- Le catalogue est public, avec `Cache-Control: public, max-age`, un ETag et sans cookie.
- Le catalogue répond 304 sans corps quand l'ETag correspond.
- Le catalogue change d'ETag quand un matériel change.
- Un ETag qui ne correspond pas donne la page complète.
- Une page réservée à un compte n'est jamais en cache public.
- L'export porte un ETag, reste une réponse en flux et n'est jamais en cache public.
- L'export répond 304 sans corps quand l'ETag correspond.
- L'ETag de l'export change quand une ligne est ajoutée.
- L'ETag de l'export change quand une date de l'historique change.
- L'ETag de l'export change quand un matériel ou un utilisateur lu par le CSV est renommé : l'export rejoué avec l'ancienne étiquette rend un 200 avec le nouveau nom, jamais un 304 périmé.
- L'ETag est l'empreinte (`md5` ou `sha1`) du contenu du fichier, entre guillemets.
- Un ETag qui ne correspond pas donne le CSV complet.
- Un historique vide a un ETag stable et un 304. Quand rien n'a changé, le 304 est conservé.
- Le refus des droits passe avant le contrôle de l'ETag : 403 pour un technicien et un utilisateur simple.
