## Ce que nous construisons

Les règles de `access_control` du sujet 10 disent « tel chemin exige tel rôle ». Parfois, un rôle ne suffit pas : on veut une **condition**. Par exemple : « le résumé du parc est réservé aux techniciens, **et en lecture seulement** ».

`access_control` accepte pour cela une option `allow_if`, qui contient une **expression** : une petite formule écrite dans le langage du composant **ExpressionLanguage**. Cette partie en ajoute une, puis la teste.

---

## Étape 1 : une règle écrite en expression

Dans `config/packages/security.yaml`, ajoute cette règle dans `access_control`, **après** celle de `/mon-compte` et **avant** celle de `/flux` :

```yaml
        # Une règle écrite en expression : le résumé du parc est réservé aux techniciens, en lecture seulement.
        - { path: '^/(?:(?:fr|en)/)?parc', allow_if: "is_granted('ROLE_TECHNICIEN') and request.isMethodSafe()" }
```

Lis l'expression :

- `is_granted('ROLE_TECHNICIEN')` : la même question que `denyAccessUnlessGranted()`, posée depuis la configuration ;
- `request.isMethodSafe()` : `request` est la requête en cours, et `isMethodSafe()` répond « oui » pour `GET` et `HEAD`, des méthodes qui ne modifient rien ;
- `and` relie les deux conditions : il faut **les deux**.

Le chemin `^/(?:(?:fr|en)/)?parc` couvre `/parc`, `/fr/parc` et `/en/parc`. La règle est placée **avant** la règle générale `^/` du sujet 10, qui sinon l'avalerait : seule la **première** règle qui correspond s'applique.

Les variables disponibles dans l'expression sont peu nombreuses : `user` (l'utilisateur connecté, `null` pour un anonyme), `request`, `token`, `role_names`, et les fonctions `is_granted()`, `is_authenticated()`, `is_fully_authenticated()`, `is_remember_me()`.

Une expression est **du texte**, évalué à chaque requête. Reste simple : une condition longue se range mieux dans un contrôle des droits (étape 1 de la partie 1), qu'on peut tester et relire.

---

## Étape 2 : essayer et tester

1. Connecte-toi avec un compte **sans rôle** : `/fr/parc` renvoie **403**.
2. Avec `ROLE_TECHNICIEN` : `/fr/parc` s'affiche.
3. Avec `curl` ou un formulaire, envoie un `POST` sur `/fr/parc` : **403**, même pour un technicien, parce que la méthode n'est pas « sûre ».

Un test de la même règle, écrit avec `loginUser()` comme au sujet 10 :

```php
public function testLeResumeDuParcEstReserveAuxTechniciens(): void
{
    $client = static::createClient();
    $client->loginUser($this->creerUtilisateur('simple@exemple.test'));
    $client->request('GET', '/fr/parc');
    self::assertResponseStatusCodeSame(403);

    $client->loginUser($this->creerUtilisateur('technicien@exemple.test', ['ROLE_TECHNICIEN']));
    $client->request('GET', '/fr/parc');
    self::assertResponseIsSuccessful();

    $client->request('POST', '/fr/parc');
    self::assertResponseStatusCodeSame(403);
}
```

Ici, `creerUtilisateur()` est une petite méthode d'aide de ton test, qui crée et enregistre un `Utilisateur` avec les rôles demandés.

Une dernière idée, avant l'exercice. Plusieurs contrôles répondent à chaque question : celui des matériels, celui des rôles, celui des expressions… Symfony les interroge **tous**, puis combine leurs réponses selon la **stratégie de décision**. Par défaut, **un seul « oui » suffit**. La partie 3 montre ce qui change quand on modifie cette règle.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute la règle `allow_if`, essaie les trois cas de l'étape 2, puis relance `php bin/phpunit tests/Junior` (ton technicien de test voit toujours `/parc`). Clique ensuite sur « Terminé ».
