## Ce que nous construisons

La fiche d'un fournisseur doit afficher la **date de fin de garantie**. Cette date n'est pas dans notre base : elle vit sur **l'API du fournisseur**, un service externe. Deux problèmes se posent tout de suite :

- un service externe est **lent** et peut tomber en panne : la fiche ne doit ni attendre indéfiniment, ni afficher une erreur 500 pour autant ;
- on ne va pas l'interroger à **chaque** affichage de la page : la date change rarement, on la garde en mémoire **une heure**.

Et pour les tests, une règle : **aucune requête ne sort jamais sur le réseau**. On remplace donc le service externe par un faux, qui rend les réponses que le test a préparées.

| Élément | Rôle |
|---|---|
| un **client HTTP limité** (`scoped_clients`) | un client réglé pour UN service : adresse de base, délai maximal, en-têtes |
| le **pool de cache** `cache.app` | l'endroit où l'application garde des valeurs entre deux requêtes |
| `CacheInterface::get()` | « donne-moi la valeur de cette clé ; si elle n'existe pas, calcule-la avec cette fonction et garde-la » |
| la **fabrique de réponses simulées** (`mock_response_factory`) | en test, le client HTTP rend ses réponses au lieu de sortir sur le réseau |

---

## Étape 1 : un client limité à l'API du fournisseur

Crée `config/packages/http_client.yaml` :

```yaml
framework:
    http_client:
        # Un client « limité » : il parle à une adresse de base, et les chemins qu'on lui donne s'y ajoutent.
        scoped_clients:
            # Autowiring par le nom de l'argument : `HttpClientInterface $apiFournisseur` reçoit CE client.
            api_fournisseur:
                base_uri: '%env(API_FOURNISSEUR_URL)%'
                headers:
                    Accept: 'application/json'
                # Un fournisseur lent ne doit pas bloquer la page : au bout de 5 secondes sans RIEN recevoir, on abandonne
                # (« timeout » est un délai d'inactivité) ; « max_duration » borne la durée TOTALE de la requête, même si
                # des octets arrivent au compte-gouttes.
                timeout: 5
                max_duration: 10
            # L'API d'archivage : un autre service externe, donc un autre client (autre adresse, autre délai).
            api_archivage:
                base_uri: '%env(API_ARCHIVAGE_URL)%'
                timeout: 10
                max_duration: 30

when@test:
    framework:
        http_client:
            # En test, AUCUNE requête ne sort : toute demande, du client par défaut comme des clients limités (ils sont
            # construits par-dessus lui), reçoit la réponse que le test a préparée dans ce service.
            mock_response_factory: App\Tests\Support\ReponsesSimulees
```

Le client `api_fournisseur` ne parle qu'à une adresse de base : un chemin comme `/fournisseurs/3/garantie` s'y ajoute. Les deux délais protègent la page : `timeout` (5 secondes) abandonne si **rien n'arrive** pendant ce temps ; `max_duration` (10 secondes) borne la durée **totale** de la requête, même si le fournisseur envoie des octets au compte-gouttes, ce que `timeout` seul ne verrait pas. Un fournisseur lent ne bloque donc pas la page plus longtemps. Son adresse vient d'une **variable d'environnement** (`API_FOURNISSEUR_URL`), avec une **valeur par défaut** donnée dans `config/services.yaml` (sinon le démarrage échouerait partout où la variable manque). Ajoute dans `parameters:` :

```yaml
    # Les adresses de base des services externes : valeurs par défaut, sinon le démarrage échouerait sans la variable.
    # En test, aucune requête n'y part (voir config/packages/http_client.yaml).
    env(API_FOURNISSEUR_URL): 'https://api.fournisseur.exemple'
    env(API_ARCHIVAGE_URL): 'https://archives.exemple.test'
```

(La seconde adresse sert à l'exercice.) La partie `when@test` du fichier de configuration sera expliquée à l'étape 5.

---

## Étape 2 : un échec propre à l'application

Crée `src/Exception/ServiceExterneIndisponible.php` :

```php
<?php

namespace App\Exception;

/** Un service externe (l'API d'un fournisseur, l'API d'archivage) ne rend pas ce qu'on attend : panne, délai dépassé, réponse illisible. */
class ServiceExterneIndisponible extends \RuntimeException
{
}
```

Une panne du fournisseur, un délai dépassé et une réponse illisible sont trois causes d'une **même** conséquence pour nous : « je n'ai pas la date ». Une exception à nous les rassemble.

---

## Étape 3 : le service, avec sa mise en cache

Crée `src/Service/GarantieFournisseur.php` :

```php
<?php

namespace App\Service;

use App\Entity\Fournisseur;
use App\Exception\ServiceExterneIndisponible;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Lit sur l'API du fournisseur la date de fin de garantie, et la garde en mémoire une heure. */
class GarantieFournisseur
{
    /** La durée de conservation, en secondes. */
    public const DUREE = 3600;

    public function __construct(
        // Le client limité « api_fournisseur » : Symfony le choisit par le NOM de l'argument.
        private HttpClientInterface $apiFournisseur,
        // Le pool « cache.app ».
        private CacheInterface $cache,
    ) {
    }

    public static function cle(Fournisseur $fournisseur): string
    {
        return 'garantie_fournisseur_'.$fournisseur->getId();
    }

    /** @return \DateTimeImmutable|null null quand l'API ne répond pas ou répond autre chose qu'une date */
    public function finDeGarantie(Fournisseur $fournisseur): ?\DateTimeImmutable
    {
        try {
            $date = $this->cache->get(self::cle($fournisseur), function (ItemInterface $item) use ($fournisseur): string {
                $item->expiresAfter(self::DUREE);

                // Une exception traverse `get()` sans rien écrire dans le pool : l'échec n'est jamais gardé en
                // mémoire, la page suivante réessaie.
                return $this->lireSurLApi($fournisseur);
            });
        } catch (ServiceExterneIndisponible) {
            return null;
        }

        return new \DateTimeImmutable($date);
    }

    private function lireSurLApi(Fournisseur $fournisseur): string
    {
        try {
            $donnees = $this->apiFournisseur->request('GET', '/fournisseurs/'.$fournisseur->getId().'/garantie')->toArray();
        } catch (ExceptionInterface $e) {
            // Réponse 4xx ou 5xx, délai dépassé, connexion impossible, corps qui n'est pas du JSON : tout est « indisponible ».
            throw new ServiceExterneIndisponible('L\'API du fournisseur ne répond pas.', 0, $e);
        }

        $date = $donnees['fin_garantie'] ?? null;
        if (!\is_string($date) || 1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $morceaux) || !checkdate((int) $morceaux[2], (int) $morceaux[3], (int) $morceaux[1])) {
            throw new ServiceExterneIndisponible('L\'API du fournisseur a rendu une date illisible.');
        }

        return $date;
    }
}
```

Observe :

- **le client est choisi par le nom de l'argument** : `$apiFournisseur` reçoit le client `api_fournisseur`, `CacheInterface` reçoit le pool `cache.app` ;
- `get($cle, $fonction)` appelle la fonction **seulement si la valeur n'existe pas** (ou a expiré) ; `expiresAfter(3600)` la garde une heure ;
- une exception levée dans la fonction **traverse** `get()` sans rien écrire dans le pool : **un échec n'est jamais gardé en mémoire**, la page suivante réessaie. C'est voulu : sinon une panne d'une seconde priverait la page de sa date pendant une heure ;
- `ExceptionInterface` (du contrat `HttpClient`) rassemble **toutes** les erreurs du client : réponse 4xx ou 5xx (levée par `toArray()`), délai dépassé, connexion impossible, JSON illisible ;
- la réponse de l'API est **validée** : une chaîne qui n'est pas une date est refusée, pas affichée.

---

## Étape 4 : le contrôleur et la fiche

Dans `src/Controller/FournisseurController.php`, ajoute `use App\Service\GarantieFournisseur;` et change la méthode `fiche()` :

```php
    #[Route('/fournisseurs/{id}', name: 'fournisseur_fiche', requirements: ['id' => '\d+'])]
    public function fiche(int $id, FournisseurRepository $fournisseurRepository, GarantieFournisseur $garantie): Response
    {
        $fournisseur = $fournisseurRepository->find($id) ?? throw $this->createNotFoundException('Fournisseur introuvable.');

        return $this->render('fournisseur/fiche.html.twig', [
            'fournisseur' => $fournisseur,
            // null quand l'API du fournisseur est en panne : la fiche s'affiche quand même, avec un message.
            'finDeGarantie' => $garantie->finDeGarantie($fournisseur),
        ]);
    }
```

Et dans `templates/fournisseur/fiche.html.twig`, avant le paragraphe des liens :

```twig
    {% if finDeGarantie %}
        <p class="garantie">Fin de garantie : {{ finDeGarantie|date('d/m/Y') }}</p>
    {% else %}
        <p class="garantie garantie-indisponible">La date de fin de garantie est indisponible pour le moment : le service du fournisseur ne répond pas. Réessaie dans quelques minutes.</p>
    {% endif %}
```

Une panne du fournisseur donne **un message à l'écran**, et la page reste une page : pas de code 500.

---

## Étape 5 : un faux service externe pour les tests

Le test ne doit jamais appeler le vrai fournisseur. Crée `tests/Support/ReponsesSimulees.php` :

```php
<?php

namespace App\Tests\Support;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Le faux service externe des tests (framework.http_client.mock_response_factory, en environnement de test).
 *
 * Chaque test prépare les réponses dans l'ordre où les demandes arriveront, puis relit les demandes envoyées. Une
 * demande sans réponse préparée est une erreur : elle prouve que le code appelle le service externe quand le test
 * ne s'y attendait pas, et rien ne sort jamais sur le réseau.
 */
class ReponsesSimulees
{
    /** @var list<ResponseInterface|\Closure> */
    private array $file = [];

    /** @var list<array{methode: string, url: string, corps: string}> */
    private array $demandes = [];

    /** Une réponse (ou une fonction qui en rend une, ou qui lève une exception) par demande à venir. */
    public function prevoir(ResponseInterface|\Closure ...$reponses): void
    {
        foreach ($reponses as $reponse) {
            $this->file[] = $reponse;
        }
    }

    /** @return list<array{methode: string, url: string, corps: string}> */
    public function demandes(): array
    {
        return $this->demandes;
    }

    /** Appelée par le client HTTP à chaque demande. */
    public function __invoke(string $methode, string $url, array $options = []): ResponseInterface
    {
        $this->demandes[] = [
            'methode' => $methode,
            'url' => $url,
            'corps' => \is_string($options['body'] ?? null) ? $options['body'] : '',
        ];

        $suivante = array_shift($this->file) ?? throw new \LogicException(sprintf('Aucune réponse préparée pour %s %s : le test doit appeler prevoir().', $methode, $url));

        return $suivante instanceof \Closure ? $suivante($methode, $url, $options) : $suivante;
    }
}
```

Puis branche-le : la fin de `config/packages/http_client.yaml` (la partie `when@test`) dit au client HTTP, **en environnement de test seulement**, de passer par ce service. Et dans `config/services.yaml`, tout en bas, rends-le accessible aux tests :

```yaml
when@test:
    services:
        # Le faux service externe des tests : il reçoit les demandes et rend les réponses que chaque test a préparées.
        # Public : le test le lit dans le conteneur pour préparer ses réponses et relire les demandes envoyées.
        App\Tests\Support\ReponsesSimulees:
            public: true
```

(Si ton fichier a déjà un bloc `when@test:`, ajoute `services:` dedans.)

**S'applique-t-il aux clients limités ?** Oui, et c'est vérifiable dans le code de Symfony (`FrameworkExtension`, méthode `registerHttpClientConfiguration`) : la fabrique de réponses **enveloppe le client par défaut** (`http_client`), et chaque client limité est **construit par-dessus** celui-là. Un client limité passe donc par le faux service. On ne remplace jamais le client dans le code de l'application : c'est la configuration de test qui décide.

Un test prépare ses réponses puis lit ce qui a été demandé :

```php
$this->reponses()->prevoir(new MockResponse('{"fin_garantie":"2027-03-01"}', ['http_code' => 200]));
// ... on affiche la fiche ...
self::assertCount(1, $this->reponses()->demandes());
```

Une demande **sans réponse préparée** lève une exception : c'est le garde-fou. Si le code appelait le service externe sans que le test le sache, le test échouerait au lieu de sortir sur le réseau.

---

## Étape 6 : essayer

1. Vérifie la configuration du client :

```bash
docker compose exec app php bin/console debug:config framework http_client
```

2. Liste les pools de cache, puis vide celui de l'application :

```bash
docker compose exec app php bin/console cache:pool:list
docker compose exec app php bin/console cache:pool:clear cache.app
```

3. Ouvre la fiche d'un fournisseur : l'adresse par défaut n'existe pas, tu dois voir le message « indisponible » (et **pas** une erreur 500). C'est exactement le comportement voulu en cas de panne.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée la configuration du client, l'exception, le service, le faux service externe, adapte le contrôleur et la fiche, puis lance `bin/exercice test` : les tests de la démonstration (la fiche, la mise en cache, les pannes) doivent passer. Clique sur « Terminé ».
