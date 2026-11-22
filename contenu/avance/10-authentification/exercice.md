## À faire

Tu sais authentifier un utilisateur par formulaire. À toi de **hiérarchiser les droits**, d'ajouter la page « Mon compte » et d'ouvrir un **flux JSON protégé par un jeton**, en suivant ta fiche de préparation. Tes tests créent leurs propres utilisateurs : rien n'est à préparer en base.

1. **Les rôles.** Dans `config/packages/security.yaml`, une **hiérarchie** : `ROLE_ADMIN` contient `ROLE_GESTIONNAIRE`, qui contient `ROLE_TECHNICIEN`, qui contient `ROLE_USER`. Chaque rôle écrit une seule fois. La clé `role_hierarchy` existe déjà (la démonstration 3 y a mis `ROLE_INVENTAIRE`) : ajoute-y tes rôles, sans la remplacer.
2. **Les règles d'accès**, dans `access_control` (en plus de celle des matériels) :
   - `/tickets` et tout ce qui est dessous, **avec ou sans** préfixe de langue (`/fr/tickets`, `/en/tickets`) : `ROLE_TECHNICIEN` ;
   - `/utilisateurs` et tout ce qui est dessous : `ROLE_ADMIN` ;
   - `/mon-compte` : `ROLE_USER` ;
   - **refus par défaut** : en toute **dernière** règle, `^/` exige `ROLE_USER`. Une page non listée (par exemple `/fournisseurs`) exige alors la connexion. La règle `PUBLIC_ACCESS` de la démonstration, placée avant, garde `/connexion` ouverte.

   Un visiteur anonyme est renvoyé vers `/connexion`. Un utilisateur connecté sans le bon rôle reçoit **403**, **y compris** quand il appelle directement l'adresse d'une action en `POST` (par exemple `/tickets/1/cloturer`) : le refus arrive **avant** toute modification.
3. **« Mon compte ».** La route `/mon-compte` (nom `compte`) affiche le **nom** et l'**adresse e-mail** de l'utilisateur connecté. Le contrôleur reçoit l'utilisateur par l'attribut `#[CurrentUser]` : il ne le cherche pas lui-même. Le menu de `templates/base.html.twig` propose le lien « Mon compte » (clé `nav.compte`, dans les **deux** catalogues, comme les autres) à l'utilisateur connecté. La page ne contient **qu'un seul** lien vers `/deconnexion` : celui du menu.
4. **Le jeton.**
   - `Utilisateur` reçoit `jetonApi`, enregistré dans la colonne **`jeton_api`** : **unique** et **facultative**. Deux utilisateurs sans jeton sont permis ; deux utilisateurs avec le même jeton ne le sont pas. La démonstration n'a pas créé cette colonne : écris la **migration** (nouvelle, qui ne modifie jamais une ancienne), puis lance les tests : `MigrationsTest` te montre le SQL qui manque.
   - Un **authentificateur personnalisé** (classe `App\Security\AuthentificateurParJeton`, dossier `src/Security/`) lit l'en-tête **`X-Jeton`** et retrouve l'utilisateur dont le jeton est exactement celui-là (majuscules et espaces comprises).
   - Un **pare-feu `flux`**, placé **avant** `main`, sur `^/flux`, **sans état** (`stateless: true`) : le flux ne pose aucun cookie, et la connexion par formulaire ne l'ouvre pas. Le jeton, lui, n'ouvre **que** `/flux` : sur les pages de l'application, il ne change rien.
   - Une règle d'accès : `/flux` exige `ROLE_USER`.
5. **Le flux.** `GET /flux/mes-tickets` répond en JSON, avec cette forme exacte :

```json
{"tickets": [{"id": 12, "titre": "Bourrage papier", "statut": "ouvert", "materiel": "Imprimante"}]}
```

   Ce sont les tickets de statut **`ouvert`** (pas `en_cours`, pas `resolu`) des matériels **affectés à l'utilisateur que le jeton désigne**, du plus récent au plus ancien. La requête passe par une méthode de `TicketRepository`.
6. **Les refus.** Toujours en JSON, toujours **401**, avec **exactement** ces messages :

| Situation | Réponse |
|---|---|
| en-tête `X-Jeton` absent | `{"erreur": "Jeton absent : envoie l'en-tête X-Jeton."}` |
| jeton inconnu, vide, ou presque juste (une majuscule, une espace) | `{"erreur": "Jeton invalide."}` |

Contraintes :

- un jeton faux ou absent ne donne **jamais** une erreur 500 ni une réponse 200 : testé dans les deux cas ;
- un utilisateur sans le rôle reçoit 403, jamais une page à moitié affichée : testé en `GET` **et** en `POST` ;
- ne dis **jamais** pourquoi un jeton est refusé (« inconnu », « vide »…) : le même message pour tous les échecs ;
- un conseil : lance aussi tes tests du sujet 9 (`tests/Junior`), pour voir tout de suite si un changement les casse.

Un conseil sur l'ordre : commence par la hiérarchie et les règles d'accès, que tu peux essayer tout de suite avec une connexion par formulaire (donne-toi le rôle voulu avec `doctrine:query:sql "UPDATE utilisateur SET roles = '[\"ROLE_ADMIN\"]' WHERE email = '…'"`). Pour le flux, essaie avec `curl -i -H "X-Jeton: …" http://localhost:8000/flux/mes-tickets` : sans jeton, avec un faux, avec le bon. Si `/flux` sans jeton ne répond pas en JSON, cherche qui doit répondre à un visiteur **anonyme** : c'est le rôle d'un **point d'entrée** (`AuthenticationEntryPointInterface`, option `entry_point`).

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- L'utilisateur est un utilisateur de sécurité : identifiant = e-mail, `ROLE_USER` toujours présent, mot de passe haché.
- Le mot de passe n'est jamais enregistré en clair et se vérifie avec le service de hachage.
- Les pages des matériels renvoient vers `/connexion` sans connexion.
- Un appel direct en `POST` ne supprime pas un matériel sans connexion.
- La page de connexion est publique et porte le champ e-mail, le mot de passe, le jeton CSRF, la case « se souvenir de moi » et le bouton « Se connecter ».
- Une connexion réussie ouvre les matériels.
- Un mauvais mot de passe renvoie au formulaire avec une erreur, et rien n'est ouvert.
- Un jeton CSRF faux refuse la connexion, même avec le bon mot de passe.
- La case « se souvenir de moi » pose le cookie de souvenir seulement quand elle est cochée.
- La déconnexion ferme la session.
- La commande `app:utilisateur:mot-de-passe` enregistre un mot de passe haché.
- La commande refuse une adresse inconnue et un mot de passe vide (code de sortie 1, rien ne change).
- La hiérarchie des rôles ouvre les bonnes pages et refuse les autres (403), pour un utilisateur simple, un technicien, un gestionnaire et un administrateur.
- Une page non listée (par exemple `/fournisseurs`) exige la connexion et renvoie vers `/connexion`, pendant que `/connexion` reste ouverte.
- Sans connexion, les tickets (avec ou sans langue), les utilisateurs et « Mon compte » renvoient vers `/connexion`.
- Un appel direct sans le droit donne 403, en `GET` comme en `POST`, avant toute modification.
- « Mon compte » affiche le nom et l'adresse e-mail de l'utilisateur connecté, et un lien de déconnexion.
- « Mon compte » montre chaque fois l'utilisateur connecté, pas un autre.
- Le jeton ouvre le flux des tickets ouverts de l'utilisateur, du plus récent au plus ancien, au format JSON attendu.
- Un jeton absent donne 401 avec le message JSON en français.
- Un jeton faux, vide ou presque juste donne 401, jamais une erreur du serveur.
- Le flux ne se sert pas de la session de l'application et ne pose aucun cookie.
- Le jeton n'ouvre pas les pages de l'application.
- Le jeton est unique mais facultatif en base.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
