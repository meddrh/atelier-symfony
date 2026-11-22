## Ce que nous construisons

Jusqu'ici, ton code **appelle** du code : un contrôleur appelle un service, qui appelle un dépôt. Les **événements** inversent le sens : le noyau **annonce** ce qu'il est en train de faire, et ton code **se branche** sur ces annonces, sans que personne ne l'appelle explicitement.

Pendant une requête, le noyau annonce, dans cet ordre :

| Événement | Quand | Exemple d'usage |
|---|---|---|
| `kernel.request` | dès la requête reçue, avant le routeur | refuser, rediriger, répondre tout de suite |
| `kernel.controller` | le contrôleur est trouvé, pas encore appelé | changer de contrôleur |
| `kernel.response` | la réponse existe, pas encore envoyée | ajouter un en-tête |
| `kernel.terminate` | la réponse est partie | un travail long, sans faire attendre |
| `kernel.exception` | une exception n'a pas été rattrapée | la transformer en page |

Celui qui annonce s'appelle le **répartiteur d'événements** (`EventDispatcher`) : il tient la liste de ceux qui écoutent chaque événement, et les appelle dans l'ordre de leur **priorité** (la plus haute d'abord).

Cette première partie branche deux comportements sur le noyau :

- un **mode maintenance**, qui répond 503 à toute requête quand un paramètre le demande ;
- un en-tête `X-Atelier` ajouté à **chaque** réponse.

Il y a deux manières d'écouter un événement. Tu vas les voir toutes les deux :

- un **abonné** (`EventSubscriberInterface`) : une classe qui **déclare** ses événements dans une méthode statique `getSubscribedEvents()` ;
- un **écouteur** avec l'attribut `#[AsEventListener]` : une méthode (ou une classe) qui **annonce** l'événement qu'elle écoute, juste au-dessus d'elle.

Les deux font la même chose : Symfony les repère tout seul (`autoconfigure`), aucune configuration d'événement n'est à écrire.

---

## Étape 1 : le mode maintenance, un abonné

Crée `src/EventSubscriber/MaintenanceSubscriber.php` :

```php
<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Le mode maintenance : quand le paramètre `app.maintenance` est vrai, toute requête reçoit 503 (« service
 * indisponible »), sans que le routeur, le pare-feu ni un contrôleur n'aient à s'exécuter.
 */
class MaintenanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        private bool $maintenance,
    ) {
    }

    /** Priorité 100 : avant le routeur (32) et le pare-feu (8), pour que rien d'autre ne s'exécute. */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['surRequete', 100]];
    }

    public function surRequete(RequestEvent $evenement): void
    {
        if (!$this->maintenance || !$evenement->isMainRequest()) {
            return;
        }

        // Les outils de développement (barre de débogage, profileur) restent joignables.
        if (str_starts_with($evenement->getRequest()->getPathInfo(), '/_')) {
            return;
        }

        // Renseigner une réponse arrête la suite : le noyau passe directement à l'événement « réponse ».
        $evenement->setResponse(new Response(
            $this->twig->render('erreur/maintenance.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE,
            ['Retry-After' => '3600'],
        ));
    }
}
```

- `getSubscribedEvents()` associe l'événement `kernel.request` à la méthode `surRequete`, avec la **priorité 100**. Plus haut que le routeur (32) et que le pare-feu (8) : en maintenance, rien d'autre ne doit s'exécuter ;
- `isMainRequest()` écarte les **sous-requêtes** (un contrôleur interne, par exemple) : seule la requête du visiteur compte ;
- renseigner une réponse avec `setResponse()` **arrête** la suite : le noyau saute le routeur et le contrôleur, et passe directement à `kernel.response` ;
- le chemin `/_wdt` et `/_profiler` (outils de développement) restent ouverts ;
- le code `503` (« service indisponible ») et l'en-tête `Retry-After` disent aux robots de revenir plus tard.

Crée le gabarit `templates/erreur/maintenance.html.twig`. Il n'étend **pas** `base.html.twig` : à cette priorité, ni la session ni l'utilisateur ne sont chargés.

```twig
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Maintenance en cours</title>
    </head>
    <body>
        <h1>Maintenance en cours</h1>
        <p>Le parc informatique est momentanément fermé pour une opération de maintenance. Reviens dans une heure.</p>
    </body>
</html>
```

---

## Étape 2 : le paramètre, et pourquoi il est désactivé par défaut

Dans `config/services.yaml`, ajoute ces paramètres **sous** `parameters:` :

```yaml
    # Mode maintenance : APP_MAINTENANCE=1 (variable d'environnement) fait répondre 503 à toute requête.
    # Désactivé par défaut : sans cette valeur, la variable absente ferait échouer le démarrage.
    env(APP_MAINTENANCE): '0'
    app.maintenance: '%env(bool:APP_MAINTENANCE)%'
    # Le journal de l'atelier : un fichier, hors de public/.
    app.fichier_journal: '%kernel.logs_dir%/atelier.log'
```

Et ajoute ce service, **sous** `services:` (avant `App\Service\RangeurDeFichiers`) :

```yaml
    App\EventSubscriber\MaintenanceSubscriber:
        arguments:
            $maintenance: '%app.maintenance%'
```

- `%env(bool:APP_MAINTENANCE)%` lit la **variable d'environnement** `APP_MAINTENANCE` au moment de la requête et la convertit en booléen ;
- la ligne `env(APP_MAINTENANCE): '0'` donne la **valeur par défaut**. **Sans elle**, une variable absente ferait échouer toute l'application : ce serait le mode maintenance **actif par défaut** par accident, ou pire, un plantage général ;
- le service reçoit le paramètre par son **nom d'argument** (`$maintenance`).

---

## Étape 3 : l'en-tête sur chaque réponse, un écouteur

Crée `src/EventListener/EnTeteAtelierListener.php` :

```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Ajoute l'en-tête X-Atelier à chaque réponse de la requête principale. */
class EnTeteAtelierListener
{
    public const NOM = 'X-Atelier';
    public const VALEUR = 'parc-informatique';

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function ajouterLEnTete(ResponseEvent $evenement): void
    {
        if (!$evenement->isMainRequest()) {
            return;
        }

        $evenement->getResponse()->headers->set(self::NOM, self::VALEUR);
    }
}
```

- `#[AsEventListener(event: KernelEvents::RESPONSE)]` placé sur la méthode dit **quel événement** elle écoute ;
- la méthode reçoit l'objet **événement** (`ResponseEvent`) : il donne accès à la réponse, qu'on peut **modifier** avant son envoi ;
- il n'y a **pas** de valeur de retour : on agit sur l'événement.

---

## Étape 4 : essayer

1. Ouvre une page de ton application et regarde les en-têtes de la réponse (outils du navigateur, onglet « Réseau », ou `curl -i http://localhost:8000/connexion`) : `X-Atelier: parc-informatique` est présent.
2. Crée (ou complète) le fichier `.env.local` à la racine du projet avec la ligne `APP_MAINTENANCE=1`. Recharge n'importe quelle page : « Maintenance en cours », code **503**. Même `/connexion`, même sans être connecté.
3. **Retire la ligne** (ou mets `0`) : l'application répond de nouveau. Le mode maintenance n'est jamais actif par défaut.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée l'abonné et l'écouteur, le gabarit, les paramètres et le service, puis fais les trois vérifications de l'étape 4. Clique ensuite sur « Terminé ».
