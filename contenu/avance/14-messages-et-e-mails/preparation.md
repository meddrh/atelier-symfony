## Penser avant de coder

Après la démonstration, ton application sait poster un message, le mettre en file d'attente et envoyer un e-mail quand on le traite. À toi de l'appliquer aux **tickets** : quand un gestionnaire confie un ticket à un technicien, **le technicien est prévenu par e-mail**.

Voici ce qu'on te demande de réaliser :

- le formulaire des tickets propose un champ « Technicien », avec les utilisateurs qui ont le rôle `ROLE_TECHNICIEN` ;
- quand un ticket est **confié** à un technicien, un message `TicketAffecte` part dans la file `async` ;
- le gestionnaire de ce message envoie au technicien un e-mail dont l'objet est « Nouveau ticket : <titre> », avec un **lien absolu** vers le ticket ;
- **aucun** message si le technicien ne change pas.

Décide maintenant de ce dont tu as besoin, avant de lire la correction. Deux pièges : **qui a le droit de choisir le technicien** (le sujet 11 protège déjà les tickets : ce nouveau champ ne doit pas ouvrir une brèche), et **ce que contient le message** (il attendra dans une file : ce qu'il porte peut être périmé quand on le traite).

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour la liste des techniciens, souviens-toi que la colonne `roles` est du JSON.
- Pour le message, demande-toi ce qui peut arriver entre le moment où on le poste et celui où on le traite.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
