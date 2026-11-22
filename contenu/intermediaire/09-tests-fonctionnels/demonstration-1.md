## Ce que nous construisons

Depuis le début, tu **vérifies à la main** : tu ouvres la page, tu remplis le formulaire, tu regardes. Les tests d'exercice, eux, vérifient tout cela **tout seuls**, en quelques secondes. Dans ce sujet, tu apprends à écrire **tes propres tests**, et à juger s'ils valent quelque chose.

Un **test fonctionnel** simule un visiteur : il envoie des requêtes à ton application, **comme un navigateur**, et vérifie ce qui revient. Il ne démarre aucun serveur web : le noyau de Symfony traite la requête directement, en mémoire. C'est rapide, et c'est le même code que celui qui tourne en vrai.

Dans cette partie :

- comprendre l'**environnement de test** ;
- préparer une **base vierge** avant chaque test ;
- écrire les premiers tests sur les matériels : la liste, la fiche, une page introuvable.

La partie 2 testera les formulaires et regardera de près ce que le client de test permet d'examiner.

---

## Étape 1 : l'environnement de test

Quand PHPUnit tourne, l'application est démarrée dans l'**environnement `test`** (`APP_ENV=test`, fixé par `phpunit.xml.dist`). Trois conséquences :

- Symfony lit `.env.test` en plus de `.env` : la **base de données de test est un autre fichier** que celle que tu utilises en développement. Un test peut donc tout effacer sans toucher à tes vraies données ;
- les sections `when@test:` de la configuration s'appliquent. Tu en as déjà rencontré deux : `framework.test: true` (qui rend le client de test disponible) et un dossier de téléversements jetable ;
- dans `config/packages/framework.yaml`, la session est stockée **en mémoire** (`mock_file`) au lieu d'un vrai fichier : un test ne laisse rien derrière lui.

Un test fonctionnel étend la classe `WebTestCase`. Les tests de **ton** code vont dans `tests/Junior/`, pour ne pas se mélanger à ceux de la plateforme.

---

## Étape 2 : une base vierge avant chaque test

Un test qui dépend de ce qu'un autre a laissé dans la base est un test **fragile** : il passe ou échoue selon l'ordre. La règle : **chaque test part d'une base vide** et crée lui-même ce dont il a besoin.

Crée `tests/Junior/AvecBaseVierge.php`. C'est un **trait**, pour le partager entre plusieurs classes de test (rappel : un trait partage du code sans héritage).

```php
<?php

namespace App\Tests\Junior;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Avant chaque test : la base de test est recréée, vide, à partir des entités. Un test ne dépend jamais
 * de ce qu'un autre a laissé derrière lui.
 */
trait AvecBaseVierge
{
    private function creerUneBaseVierge(): EntityManagerInterface
    {
        // Le conteneur de test donne accès aux services, même privés : c'est ici que la base est atteinte.
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $metadonnees = $entityManager->getMetadataFactory()->getAllMetadata();
        $outil = new SchemaTool($entityManager);
        $outil->dropSchema($metadonnees);
        $outil->createSchema($metadonnees);

        return $entityManager;
    }
}
```

- `static::getContainer()` est le **conteneur de test** : il donne accès à **tous** les services de l'application, y compris ceux qui sont privés dans la vraie application. C'est ainsi qu'un test atteint Doctrine pour préparer ou lire des données ;
- `SchemaTool` recrée les tables à partir des entités : on les supprime, puis on les crée vides ;
- la méthode renvoie l'`EntityManager`, dont le test se servira pour fabriquer ses données et vérifier la base.

---

## Étape 3 : la classe de test

Crée `tests/Junior/MaterielTest.php`, que tu complèteras en partie 2. Voici son début :

```php
namespace App\Tests\Junior;

use App\Entity\Materiel;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MaterielTest extends WebTestCase
{
    use AvecBaseVierge;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        // createClient() démarre le noyau et renvoie le client de test : il passe avant tout accès au conteneur.
        $this->client = static::createClient();
        $this->entityManager = $this->creerUneBaseVierge();
    }
```

- `setUp()` est appelé **avant chaque test** ;
- `static::createClient()` **démarre le noyau et renvoie le client de test**, un objet de la classe `KernelBrowser` (en Symfony 5.4, l'ancien nom `Client` n'existe plus). Il joue le rôle du navigateur : on dit « client de test » ou, dans le code, `$client`. Il faut l'appeler **avant** d'utiliser le conteneur de test, puisque c'est lui qui démarre le noyau ;
- `creerUneBaseVierge()` est la méthode du trait.

---

## Étape 4 : les premiers tests

Ajoute ces trois tests et la méthode d'aide, dans la classe :

```php
public function testLaListeAfficheLesMateriels(): void
{
    $this->enregistrer('Imprimante', 'Informatique');
    $this->enregistrer('Scanner', 'Informatique');

    $crawler = $this->client->request('GET', '/materiels');

    self::assertResponseIsSuccessful();
    self::assertSelectorTextContains('h1', 'Matériels');
    self::assertCount(2, $crawler->filter('li'));
    self::assertSelectorTextContains('body', 'Scanner');
}

public function testLaFicheAfficheLeMateriel(): void
{
    $materiel = $this->enregistrer('Imprimante', 'Informatique');

    $this->client->request('GET', '/materiels/'.$materiel->getId());

    self::assertResponseIsSuccessful();
    self::assertSelectorTextSame('h1', 'Imprimante');
}

public function testUnMaterielInconnuRenvoie404(): void
{
    $this->client->request('GET', '/materiels/999');

    self::assertResponseStatusCodeSame(404);
}
```

```php
private function enregistrer(string $nom, string $categorie): Materiel
{
    $materiel = (new Materiel())->setNom($nom)->setCategorie($categorie);
    $this->entityManager->persist($materiel);
    $this->entityManager->flush();

    return $materiel;
}
```

Lis le premier test comme une histoire en trois temps :

1. **Préparer** : on enregistre deux matériels dans la base de test (`enregistrer()` fabrique un matériel et le sauvegarde) ;
2. **Agir** : `$this->client->request('GET', '/materiels')` envoie la requête et renvoie un **`Crawler`**, un objet qui représente la page reçue et permet de la parcourir ;
3. **Vérifier** : les **assertions**.

Les assertions de Symfony décrivent la réponse :

- `assertResponseIsSuccessful()` : code 2xx ; `assertResponseStatusCodeSame(404)` : ce code précis ;
- `assertSelectorTextContains('h1', 'Matériels')` : un élément choisi avec un **sélecteur CSS** contient ce texte ; `assertSelectorTextSame` exige le texte **exact** ;
- le `Crawler` répond aux mêmes sélecteurs : `$crawler->filter('li')` renvoie les éléments `<li>`, et `assertCount(2, …)` vérifie leur nombre.

Remarque le **404** : tester un cas où l'application doit **refuser** est aussi important que tester le cas qui marche.

---

## Étape 5 : lancer et lire

```bash
docker compose exec app php bin/phpunit tests/Junior/MaterielTest.php
```

- un point `.` par test réussi, `F` pour un test dont une assertion est fausse, `E` pour une erreur (une exception) ;
- pour un échec, PHPUnit affiche l'assertion et ce qu'il a reçu : lis-le, c'est une description de ce que ton application a fait de travers.

Fais maintenant ce que fait tout bon testeur : **casse volontairement** ton application (par exemple, change `'Matériels'` en `'Materiels'` dans le gabarit de la liste), relance, **vois le test passer au rouge**, puis répare. Un test que tu n'as jamais vu échouer est un test dont tu ne sais pas s'il vérifie quelque chose.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée le trait, la classe `MaterielTest` avec ses trois premiers tests et la méthode d'aide. Lance-les, casse l'application volontairement pour les voir échouer, puis clique sur « Terminé ».
