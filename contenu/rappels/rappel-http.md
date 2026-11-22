## Pourquoi ce rappel

Tout ce que fait Symfony tient en une phrase : **il reçoit une requête HTTP et renvoie une réponse HTTP**. Si cette idée est claire, la suite du parcours devient bien plus simple. Prends quinze minutes pour la remettre à neuf.

## Le dialogue entre le navigateur et le serveur

Quand tu tapes une adresse dans ton navigateur, il joue le rôle du **client** : il envoie un message au **serveur**. Le serveur répond par un autre message, puis la conversation s'arrête. Le serveur ne garde pas le souvenir de la requête précédente : chaque échange est indépendant.

## La requête

Une requête contient :

- une **méthode**, qui dit ce que le client veut faire ;
- une **adresse** (le chemin, par exemple `/materiels/2`, et éventuellement des paramètres : `?tri=nom`) ;
- des **en-têtes** : des informations sur le client (langue préférée, type de contenu accepté, cookies…) ;
- parfois un **corps**, par exemple les champs d'un formulaire envoyé.

Voici une requête simple :

```
GET /materiels/2 HTTP/1.1
Host: localhost:8000
Accept: text/html
```

## Les méthodes à connaître

| Méthode | Usage habituel |
|---|---|
| `GET` | Lire une page. Elle ne doit rien modifier. |
| `POST` | Envoyer des données : créer quelque chose, valider un formulaire. |
| `PUT` ou `PATCH` | Remplacer ou modifier une ressource (surtout dans les interfaces de programmation). |
| `DELETE` | Supprimer une ressource. |

Un navigateur sait envoyer `GET` et `POST` depuis un lien ou un formulaire. Retiens surtout cette règle : **une requête `GET` ne change jamais rien**.

## La réponse

Une réponse contient :

- un **code de statut**, qui résume ce qui s'est passé ;
- des **en-têtes** (type du contenu, cookies à enregistrer, durée de conservation en cache…) ;
- un **corps** : la page HTML, du texte, du JSON…

## Les codes de statut

Ils se lisent par famille :

| Famille | Sens | Exemples |
|---|---|---|
| `2xx` | Tout s'est bien passé | `200` (c'est bon), `201` (créé), `204` (rien à renvoyer) |
| `3xx` | Va voir ailleurs | `301` (déplacée définitivement), `302` (redirection temporaire) |
| `4xx` | Le client s'est trompé | `400` (requête mal formée), `403` (interdit), `404` (introuvable), `405` (méthode non permise) |
| `5xx` | Le serveur a un problème | `500` (erreur interne) |

Pour la suite, la distinction la plus utile est celle-ci : **`404` veut dire « cette page n'existe pas », `500` veut dire « mon code a planté »**. Une adresse inconnue ne doit jamais provoquer un `500`.

## Pour vérifier que tu as retenu

Réponds de tête, sans regarder plus haut :

1. Quelle méthode utilises-tu pour afficher une liste ?
2. Quel code renvoie une page qui existe ?
3. Quel code renvoie une page qui n'existe pas ?
4. Qui, du client ou du serveur, envoie la requête ?

Si tu hésites sur une réponse, relis la section correspondante, puis clique sur « Terminé ».
