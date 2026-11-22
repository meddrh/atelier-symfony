## Ce que nous construisons

Cette seconde partie écoute deux autres sortes d'annonces :

- `kernel.exception` : une exception n'a été rattrapée par personne. Au lieu de la page d'erreur habituelle, une **page dédiée** pour une exception **métier** de ton application ;
- `console.terminate` : une commande de la console vient de finir. Sa **durée** est écrite dans un **journal**.

---

## Étape 1 : une exception métier

Une exception **technique** (une base injoignable, une erreur de code) dit « le serveur a un problème ». Une exception **métier** dit « ce que tu demandes n'a pas de sens pour le parc » : un matériel au rebut ne peut plus être affecté. La première donne 500, la seconde mérite une réponse qui explique.

Crée `src/Exception/MaterielIndisponible.php` :

```php
<?php

namespace App\Exception;

use App\Entity\Materiel;

/**
 * Une exception « métier » : elle dit quelque chose du parc (ce matériel ne peut pas servir), pas du serveur.
 * Un écouteur de l'événement `kernel.exception` la transforme en page dédiée (MaterielIndisponibleListener).
 */
class MaterielIndisponible extends \RuntimeException
{
    public function __construct(private Materiel $materiel, string $raison)
    {
        parent::__construct(sprintf('Le matériel « %s » est indisponible : %s', $materiel->getNom(), $raison));
    }

    public function getMateriel(): Materiel
    {
        return $this->materiel;
    }
}
```

L'exception **porte** le matériel : l'écouteur en aura besoin pour afficher la page.

Dans `src/Controller/MaterielController.php`, ajoute le `use`, puis modifie `modifier()` :

```php
use App\Exception\MaterielIndisponible;
```

Juste **avant** la création du formulaire :

```php
        // L'affectation d'avant la lecture du formulaire : après handleRequest(), l'objet porte déjà la nouvelle.
        $affecteAvant = $materiel->getUtilisateur();
```

Et, dans le `if ($formulaire->isSubmitted() && $formulaire->isValid())`, **tout en haut** :

```php
            // Un matériel au rebut n'est plus affecté : l'exception est transformée en page dédiée par un écouteur.
            if ($materiel->estAuRebut() && $materiel->getUtilisateur()?->getId() !== $affecteAvant?->getId()) {
                throw new MaterielIndisponible($materiel, "il est au rebut, on ne l'affecte plus.");
            }
```

Le contrôleur **lève** l'exception sans savoir comment elle sera présentée. L'`affecteAvant` est lu **avant** `handleRequest()` : après la lecture du formulaire, l'objet porte déjà la nouvelle valeur, et on ne pourrait plus savoir si elle a changé.

---

## Étape 2 : la page dédiée, un écouteur de `kernel.exception`

Crée `src/EventListener/MaterielIndisponibleListener.php` :

```php
<?php

namespace App\EventListener;

use App\Exception\MaterielIndisponible;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Une page dédiée (409 « conflit ») pour l'exception métier MaterielIndisponible. Toute autre exception
 * n'est pas la nôtre : on n'y touche pas, Symfony affiche sa page d'erreur habituelle.
 */
class MaterielIndisponibleListener
{
    public function __construct(private Environment $twig)
    {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function surException(ExceptionEvent $evenement): void
    {
        $exception = $evenement->getThrowable();
        if (!$exception instanceof MaterielIndisponible) {
            return;
        }

        $evenement->setResponse(new Response(
            $this->twig->render('erreur/materiel-indisponible.html.twig', [
                'materiel' => $exception->getMateriel(),
                'message' => $exception->getMessage(),
            ]),
            Response::HTTP_CONFLICT,
        ));
    }
}
```

- l'événement porte l'exception (`getThrowable()`). Si ce **n'est pas** une `MaterielIndisponible`, l'écouteur **ne fait rien** : Symfony affichera sa page habituelle, et les autres écouteurs ont leur chance ;
- sinon, `setResponse()` donne la réponse : le code **409** (« conflit » : la demande est bien comprise, mais l'état du matériel l'empêche).

Crée `templates/erreur/materiel-indisponible.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Matériel indisponible{% endblock %}

{% block body %}
    <h1>Matériel indisponible</h1>

    <p class="erreur">{{ message }}</p>

    <p><a href="{{ path('materiel_fiche', {id: materiel.id}) }}">Retour à la fiche de {{ materiel.nom }}</a></p>
{% endblock %}
```

---

## Étape 3 : la durée d'une commande, dans le journal

Le **journal** est un fichier où l'application note ce qui s'est passé. Symfony fournit une classe `Logger` qui écrit dans un fichier ; tu vas la déclarer comme **un service à toi**, nommé `app.journal`.

Dans `config/services.yaml`, ajoute ce bloc sous `services:` (avant `App\Service\RangeurDeFichiers`) :

```yaml
    # Un journal à nous : le service `Logger` de Symfony, qui écrit dans un fichier (niveau « info » et au-dessus).
    app.journal:
        class: Symfony\Component\HttpKernel\Log\Logger
        arguments:
            $minLevel: info
            $output: '%app.fichier_journal%'

    App\EventSubscriber\DureeCommandeSubscriber:
        arguments:
            $journal: '@app.journal'
```

Et, dans la section `when@test:` du même fichier, sous `parameters:` :

```yaml
        # En test, le journal va dans le cache du noyau : un fichier jetable.
        app.fichier_journal: '%kernel.cache_dir%/atelier.log'
```

(En test, le fichier va dans le cache : un dossier jetable.)

Crée maintenant `src/EventSubscriber/DureeCommandeSubscriber.php` :

```php
<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Écrit dans le journal la durée de chaque commande de l'application (`app:…`), import compris.
 *
 * Deux événements de la console : `console.command` (avant l'exécution : on note l'heure) et
 * `console.terminate` (après : on calcule la durée et on l'écrit).
 */
class DureeCommandeSubscriber implements EventSubscriberInterface
{
    /** @var array<string, int> heure de départ (en nanosecondes) de chaque commande en cours, par nom */
    private array $departs = [];

    public function __construct(private LoggerInterface $journal)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'auDepart',
            ConsoleEvents::TERMINATE => 'alArrivee',
        ];
    }

    public function auDepart(ConsoleCommandEvent $evenement): void
    {
        $nom = $evenement->getCommand()?->getName();
        if (null !== $nom && str_starts_with($nom, 'app:')) {
            $this->departs[$nom] = hrtime(true);
        }
    }

    public function alArrivee(ConsoleTerminateEvent $evenement): void
    {
        $nom = $evenement->getCommand()?->getName();
        if (null === $nom || !isset($this->departs[$nom])) {
            return;
        }

        $duree = (hrtime(true) - $this->departs[$nom]) / 1_000_000;
        unset($this->departs[$nom]);

        $this->journal->info('La commande {commande} a duré {duree} ms (code de sortie {code}).', [
            'commande' => $nom,
            'duree' => number_format($duree, 1, '.', ''),
            'code' => $evenement->getExitCode(),
        ]);
    }
}
```

- **deux** événements de la console : `console.command` (la commande va s'exécuter : on note l'heure) et `console.terminate` (elle a fini : on calcule la durée et on l'écrit) ;
- `hrtime(true)` donne un temps en nanosecondes, adapté pour **mesurer une durée** (pas pour une date) ;
- on ne s'intéresse qu'aux commandes de ton application (`app:…`), import compris ;
- le journal reçoit un **message** avec des **marques** `{commande}`, `{duree}`, `{code}` et un tableau de valeurs : jamais de texte assemblé à la main dans le message ;
- l'abonné reçoit le journal par son **nom d'argument** (`$journal`), comme le paramètre de la maintenance.

---

## Étape 4 : essayer

1. Mets un matériel au rebut (le bouton « Mettre au rebut » de sa fiche). Ouvre ensuite sa page « Modifier », choisis un utilisateur dans « Affecté à » et enregistre : la page **« Matériel indisponible »** s'affiche, avec le code **409**, et rien n'est enregistré. Si tu ne touches pas à l'affectation, la modification passe.
2. Lance une importation :

```bash
docker compose exec app php bin/console app:materiel:importer tests/Exercices/Console/materiels-valides.csv
```

3. Lis le journal (`var/log/atelier.log`) : une ligne indique la commande, sa durée en millisecondes et son code de sortie.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée l'exception, l'écouteur et son gabarit, l'abonné de la console, le journal, et modifie `modifier()`. Fais ensuite les vérifications de l'étape 4. Clique sur « Terminé ».
