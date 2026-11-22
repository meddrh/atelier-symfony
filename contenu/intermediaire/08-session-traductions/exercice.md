## À faire

Tu sais mémoriser un choix en session sans lui faire confiance, et traduire une page avec un pluriel. À toi de faire la **liste des tickets**, en suivant ta fiche de préparation.

1. **La route** : `/{_locale}/tickets` (nom `ticket_liste`, méthode `GET`), avec `_locale` limité à `fr` et `en` : toute autre langue donne une page introuvable (404). L'adresse `/tickets` **sans langue** (nom `ticket_liste_sans_langue`) redirige vers `/fr/tickets`.
2. **Le filtre** : le paramètre `statut` de l'adresse vaut `ouvert`, `en_cours`, `resolu` ou `tous`. Le filtre choisi est **mémorisé en session sous la clé `tickets.statut`** ; sans paramètre, on reprend celui de la session ; **par défaut** (rien en session, ou rien de valable) : `ouvert`. Une valeur inconnue, dans l'adresse **ou** dans la session — y compris une valeur qui n'est pas un texte —, ramène au filtre par défaut : jamais d'erreur 500.
3. **La liste** : tous les tickets du statut demandé (tous les tickets pour `tous`), du **plus récent au plus ancien**. Chaque ticket est dans un élément de classe `ticket` ; son titre est dans un `<strong>`, son statut **traduit** dans un élément de classe `statut`. La requête passe par une méthode de `TicketRepository`.
4. **Le compteur**, dans un élément de classe `compteur`, avec ces phrases exactes :

| Filtre | 0 | 1 | 3 |
|---|---|---|---|
| `ouvert` (fr) | Aucun ticket ouvert | 1 ticket ouvert | 3 tickets ouverts |
| `en_cours` (fr) | Aucun ticket en cours | 1 ticket en cours | 3 tickets en cours |
| `resolu` (fr) | Aucun ticket résolu | 1 ticket résolu | 3 tickets résolus |
| `tous` (fr) | Aucun ticket | 1 ticket | 3 tickets |
| `ouvert` (en) | No open tickets | 1 open ticket | 3 open tickets |
| `en_cours` (en) | No tickets in progress | 1 ticket in progress | 3 tickets in progress |
| `resolu` (en) | No resolved tickets | 1 resolved ticket | 3 resolved tickets |
| `tous` (en) | No tickets | 1 ticket | 3 tickets |

5. **Les statuts traduits** : français `ouvert`, `en cours`, `résolu` ; anglais `open`, `in progress`, `resolved`.
6. **Les traductions** : dans `translations/messages+intl-icu.fr.yaml` et `translations/messages+intl-icu.en.yaml` (les fichiers de la démonstration), avec **exactement les mêmes clés** dans les deux langues. Les phrases avec pluriel utilisent le format ICU.
7. **La page** déclare sa langue dans `<html lang="…">` (c'est déjà le cas si tu as fait la démonstration), et la fiche d'un matériel propose, sous le titre « Tickets », un lien vers la liste des tickets dans la langue de la page en cours (sur la fiche, qui n'a pas de langue dans son adresse, c'est `/fr/tickets`).

Contraintes :

- la liste des filtres permis est écrite **une seule fois**, et n'importe quelle valeur hors de cette liste revient au filtre par défaut ;
- aucune clé de traduction ne doit s'afficher telle quelle : les tests le vérifient sur toutes les pages, dans les deux langues ;
- ton application doit continuer à faire tout ce qu'elle faisait avant : plusieurs tests le vérifient, comme la démonstration.

Un conseil sur l'ordre : écris d'abord les phrases des deux catalogues et essaie-les dans la page, puis la route, puis le filtre. Pour tester ton filtre à la main, tape `?statut=pirate` dans l'adresse, puis `?statut[]=x`.

## Squelette des fichiers

Ce sujet ne crée aucune classe : il ajoute deux pages à `TicketController` et une méthode à `TicketRepository`, qui existent déjà. Le squelette ne donne que ces méthodes **à ajouter** ; la liste des filtres permis, la session, la requête et les messages sont à toi. Les gabarits et les catalogues de traduction ne sont pas dans ce squelette : ce ne sont pas des classes PHP.

```php
// à ajouter dans src/Controller/TicketController.php

    #[Route('/tickets', name: 'ticket_liste_sans_langue')]
    public function listeSansLangue(): Response
    {
        // À toi : rediriger vers la liste en français.
    }

    #[Route('/{_locale}/tickets', name: 'ticket_liste')]
    public function liste(Request $request, TicketRepository $ticketRepository): Response
    {
        // À toi : déterminer le filtre de statut (adresse, session ou défaut), le mémoriser, puis rendre la liste des tickets.
    }
```

N'oublie pas les `use` en haut du fichier si ton contrôleur ne les a pas déjà : `App\Repository\TicketRepository`, `Symfony\Component\HttpFoundation\Request` et `Symfony\Component\HttpFoundation\Response`. Ces routes sont incomplètes : les langues permises et la méthode `GET` font partie de la consigne.

Dans `TicketRepository`, la requête (le nom `findParStatut` est celui du corrigé ; les tests passent par les pages, pas par ce nom) :

```php
// à ajouter dans src/Repository/TicketRepository.php

    /**
     * @return list<Ticket>
     */
    public function findParStatut(string $filtre): array
    {
        // À toi : les tickets du statut demandé (tous pour « tous »), du plus récent au plus ancien.
    }
```

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Sans filtre, tous les matériels sont listés.
- Le filtre « affectés » ne montre que les matériels affectés.
- Le filtre « libres » ne montre que les matériels sans utilisateur.
- Le filtre choisi est gardé en session, et s'applique encore à la requête suivante.
- Le filtre « tous » remet la liste complète.
- Un filtre inconnu dans l'adresse revient au filtre par défaut et prévient par un message flash.
- Une valeur de session altérée (texte inconnu, texte vide, liste, nombre) revient au filtre par défaut, sans erreur.
- La page du parc compte les matériels avec le pluriel de la langue (0, 1 et 3, en français et en anglais).
- Une langue inconnue renvoie 404.
- `/parc` sans langue redirige selon la langue préférée du navigateur, et vers le français à défaut.
- La langue de la page est déclarée dans le document.
- Le menu est traduit.
- Les deux catalogues ont exactement les mêmes clés.
- Aucune clé de traduction brute n'apparaît sur les pages, dans les deux langues.
- `/tickets` sans langue redirige vers `/fr/tickets`.
- Une langue inconnue sur la liste des tickets renvoie 404.
- Le compteur de tickets accorde le pluriel et le statut, en français et en anglais, pour 0, 1 et 3 tickets et pour chaque filtre.
- Chaque ticket affiche son statut traduit.
- Les tickets sont du plus récent au plus ancien.
- Sans filtre, les tickets ouverts sont montrés par défaut.
- Le filtre des tickets est gardé en session et s'applique dans toutes les langues.
- Un statut inconnu dans l'adresse revient au filtre par défaut.
- Une valeur de session altérée pour les tickets revient au filtre par défaut, sans erreur 500.
- La langue de la liste des tickets est déclarée dans le document.
- La fiche du matériel mène à la liste des tickets.
