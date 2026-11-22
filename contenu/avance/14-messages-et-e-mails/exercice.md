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
