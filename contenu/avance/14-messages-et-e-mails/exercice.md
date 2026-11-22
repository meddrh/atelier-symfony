## À faire

Tu sais poster un message, le router vers un transport et envoyer un e-mail depuis son gestionnaire. À toi de **prévenir un technicien quand on lui confie un ticket**, en suivant ta fiche de préparation. Tes tests créent leurs propres utilisateurs et ne font aucun accès réseau : le transport `async` est en mémoire (`in-memory://`) et les e-mails partent vers `null://null`. Il n'y a **pas de migration** : la colonne `technicien` existe depuis le sujet 11.

1. **Le champ « Technicien ».**
   - `TicketType` reçoit un champ `technicien` (`EntityType`, **facultatif**, libellé « Technicien », choix vide « Personne »). Ses choix sont les utilisateurs dont le rôle **enregistré** contient `ROLE_TECHNICIEN`, par ordre alphabétique de nom : ni un utilisateur simple, ni un gestionnaire qui n'a que `ROLE_GESTIONNAIRE`.
   - `roles` est une colonne **JSON** : écris une méthode `UtilisateurRepository::findTechniciens()` qui charge les utilisateurs et **filtre en PHP**. Une requête SQL chercherait un morceau de texte dans le JSON : cela dépend du moteur de base, et « ROLE_TECHNICIEN » pourrait être confondu avec un rôle qui le contient. Le filtre en PHP est exact tant que la liste reste courte.
   - **Qui peut choisir le technicien : le gestionnaire (et l'administrateur, qui le contient).** Pour un technicien ou un utilisateur simple, le champ **n'existe pas** (une option du formulaire, décidée par le contrôleur avec `isGranted('ROLE_GESTIONNAIRE')`). Un technicien qui force le formulaire en envoyant un champ `technicien` n'obtient rien : le formulaire refuse les champs en trop, le ticket ne change pas. Le champ apparaît à la **création** d'un ticket comme à sa **modification**.
2. **Le message `App\Message\TicketAffecte`.** `new TicketAffecte($ticketId, $technicienId)`, lu par `getTicketId()` et `getTechnicienId()`. Il ne porte **que des identifiants**.
3. **Le routage.** Le message va dans le transport `async` (`config/packages/messenger.yaml`). Il n'est **pas** traité pendant la requête.
4. **L'émission, dans `TicketController`.**
   - `modifier()` poste le message **après l'enregistrement**, **seulement si le technicien a changé** et qu'il y en a un (affecter, ou passer d'un technicien à un autre). Enregistrer un autre champ n'envoie rien ; retirer le technicien (« Personne ») n'envoie rien non plus. Compare le technicien d'**avant** la lecture du formulaire à celui d'**après**.
   - `nouveau()` poste le message quand le ticket est créé **avec** un technicien.
5. **Le gestionnaire `App\MessageHandler\TicketAffecteHandler`** (`#[AsMessageHandler]`) envoie un `TemplatedEmail` :
   - à l'adresse du technicien, **de la part** de l'expéditeur `app.expediteur` ;
   - objet **exactement** `Nouveau ticket : <titre du ticket>` ;
   - corps en deux modèles Twig (`templates/email/ticket_affecte.txt.twig` et `.html.twig`) qui contiennent le titre, le nom du technicien et un **lien absolu** vers la page `ticket_modifier` du ticket ;
   - il **ne fait rien, sans erreur**, si le ticket n'existe plus ou n'est plus confié à ce technicien quand le message est traité : on ne prévient personne à tort.
6. **Le contexte du routeur.** Le lien absolu s'écrit dans le gestionnaire, qui tourne dans la console : il faut l'adresse du site (`framework.router.default_uri`, déjà réglée à la démonstration par le paramètre `app.url_publique`). Vérifie que ton lien commence par cette adresse.

Contraintes :

- l'émission est **unique** par changement et **jamais** pour un technicien inchangé : testé en lisant le transport après chaque requête ;
- le sujet 11 reste en place : seul un gestionnaire (ou un administrateur) choisit le technicien ; un technicien modifie **ses** tickets sans pouvoir se les réaffecter ;
- un conseil : lance aussi tes tests des démonstrations (le message du matériel, son e-mail) et ceux des sujets précédents (`tests/Junior` compris), pour voir tout de suite si un changement les casse.

Deux conseils :

- **Le transport en mémoire repart de vide à chaque requête.** Lis donc le message **juste après** la requête qui l'a posé : `static::getContainer()->get('messenger.transport.async')->getSent()`.
- **Pour traiter un message dans un test**, ne le laisse pas dans le même noyau que la requête : redémarre un noyau (`static::ensureKernelShutdown(); static::bootKernel();`), comme le fait un `messenger:consume` qui démarre, puis donne le message au bus **marqué comme reçu** : `$bus->dispatch(new Envelope($message, [new ReceivedStamp('async')]))`. Sans ce marquage, le bus le renverrait dans la file au lieu de le traiter. Les assertions `assertEmailCount()`, `assertEmailHeaderSame()` et `assertEmailTextBodyContains()` lisent ensuite les e-mails de ce noyau.

## Squelette des fichiers

Ce sujet crée deux classes, le message et son gestionnaire, et modifie ou ajoute des méthodes à `TicketType`, `UtilisateurRepository` et `TicketController`. Le squelette ne te donne pas la solution : la requête, les champs du formulaire, l'envoi du message et la construction de l'e-mail restent à écrire. Le routage (`messenger.yaml`), les modèles Twig de l'e-mail et le réglage de l'expéditeur ne sont pas dans ce squelette : ce ne sont pas des classes PHP. Le champ `technicien` de `TicketType` (le corps de `buildForm()` et l'option du formulaire dans `configureOptions()`) n'a pas non plus de squelette : ces méthodes gardent leur signature, les modifier est le travail de l'exercice.

Le message. Son constructeur et ses deux méthodes sont donnés par la consigne (`new TicketAffecte($ticketId, $technicienId)`) ; il ne porte que des identifiants.

```php
<?php
// src/Message/TicketAffecte.php

namespace App\Message;

final class TicketAffecte
{
    public function __construct(int $ticketId, int $technicienId)
    {
        // À toi : garder les deux identifiants.
    }

    public function getTicketId(): int
    {
        // À toi : renvoyer l'identifiant du ticket.
    }

    public function getTechnicienId(): int
    {
        // À toi : renvoyer l'identifiant du technicien.
    }
}
```

Le gestionnaire. `#[AsMessageHandler]` le déclare auprès du bus, et sa méthode `__invoke()` reçoit le message. Le constructeur est à toi : décide ce dont le gestionnaire a besoin pour relire le ticket, construire l'adresse du lien et envoyer l'e-mail (l'expéditeur vient du paramètre `app.expediteur`).

```php
<?php
// src/MessageHandler/TicketAffecteHandler.php

namespace App\MessageHandler;

use App\Message\TicketAffecte;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TicketAffecteHandler
{
    public function __invoke(TicketAffecte $message): void
    {
        // À toi : envoyer l'e-mail au technicien, ou ne rien faire (sans erreur) si le ticket n'est plus à lui.
    }
}
```

Les classes que tu as déjà reçoivent des méthodes, **à ajouter** à la suite des autres. Ces blocs ne s'écrivent pas tels quels dans un nouveau fichier. Dans `UtilisateurRepository`, la requête des techniciens, que la consigne nomme :

```php
// à ajouter dans src/Repository/UtilisateurRepository.php

    /**
     * @return list<Utilisateur>
     */
    public function findTechniciens(): array
    {
        // À toi : les utilisateurs qui ont le rôle ROLE_TECHNICIEN, par ordre alphabétique de nom.
    }
```

Dans `TicketType`, le constructeur : le formulaire a besoin de quelque chose pour proposer les techniciens.

```php
// à ajouter dans src/Form/TicketType.php

    public function __construct(UtilisateurRepository $utilisateurRepository)
    {
        // À toi : garder ce dont le formulaire a besoin pour proposer les techniciens.
    }
```

`TicketController` existe déjà, avec ton code. **Ne le remplace pas.** Ses méthodes `nouveau()` et `modifier()` gardent leur route, mais reçoivent maintenant le bus des messages : ce bloc montre leur nouvelle signature. Garde ton code et adapte-le (la signature, puis le corps qui poste le message).

```php
// à modifier dans src/Controller/TicketController.php

    // Ce qui change : reçoit maintenant le bus des messages. Garde ton code et adapte-le.
    #[Route('/materiels/{id}/tickets/nouveau', name: 'ticket_nouveau', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function nouveau(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager, RangeurDeFichiers $rangeur, MessageBusInterface $bus): Response
    {
        // À toi : poster le message quand le ticket est créé avec un technicien.
    }

    // Ce qui change : reçoit maintenant le bus des messages. Garde ton code et adapte-le.
    #[Route('/tickets/{id}/modifier', name: 'ticket_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifier(int $id, Request $request, TicketRepository $ticketRepository, EntityManagerInterface $entityManager, RangeurDeFichiers $rangeur, MessageBusInterface $bus): Response
    {
        // À toi : poster le message après l'enregistrement, seulement si le technicien a changé et qu'il y en a un.
    }
```

N'oublie pas les `use` : `App\Repository\UtilisateurRepository` en haut de `TicketType`, `App\Message\TicketAffecte` et `Symfony\Component\Messenger\MessageBusInterface` en haut de `TicketController`, `App\Entity\Utilisateur` en haut de `UtilisateurRepository`. Le dossier `src/Message/` donne l'espace de noms `App\Message`, `src/MessageHandler/` donne `App\MessageHandler` : c'est la règle PSR-4.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Le message du matériel ne porte que des identifiants.
- Affecter un matériel pose un message dans le transport, et n'envoie aucun e-mail tout de suite.
- Aucun message si l'affectation ne change pas ou si le matériel retourne à personne.
- Un matériel créé déjà affecté envoie un message.
- Le message du matériel est acheminé puis traité : un e-mail part vers le nouvel utilisateur, avec l'expéditeur du paramètre, le matériel, un texte, un HTML et un lien absolu.
- Un message envoyé au bus va dans la file au lieu d'être traité sur le champ.
- Le gestionnaire du matériel ignore sans erreur un matériel ou un utilisateur supprimé.
- Le message `TicketAffecte` ne porte que des identifiants.
- Le formulaire propose les techniciens seulement, par ordre alphabétique, avec « Personne ».
- Le champ technicien est réservé au gestionnaire : absent pour un technicien et pour un utilisateur simple, présent aussi à la création.
- Un technicien ne peut pas changer le technicien en forçant le formulaire : rien ne change, aucun message.
- Un utilisateur qui n'est pas technicien est refusé par le formulaire.
- Affecter un ticket pose un message `TicketAffecte` et enregistre le technicien, sans e-mail avant le traitement.
- Un message par changement de technicien, aucun si rien ne change ou si le technicien est retiré.
- Un ticket créé avec un technicien pose un message, un ticket créé sans technicien n'en pose aucun.
- Le gestionnaire du message envoie l'e-mail au technicien : objet, destinataire, expéditeur, texte, HTML et lien absolu.
- Traiter un message ne le renvoie pas dans la file.
- Le gestionnaire ignore sans erreur un ticket supprimé ou confié à quelqu'un d'autre.
