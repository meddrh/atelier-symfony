## Ce que nous construisons

Lire une page, c'est bien. Mais le cœur d'une application, ce sont ses **actions** : envoyer un formulaire, être redirigé, voir un message. Cette partie complète `MaterielTest` avec :

- des tests de **formulaires** : un envoi valide, un envoi refusé ;
- le **suivi des redirections** ;
- l'**examen de la requête et de la réponse** : que sait le client de test sur ce qui vient de se passer ?
- la **configuration du client** : lui faire envoyer les en-têtes d'un autre navigateur.

Une règle domine toute la partie : **un test vérifie ce qu'une action a produit, pas seulement ce qu'elle a répondu**.

---

## Étape 1 : envoyer un formulaire

Ajoute ces deux tests dans `MaterielTest` :

```php
public function testLeFormulaireEnregistreUnMateriel(): void
{
    $this->client->request('GET', '/materiels/nouveau');
    $this->client->submitForm('Enregistrer', [
        'materiel[nom]' => 'Scanner',
        'materiel[categorie]' => 'Informatique',
    ]);

    self::assertResponseRedirects();
    $this->client->followRedirect();
    self::assertSelectorTextContains('body', 'Le matériel a été ajouté.');
    self::assertSame(1, $this->entityManager->getRepository(Materiel::class)->count(['nom' => 'Scanner']));
}

public function testUnNomVideEstRefuseEtRienNEstEnregistre(): void
{
    $this->client->request('GET', '/materiels/nouveau');
    $this->client->submitForm('Enregistrer', [
        'materiel[nom]' => '',
        'materiel[categorie]' => 'Informatique',
    ]);

    self::assertResponseStatusCodeSame(422);
    self::assertSelectorTextContains('body', 'Le nom est obligatoire.');
    self::assertSame(0, $this->entityManager->getRepository(Materiel::class)->count([]));
}
```

- on **ouvre d'abord la page** du formulaire (`request('GET', …)`) : c'est ce qui donne au client de test le formulaire à remplir, avec son jeton de sécurité (CSRF) déjà dedans ;
- `$this->client->submitForm('Enregistrer', [...])` cherche le **bouton** dont le texte est « Enregistrer », trouve son formulaire, remplit les champs donnés (par leur nom : `materiel[nom]`) et l'envoie ;
- dans le premier test, un envoi valide : `assertResponseRedirects()` vérifie qu'on est **redirigé** (on peut aussi préciser l'adresse), puis `followRedirect()` **suit** la redirection, comme un navigateur, et on lit le message flash de la page d'arrivée ;
- la dernière assertion du premier test regarde **la base** : le matériel est bien enregistré. Une redirection seule ne prouve pas que l'enregistrement a eu lieu ;
- dans le second, un envoi refusé : le code **422**, le message d'erreur à l'écran, **et** aucune ligne enregistrée. Là encore, on vérifie l'effet (rien n'est créé) en plus de la réponse.

---

## Étape 2 : d'autres cas de la démonstration précédente

Les règles que tu as écrites aux sujets précédents se testent de la même manière :

```php
public function testUnNumeroDeSerieMalFormeEstRefuse(): void
{
    $this->client->request('GET', '/materiels/nouveau');
    $this->client->submitForm('Enregistrer', [
        'materiel[nom]' => 'Scanner',
        'materiel[categorie]' => 'Informatique',
        'materiel[numeroSerie]' => 'ab1',
    ]);

    self::assertResponseStatusCodeSame(422);
    self::assertSelectorTextContains('body', "n'est pas valide");
}

public function testLeFiltreLibresNeMontreQueLesMaterielsSansUtilisateur(): void
{
    $alice = (new Utilisateur())->setNom('Alice Martin')->setEmail('alice@exemple.test');
    $this->entityManager->persist($alice);
    $this->enregistrer('Imprimante', 'Informatique');
    $this->enregistrer('Écran', 'Informatique')->setUtilisateur($alice);
    $this->entityManager->flush();

    $crawler = $this->client->request('GET', '/materiels?filtre=libres');

    self::assertCount(1, $crawler->filter('li'));
    self::assertSelectorTextContains('li', 'Imprimante');
}
```

Le premier envoie un numéro de série mal formé, le second teste le filtre de la liste (sujet 8) : il prépare un matériel affecté à un utilisateur et un libre, puis vérifie que `?filtre=libres` ne montre que le libre.

---

## Étape 3 : examiner la requête et la réponse

Après chaque `request()`, le client de test garde **tout** ce qui s'est passé, et ton test peut l'interroger :

```php
public function testLaRequeteEtLaReponseSontExaminables(): void
{
    $this->client->request('GET', '/materiels');

    // La requête : la route que Symfony a retrouvée, et la langue de la requête.
    self::assertSame('materiel_liste', $this->client->getRequest()->attributes->get('_route'));
    self::assertSame('fr', $this->client->getRequest()->getLocale());
    // La réponse : son code, ses en-têtes, son contenu.
    self::assertSame(200, $this->client->getResponse()->getStatusCode());
    self::assertStringContainsString('text/html', (string) $this->client->getResponse()->headers->get('Content-Type'));
    self::assertStringContainsString('<h1>Matériels</h1>', (string) $this->client->getResponse()->getContent());
}
```

- `$this->client->getRequest()` est la **requête** que ton application a reçue. `attributes->get('_route')` donne le **nom de la route** que Symfony a retrouvée ; `getLocale()`, la langue de la requête ;
- `$this->client->getResponse()` est la **réponse** : `getStatusCode()`, `headers` (les en-têtes) et `getContent()` (le corps, en texte) ;
- on peut aussi lire la session de la requête (`getRequest()->getSession()`), ce que les tests d'exercice du sujet précédent faisaient.

C'est ce qu'on appelle **examiner la requête et la réponse** : quand une assertion toute faite n'existe pas, tu lis l'objet et tu compares toi-même.

---

## Étape 4 : configurer le client

Un navigateur envoie des **en-têtes** : sa langue préférée, son type de contenu, etc. Le client de test en envoie par défaut quelques-uns (par exemple une langue préférée `en-us` : voilà pourquoi une page qui choisit sa langue d'après le navigateur répond en anglais dans un test, sauf indication contraire !). On les règle :

```php
public function testLeClientPeutEnvoyerLesEnTetesDUnNavigateur(): void
{
    // Un navigateur anglophone : la page sans langue mène à la version anglaise.
    $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'en');

    $this->client->request('GET', '/parc');

    self::assertResponseRedirects('/en/parc');
}
```

- `setServerParameter('HTTP_ACCEPT_LANGUAGE', 'en')` change un paramètre « serveur » pour **toutes** les requêtes suivantes de ce client. Les en-têtes HTTP s'écrivent en majuscules, avec `HTTP_` devant et des `_` à la place des tirets ;
- on peut aussi les donner **à la création** : `static::createClient([], ['HTTP_ACCEPT_LANGUAGE' => 'en'])` (le premier tableau règle le noyau, le second les paramètres du serveur), ou **pour une seule requête**, en dernier argument de `request()` ;
- `followRedirects()` demande au client de **suivre seul** les redirections. Plus besoin d'appeler `followRedirect()` :

```php
public function testLeClientPeutSuivreLesRedirectionsTouteSeul(): void
{
    $this->client->followRedirects();

    $this->client->request('GET', '/materiels/nouveau');
    $this->client->submitForm('Enregistrer', [
        'materiel[nom]' => 'Scanner',
        'materiel[categorie]' => 'Informatique',
    ]);

    self::assertResponseIsSuccessful();
    self::assertRouteSame('materiel_fiche');
    self::assertSelectorTextSame('h1', 'Scanner');
}
```

Ici, `assertRouteSame('materiel_fiche')` vérifie qu'on a **fini sur la bonne page**, par le nom de sa route.

---

## Étape 5 : le conteneur de test, pour aller plus loin

Dans ces tests, le conteneur de test sert deux fois : à atteindre Doctrine (dans le trait) et à lire la base après une action. Tout service de l'application s'obtient de la même façon, avec `static::getContainer()->get('nom_du_service')` : les tests d'exercice du sujet 7 s'en servaient pour demander le service `validator` et valider un objet sans passer par un formulaire.

Retiens l'idée : un test fonctionnel passe par l'**extérieur** (requêtes, pages), mais il peut aussi **entrer** dans l'application, quand c'est le plus simple pour préparer ou vérifier.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Complète `MaterielTest` avec les tests de cette partie. Lance toute la classe : elle doit passer. Puis **casse volontairement** deux choses (une règle de validation, une redirection), et vérifie que les tests correspondants passent au rouge. Clique sur « Terminé ».
