## Ce que nous construisons

Jusqu'ici, ceux qui se connectent sont des **personnes** : elles tapent une adresse e-mail et un mot de passe dans un formulaire, et le navigateur garde leur session dans un cookie. Un **programme** ne fait rien de tout cela. Un lecteur de codes-barres qui fait l'inventaire du parc n'a ni formulaire ni cookie : à **chaque** requête, il se présente avec une **clé** dans un **en-tête** HTTP, et il attend une réponse **lisible par une machine** : du JSON.

Cette partie construit ce petit service sur la partie **matériel** :

- un **authentificateur** personnalisé, qui lit l'en-tête `X-Cle-Inventaire` et décide si la requête est authentifiée ;
- un **pare-feu à part**, **sans état** : aucune session, aucun cookie, la clé ne vaut que sur `/inventaire` ;
- une route `/inventaire/materiels` qui répond en **JSON** et qui sait **qui** l'appelle, grâce à `#[CurrentUser]` ;
- un nouveau rôle, `ROLE_INVENTAIRE`, rangé dans la **hiérarchie des rôles** ;
- des tests qui appellent la route avec la bonne clé, une fausse, ou sans clé.

Ici, la clé est **une seule valeur de configuration** et le lecteur n'a **pas de compte en base** : c'est un utilisateur fabriqué en mémoire. L'exercice qui suit protège une autre partie du parc, et ses choix sont différents : à toi de voir ce qui change (qui est reconnu, où se range le secret, quels messages d'erreur).

---

## Étape 1 : une clé qui ne vit pas dans le code

Une clé est un **secret** : elle ne s'écrit jamais en dur dans une classe, ni dans un fichier du dépôt. Elle vient de la configuration, par une **variable d'environnement**.

Dans `config/services.yaml`, ajoute **à la fin de la section `parameters`** :

```yaml
    # La clé du lecteur d'inventaire : VIDE par défaut, ce qui ferme la porte (l'authentificateur refuse tout quand elle est vide).
    # En développement, .env.local la fixe ; en production, la variable d'environnement du serveur. Jamais dans le dépôt.
    env(CLE_INVENTAIRE): ''
```

Ajoute ensuite le service de l'authentificateur, **à la suite** des services déjà déclarés dans la section `services` :

```yaml
    App\Security\AuthentificateurParCleInventaire:
        arguments:
            $cle: '%env(CLE_INVENTAIRE)%'
```

Et, dans le bloc `when@test`, sous `parameters`, une clé **fictive** pour les tests :

```yaml
        # En test, une clé fictive : les tests de l'inventaire l'envoient dans l'en-tête.
        env(CLE_INVENTAIRE): 'cle-de-test'
```

Lis-les :

- `%env(CLE_INVENTAIRE)%` est la valeur de la variable d'environnement `CLE_INVENTAIRE` ;
- la ligne `env(CLE_INVENTAIRE): ''` donne une **valeur par défaut** : sans elle, l'application refuserait de démarrer quand la variable n'existe pas. Le choix de la valeur compte : une clé par défaut **connue** (« secret », « test »…) ouvrirait la porte à n'importe qui sur un serveur où on a oublié de la fixer. Vide, elle ne laisse entrer **personne** (l'authentificateur le vérifie à l'étape 2) ;
- `$cle` est le nom de l'argument du constructeur, que Symfony ne peut pas deviner : une chaîne n'est pas un service. On le **nomme** donc dans la configuration ;
- en développement, crée un fichier `.env.local` à la racine du projet, avec une ligne `CLE_INVENTAIRE=ma-cle-de-dev`. Ce fichier est **ignoré par Git** : la clé ne part jamais dans le dépôt.

---

## Étape 2 : l'authentificateur

Crée `src/Security/AuthentificateurParCleInventaire.php` :

```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentifie le lecteur d'inventaire (un programme, pas une personne) par l'en-tête « X-Cle-Inventaire ».
 *
 * Il n'y a qu'une clé, celle de la configuration, et aucun compte en base : le « lecteur » est un utilisateur en
 * mémoire, créé à la volée, qui porte le rôle ROLE_INVENTAIRE. Le passeport « se valide tout seul » : la clé a déjà
 * été comparée, aucun mot de passe n'est en jeu.
 */
class AuthentificateurParCleInventaire extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const EN_TETE = 'X-Cle-Inventaire';
    public const IDENTIFIANT = 'lecteur-inventaire';
    public const ROLE = 'ROLE_INVENTAIRE';

    public function __construct(private string $cle)
    {
    }

    /** Ne s'occupe de la requête que si l'en-tête est présent ; sans lui, c'est le point d'entrée (start) qui répond. */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::EN_TETE);
    }

    public function authenticate(Request $request): Passport
    {
        $cleRecue = (string) $request->headers->get(self::EN_TETE);

        // Une clé vide dans la configuration refuse tout le monde : sans ce garde-fou, une requête avec un en-tête
        // vide serait reconnue (deux textes vides sont égaux). hash_equals() compare en temps constant : la durée de
        // la comparaison ne renseigne pas sur le nombre de caractères justes.
        if ('' === $this->cle || !hash_equals($this->cle, $cleRecue)) {
            throw new CustomUserMessageAuthenticationException("Clé d'inventaire refusée.");
        }

        // Le badge dit QUI est authentifié. Ici, la fonction fabrique l'utilisateur : aucun fournisseur n'est consulté.
        return new SelfValidatingPassport(new UserBadge(
            self::IDENTIFIANT,
            static fn (string $identifiant): InMemoryUser => new InMemoryUser($identifiant, null, [self::ROLE]),
        ));
    }

    // Succès : on ne répond rien, la requête continue vers le contrôleur.
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Le même message pour tous les échecs : on ne dit pas ce qui était faux.
        return new JsonResponse(['erreur' => "Clé d'inventaire refusée."], Response::HTTP_UNAUTHORIZED);
    }

    /** Appelé quand un visiteur sans clé demande une page protégée : le pare-feu n'a rien à authentifier, il faut lui répondre. */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['erreur' => "Clé d'inventaire absente : envoie l'en-tête X-Cle-Inventaire."], Response::HTTP_UNAUTHORIZED);
    }
}
```

Lis les méthodes dans l'ordre où le pare-feu les appelle :

1. `supports()` répond à « est-ce **mon** affaire ? ». Ici : seulement si l'en-tête est présent. Sans lui, l'authentificateur ne fait rien ; c'est le contrôle d'accès, plus loin, qui refusera la requête anonyme, et le **point d'entrée** (point 6 ci-dessous) qui répondra ;
2. `authenticate()` décide. Elle **compare** la clé reçue à celle de la configuration, et **lève une exception** si elles diffèrent. Sinon elle rend un **passeport**. `hash_equals()` compare en **temps constant** : avec `===`, la durée de la comparaison trahirait combien de premiers caractères sont justes ;
3. le **passeport** (`Passport`) dit **qui** est authentifié et **ce qui a été vérifié**. Un `SelfValidatingPassport` dit « tout est déjà vérifié », ce qui est le cas : on a comparé la clé nous-mêmes, il n'y a pas de mot de passe à contrôler ensuite. Le `UserBadge` porte l'identité : un identifiant, et une **fonction** qui fabrique l'utilisateur. Ici elle fabrique un `InMemoryUser` (un utilisateur **qui n'existe qu'en mémoire**, avec le rôle `ROLE_INVENTAIRE`) : aucun fournisseur d'utilisateurs, aucune table ;
4. `onAuthenticationSuccess()` : rendre `null` veut dire « ne réponds rien, laisse la requête continuer vers le contrôleur » ;
5. `onAuthenticationFailure()` : quand `authenticate()` a levé une exception, c'est **elle** qui fabrique la réponse. Un `JsonResponse` avec le code **401** : « je ne sais pas qui tu es ». **Un seul message** pour tous les échecs : on ne dit pas à un inconnu si sa clé était presque juste ;
6. `start()` est le **point d'entrée** (`AuthenticationEntryPointInterface`) : la réponse à un visiteur **sans clé** qui demande une page protégée. Dans le pare-feu du formulaire, cette réponse est une redirection vers `/connexion` ; pour un programme, une redirection n'a aucun sens : on répond **401 en JSON**.

Le même 401 sert donc deux cas : **clé absente** (`start()`) et **clé fausse** (`onAuthenticationFailure()`), avec deux messages différents que le programme peut lire.

Une clé vide est refusée **avant** la comparaison : sans cette garde, un programme qui enverrait un en-tête **vide** serait reconnu sur un serveur dont la clé n'est pas fixée, puisque deux textes vides sont égaux.

---

## Étape 3 : un pare-feu sans état, un rôle, une règle

Trois ajouts dans `config/packages/security.yaml`.

**Un rôle dans la hiérarchie.** Ajoute cette clé **après** `providers` et **avant** `firewalls` :

```yaml
    # Chaque rôle reçoit aussi les droits de ceux qui sont en dessous de lui.
    role_hierarchy:
        ROLE_INVENTAIRE: ROLE_USER
```

**Un pare-feu à part**, dans `firewalls`, **entre `dev` et `main`** :

```yaml
        # Le lecteur d'inventaire : sans session, authentifié à chaque requête par l'en-tête X-Cle-Inventaire.
        inventaire:
            pattern: ^/inventaire
            stateless: true
            custom_authenticators:
                - App\Security\AuthentificateurParCleInventaire
            # Sans clé, c'est lui qui répond 401 (il implémente le point d'entrée).
            entry_point: App\Security\AuthentificateurParCleInventaire
```

**Une règle d'accès**, juste après celle des matériels dans `access_control` :

```yaml
        - { path: ^/inventaire, roles: ROLE_USER }
```

Lis-les :

- la **hiérarchie** dit « qui a `ROLE_INVENTAIRE` a aussi `ROLE_USER` ». Le lecteur n'a **que** `ROLE_INVENTAIRE`, et pourtant la règle `ROLE_USER` l'ouvre : c'est la hiérarchie qui fait le lien. On l'écrit **une fois**, au lieu de lister les rôles dans chaque règle. Plusieurs rôles hérités s'écrivent en liste : `ROLE_X: [ROLE_A, ROLE_B]` ;
- **les pare-feu sont examinés dans l'ordre**, et **le premier dont le `pattern` correspond** à l'adresse prend la requête : `inventaire` est donc placé **avant** `main`, qui couvre tout. Sans cet ordre, `main` s'emparerait de `/inventaire` ;
- `stateless: true` : le pare-feu **ne crée ni ne lit aucune session** et ne pose aucun cookie. L'authentification a lieu à **chaque** requête, ce qui convient à un programme qui présente sa clé à chaque appel ;
- `custom_authenticators` branche **notre** classe. Il n'y a pas de `provider` ici : la fonction du badge fabrique elle-même l'utilisateur ;
- `entry_point` désigne qui répond à un visiteur sans clé. Un pare-feu a **un seul** point d'entrée : ici notre classe, qui l'implémente ;
- la clé n'ouvre que `/inventaire`. Les pages des matériels sont dans le pare-feu `main`, qui ne connaît pas cet en-tête : l'en-tête n'y change rien.

---

## Étape 4 : une route qui répond en JSON

Crée `src/Controller/InventaireController.php` :

```php
<?php

namespace App\Controller;

use App\Entity\Materiel;
use App\Repository\MaterielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class InventaireController extends AbstractController
{
    // Les matériels, pour le lecteur d'inventaire. L'utilisateur connecté est ici un utilisateur en mémoire :
    // on le demande par l'interface (UserInterface), pas par la classe Utilisateur de la base.
    #[Route('/inventaire/materiels', name: 'inventaire_materiels', methods: ['GET'])]
    public function materiels(#[CurrentUser] UserInterface $lecteur, MaterielRepository $materielRepository): JsonResponse
    {
        $materiels = array_map(
            static fn (Materiel $materiel) => [
                'id' => $materiel->getId(),
                'nom' => $materiel->getNom(),
                'categorie' => $materiel->getCategorie(),
                'numero_serie' => $materiel->getNumeroSerie(),
                'au_rebut' => $materiel->estAuRebut(),
            ],
            $materielRepository->findBy([], ['id' => 'ASC']),
        );

        return $this->json(['lu_par' => $lecteur->getUserIdentifier(), 'materiels' => $materiels]);
    }
}
```

- `$this->json([...])` fabrique une `JsonResponse` : un corps en **JSON** et l'en-tête `Content-Type: application/json`. On lui donne un tableau, jamais du texte JSON écrit à la main ;
- le type de retour `JsonResponse` dit ce que la méthode rend, et un contrôleur relu en deux secondes se comprend mieux ;
- `#[CurrentUser]` demande à Symfony de **donner l'utilisateur connecté** en argument du contrôleur : le contrôleur ne le cherche pas lui-même. Le type de l'argument est l'**interface** `UserInterface`, parce que ce lecteur n'est pas un `Utilisateur` de la base ; `getUserIdentifier()` rend son identifiant ;
- la route n'accepte que `GET`, et elle ne fait **aucun contrôle d'accès** elle-même : la règle `ROLE_USER` de l'étape 3 s'exécute **avant** le contrôleur.

---

## Étape 5 : les tests

Crée `tests/Junior/InventaireTest.php` :

```php
<?php

namespace App\Tests\Junior;

use App\Entity\Materiel;
use App\Security\AuthentificateurParCleInventaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class InventaireTest extends WebTestCase
{
    use AvecBaseVierge;

    // La clé du lecteur en test : celle de `when@test` dans config/services.yaml.
    private const CLE = 'cle-de-test';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->creerUneBaseVierge();
    }

    public function testLaBonneCleOuvreLaListeEnJson(): void
    {
        $this->enregistrer('Imprimante');
        $this->enregistrer('Scanner');

        // Un en-tête « X-Cle-Inventaire » s'écrit « HTTP_X_CLE_INVENTAIRE » dans les variables du serveur.
        $this->client->request('GET', '/inventaire/materiels', [], [], ['HTTP_X_CLE_INVENTAIRE' => self::CLE]);

        // Le lecteur n'a que ROLE_INVENTAIRE : la règle « ROLE_USER » ne l'ouvre que grâce à la hiérarchie des rôles.
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $reponse = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('lecteur-inventaire', $reponse['lu_par']);
        self::assertSame(['Imprimante', 'Scanner'], array_column($reponse['materiels'], 'nom'));
    }

    public function testSansCleLaReponseEst401EnJson(): void
    {
        $this->client->request('GET', '/inventaire/materiels');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            ['erreur' => "Clé d'inventaire absente : envoie l'en-tête X-Cle-Inventaire."],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @dataProvider desClesRefusees
     */
    public function testUneCleFausseOuVideEst401AvecLeMemeMessage(string $cle): void
    {
        $this->client->request('GET', '/inventaire/materiels', [], [], ['HTTP_X_CLE_INVENTAIRE' => $cle]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            ['erreur' => "Clé d'inventaire refusée."],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    /** @return iterable<string, array{string}> */
    public function desClesRefusees(): iterable
    {
        yield 'une autre clé' => ['une-autre-cle'];
        yield 'la bonne clé en majuscules' => [strtoupper(self::CLE)];
        yield 'une clé vide' => [''];
    }

    public function testLaCleNOuvrePasLesPagesDeLApplication(): void
    {
        $this->client->request('GET', '/materiels', [], [], ['HTTP_X_CLE_INVENTAIRE' => self::CLE]);

        self::assertResponseRedirects();
        self::assertStringEndsWith('/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testLaReponseNePoseAucunCookie(): void
    {
        $this->client->request('GET', '/inventaire/materiels', [], [], ['HTTP_X_CLE_INVENTAIRE' => self::CLE]);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->client->getResponse()->headers->getCookies(), 'Un pare-feu sans état ne pose ni session ni cookie.');
    }

    public function testSansCleConfigureePersonneNEntre(): void
    {
        $authentificateur = new AuthentificateurParCleInventaire('');
        $requete = new Request();
        $requete->headers->set('X-Cle-Inventaire', '');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authentificateur->authenticate($requete);
    }

    private function enregistrer(string $nom): Materiel
    {
        $materiel = (new Materiel())->setNom($nom)->setCategorie('Informatique');
        $this->entityManager->persist($materiel);
        $this->entityManager->flush();

        return $materiel;
    }
}
```

- un en-tête HTTP se passe au client de test dans les **variables du serveur** : `X-Cle-Inventaire` devient `HTTP_X_CLE_INVENTAIRE` (préfixe `HTTP_`, majuscules, tirets remplacés par des soulignés) ;
- les trois cas qui comptent : la bonne clé (200, du JSON, la liste), **pas de clé** (401, message d'absence), **une mauvaise clé** (401, message de refus, **le même** pour une clé fausse, presque juste ou vide) ;
- le test de la bonne clé prouve la **hiérarchie** : le lecteur n'a que `ROLE_INVENTAIRE`, et la route exige `ROLE_USER` ;
- `testLaCleNOuvrePasLesPagesDeLApplication` prouve que le pare-feu est **à part** ; `testLaReponseNePoseAucunCookie` prouve qu'il est **sans état** ;
- le dernier test n'appelle pas le serveur : il construit l'authentificateur avec une clé **vide** et vérifie qu'il refuse. Un test d'**unité** suffit quand on veut juste un cas limite.

Lance-les :

```bash
docker compose exec app php bin/phpunit tests/Junior/InventaireTest.php
```

Si un test est rouge, lis son message : il dit le code reçu et ce qu'il attendait.

---

## Étape 6 : essayer à la main

Avec `.env.local` (étape 1) et le serveur lancé, appelle la route avec `curl`. L'option `-i` affiche le code de réponse et les en-têtes :

```bash
curl -i http://localhost:8000/inventaire/materiels
curl -i -H "X-Cle-Inventaire: pas-la-bonne" http://localhost:8000/inventaire/materiels
curl -i -H "X-Cle-Inventaire: ma-cle-de-dev" http://localhost:8000/inventaire/materiels
```

Tu dois voir, dans l'ordre : un **401** « absente », un **401** « refusée », puis un **200** avec la liste en JSON. Regarde les en-têtes de la troisième réponse : **aucun** `Set-Cookie`. Essaie enfin la bonne clé sur `/materiels` : tu es renvoyé vers `/connexion`, comme n'importe quel visiteur.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute les trois morceaux de configuration, crée l'authentificateur, le contrôleur et les tests, lance les tests, puis fais les trois appels de l'étape 6. Clique ensuite sur « Terminé ». L'exercice te demande de protéger un flux de **tickets** : le principe est le même, pas les détails.
